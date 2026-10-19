# Conversion Pulse

Campaign tracking reliability and observability. A small JavaScript SDK sends popup and form events from your sites. Conversion Pulse stores them durably, processes them asynchronously into campaign analytics, and alerts you when tracking breaks.

Built as an MVP to show backend engineering around correctness under retries and concurrency, tenant isolation, and observable failure handling.

## Features

- **Workspaces** with server-side tenant isolation. Every query goes through the current workspace, and other tenants' records return 404.
- **Sites and campaigns.** Each site gets a public site key; each campaign gets a public ID for the SDK.
- **Tracking SDK** (`sdk/`, ~1.8 KB gzipped) for `page_view`, `campaign_impression`, `campaign_click` and `conversion` events. It keeps a persisted queue, sends in batches, retries with backoff and uses `sendBeacon` on unload.
- **Ingestion API** with validation, rate limiting, an origin allowlist and idempotency by client-generated event ID.
- **Asynchronous processing** into hourly rollups. Each event is counted exactly once, failures are retried with backoff, and a sweeper recovers lost jobs.
- **Analytics** for 24 hours, 7 days, 30 days or a custom range, with period-over-period comparison, per-campaign funnels, and chart and table views.
- **Event pipeline** page for failed-event inspection and safe single or bulk retry.
- **Monitoring alerts** for traffic drops against a same-hours baseline, processing failures and stalled processing. Alerts resolve automatically and email the workspace when they open.

## Stack

Laravel 13 (PHP 8.5) · Inertia v3 + Vue 3 + TypeScript · Tailwind CSS 4 + shadcn-vue · Fortify authentication · Pest · Vitest (via Vite+) · SQLite locally, PostgreSQL + Redis in production.

## Getting started

Requirements: PHP 8.3+ with `pdo_sqlite`, Composer and Node 22. The app is served by [Laravel Herd](https://herd.laravel.com) at `http://pulse.test`; `php artisan serve` also works if you set `APP_URL` to match.

```bash
composer setup                  # install dependencies, create .env, generate the key, migrate, build assets
php artisan db:seed             # demo workspace: demo@conversionpulse.test / password
composer run dev                # Vite, queue worker, scheduler and log tail
```

Then:

1. Sign in as the demo user to see 30 days of sample analytics and two staged alerts.
2. Open **`/demo`**, a sample store running the real SDK. Its popup, CTA and "Buy now" buttons send live events, which the queue worker processes into the dashboard within seconds. The tracker panel can replay an event to show the API reporting a duplicate.

The demo data ends when it was seeded. Re-run `php artisan db:seed` to refresh it; this replaces the previous demo workspace. As time passes without live traffic, the monitoring will (correctly) report traffic drops.

### Installing the tracker on a site

Add a site in the dashboard, then paste its snippet into every page:

```html
<script
    src="https://your-app.example/sdk/pulse.js"
    data-site-key="pk_..."
    defer
></script>
```

Page views are recorded automatically. From your popup code:

```js
ConversionPulse.impression('01JCAMPAIGNID...');
ConversionPulse.click('01JCAMPAIGNID...');
ConversionPulse.conversion({
    campaignId: '01JCAMPAIGNID...',
    properties: { value: 49 },
});
```

Optional script attributes: `data-endpoint` (defaults to `/api/v1/events` on the script's origin) and `data-auto-page-view="false"`.

## Testing and checks

```bash
php artisan test --compact      # Pest feature and unit tests
npm run test:sdk                # SDK tests (Vitest)
composer ci:check               # lint, format, type checks, SDK tests and the PHP suite (what CI runs)
vendor/bin/phpstan analyse --memory-limit=1G
```

## Production configuration

The MVP runs on SQLite with the database queue so it needs no services locally. The code avoids database-specific SQL, apart from a partial unique index that both SQLite and PostgreSQL support. For production:

```dotenv
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis          # after_commit is enabled for the database and redis connections
CACHE_STORE=redis               # also backs rate limiting and scheduler locks
```

Run at least one `php artisan queue:work` process and the scheduler (`php artisan schedule:run` every minute). `npm run build` builds both the dashboard and the SDK (`public/sdk/pulse.js`). Ingestion and alert thresholds live in `config/tracking.php` and can be overridden with environment variables.

## Documentation

- [API contract](docs/api.md): the ingestion endpoint, payloads, responses and SDK delivery semantics.
- [Architecture and decisions](docs/architecture.md): data flow, idempotency, transactions, retries, tenancy, alerts and known limitations.
