# Architecture and decisions

## Data flow

```
Browser SDK ──POST /api/v1/events──▶ IngestEvents ──insert-or-ignore──▶ ingested_events (pending)
                                          │
                                          └─ queue ProcessIngestedEvent (after commit)
                                                        │
             events:dispatch-stale (every 5 min) ───────┤  re-queues events whose job never ran
                                                        ▼
                                   ProcessEvent: claim pending → processed
                                                 + upsert event_rollups (one transaction)
                                                        │
                    Dashboard / campaign analytics ◀────┤  read hourly rollups
                    alerts:evaluate (every 15 min) ◀────┘  compares rollups and event states → alerts
```

## Key decisions

### Store first, process later

Ingestion only validates and inserts raw events, then returns `202`. Aggregation happens in queued jobs. This keeps the public endpoint fast and keeps every event even when processing is slow or failing. Raw events are the source of truth: rollups can always be explained by them.

### Idempotency by client-generated event ID

The SDK assigns a UUID to each event and resends the same ID on retry. `ingested_events` has a unique `(site_id, event_id)` constraint, and valid events are written with a single insert-or-ignore statement (`ON CONFLICT DO NOTHING` on PostgreSQL). Two concurrent requests carrying the same event cannot both insert it, because the database settles the race rather than an application-level "check, then insert". The API reports such events as `duplicates`.

### Exactly-once counting with a claim in the same transaction

`ProcessEvent` runs one transaction that:

1. Claims the event with a conditional update: `status = processed WHERE id = ? AND status IN (pending, failed)`.
2. Upserts the hourly rollup with `count = count + 1`.

If the job is delivered twice, or two workers race, the second claim updates zero rows and does nothing. On PostgreSQL the row lock taken by the first update makes the second wait, then re-check the condition. If anything throws, both the claim and the increment roll back, so an event is never counted without being marked, or marked without being counted.

### Retries are safe and observable

- Jobs use `#[Tries(3)]` and `#[Backoff([10, 60])]`. Each failed attempt increments `attempts` and stores `last_error` on the event. After the last attempt the event becomes `failed` with `failed_at`.
- Some failures can never succeed automatically, such as an event for an archived campaign. These throw `EventRejected` and are marked failed immediately, without burning retries.
- Manual retry (single or bulk) resets only events still marked `failed`, then re-queues them. Double clicks and concurrent retries are harmless because processing is idempotent.

### The database is the outbox

Events are committed before their job is queued, and queue connections use `after_commit`. If queueing fails or a job is lost, the event stays `pending`. `events:dispatch-stale` re-queues pending events whose `dispatched_at` is missing or older than 10 minutes. Recently queued events are skipped, so an outage doesn't pile up duplicate jobs.

### Hourly rollups for analytics

Dashboards read `event_rollups` (site, campaign, type, hour → count), not raw events. Queries stay bounded by the number of hours in the range, whatever the traffic volume. Daily buckets are summed in PHP from hourly rows, which keeps the SQL portable. Unique indexes treat `NULL` as distinct, so `campaign_key` mirrors `campaign_id` with 0 for site-level events; this lets the unique `(site_id, campaign_key, type, bucket_start)` key work for page views too.

Rates use impressions as the denominator. Conversion rate counts only conversions attributed to a campaign, so site-wide conversions don't inflate it.

### Tenant isolation

- Every tenant-owned table carries `workspace_id`.
- Controllers query through `$request->user()->currentWorkspace`, never through bare model lookups.
- Policies deny cross-tenant access with `denyAsNotFound()`, so another tenant's record returns 404 rather than revealing that it exists.
- The `workspace` middleware also verifies that the user is still a member of their current workspace.
- The ingestion API resolves the workspace from the site key on the server; clients never send a workspace ID.

There are deliberately no global scopes: scoping is explicit at each query and covered by cross-tenant tests for every route.

### Alerts

`alerts:evaluate` computes the set of conditions breaching right now:

- **Traffic drop:** the last 3 complete hours of site page views or active-campaign impressions are at least 50% below the average of the same hours over the previous 7 days. Subjects averaging under 20 events for that window are ignored.
- **Processing failures:** at least 5 failed events for a site in the last hour.
- **Processing stalled:** events pending for more than 15 minutes, which usually means no queue worker.

The run then reconciles that set with the open alerts:

- New breaches open an alert and email the workspace members.
- Ongoing breaches refresh the alert's numbers.
- Open alerts whose condition has cleared resolve themselves.

A partial unique index on `(site_id, campaign_key, type) WHERE status = 'open'` guarantees at most one open alert per subject, even with concurrent runs. Users acknowledge alerts rather than resolving them, so an alert can't be closed while the problem persists.

### Keeping it simple

- Actions (`app/Actions`) hold the business operations; controllers only authorize, validate and respond.
- No repositories, no event sourcing, and no chart library: the charts are small Vue SVG components.
- Locally it runs on SQLite and the database queue, so no services are needed. PostgreSQL and Redis are the production targets and need only `.env` changes.

## Known limitations

- **Public site keys can be abused.** Anyone can read a site key from a page and send events. The origin allowlist only stops other websites' browsers, rate limiting caps volume, and requests without an `Origin` header are allowed for server-side senders. Bot filtering and signed server-side ingestion are out of scope.
- **UTC only.** Rollups and charts use UTC hours; per-workspace time zones aren't supported.
- **Late events.** Events count in the hour they occurred, so late or retried events change past buckets, and alerts may briefly see a dip before delayed events arrive. Events older than 7 days are rejected.
- **No raw-event retention policy.** Raw events are kept indefinitely. A pruning job would be needed at scale; rollups don't depend on raw events after processing.
- **One job per event.** This keeps failure isolation simple but adds queue overhead at high volume; batching jobs per ingest request would be the next step. An event can also be queued again while its job is still waiting (for example by a retry); the extra job is a no-op.
- **Lists aren't paginated** for sites and campaigns, which is fine at MVP sizes. Failed events and resolved alerts are paginated.
- **No invitations or roles UI.** The schema supports multiple members per workspace with roles, but only the owner exists today.
- **Alert notifications go only by email**, to all workspace members. There are no per-user preferences or Slack/webhook channels.
- **Demo data is static.** The seeded history ends at seeding time; re-seed to refresh it.
