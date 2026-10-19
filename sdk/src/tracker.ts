export type EventType =
    | 'page_view'
    | 'campaign_impression'
    | 'campaign_click'
    | 'conversion';

export type EventProperties = Record<string, string | number | boolean | null>;

export type TrackedEvent = {
    event_id: string;
    type: EventType;
    campaign_id?: string;
    occurred_at: string;
    visitor_id: string;
    session_id: string;
    url: string | null;
    referrer: string | null;
    properties?: EventProperties;
};

export type IngestResponse = {
    accepted: number;
    duplicates: number;
    rejected: { index: number; errors: Record<string, string[]> }[];
};

/**
 * The outcome of sending a batch: an HTTP status, or null when the request never reached the server.
 */
export type SendResult = { status: number } | null;

export type Transport = {
    send(url: string, body: string): Promise<SendResult>;
    beacon(url: string, body: string): boolean;
};

export type KeyValueStore = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;

export type TrackerOptions = {
    siteKey: string;
    endpoint: string;
    transport: Transport;
    storage?: KeyValueStore;
    sessionStorage?: KeyValueStore;
    page?: () => { url: string | null; referrer: string | null };
    now?: () => Date;
    uuid?: () => string;
    flushIntervalMs?: number;
    maxBatchSize?: number;
    maxQueueSize?: number;
    retryBaseDelayMs?: number;
    retryMaxDelayMs?: number;
};

export type Tracker = {
    track(
        type: EventType,
        data?: { campaignId?: string; properties?: EventProperties },
    ): string;
    pageView(properties?: EventProperties): string;
    impression(campaignId: string, properties?: EventProperties): string;
    click(campaignId: string, properties?: EventProperties): string;
    conversion(data?: {
        campaignId?: string;
        properties?: EventProperties;
    }): string;
    flush(): Promise<void>;
    flushWithBeacon(): void;
    pending(): readonly TrackedEvent[];
};

/**
 * Create a tracker that queues events, persists them until the server confirms them, and delivers them in batches.
 *
 * Delivery is at-least-once: a batch that fails with a network error, 429 or 5xx is retried with the same event IDs, and the server discards duplicates by event ID.
 */
export function createTracker(options: TrackerOptions): Tracker {
    const flushIntervalMs = options.flushIntervalMs ?? 5000;
    const maxBatchSize = Math.min(options.maxBatchSize ?? 20, 50);
    const maxQueueSize = options.maxQueueSize ?? 500;
    const retryBaseDelayMs = options.retryBaseDelayMs ?? 1000;
    const retryMaxDelayMs = options.retryMaxDelayMs ?? 60_000;
    const now = options.now ?? (() => new Date());
    const uuid = options.uuid ?? randomUuid;
    const page = options.page ?? (() => ({ url: null, referrer: null }));
    const storage = resilient(options.storage);
    const sessionStorage = resilient(options.sessionStorage);

    const queueKey = `cpulse:queue:${options.siteKey}`;
    const url = `${options.endpoint}?site_key=${encodeURIComponent(options.siteKey)}`;
    const visitorId = persistentId(storage, 'cpulse:visitor');
    const sessionId = persistentId(sessionStorage, 'cpulse:session');

    let queue = loadQueue();
    let timer: ReturnType<typeof setTimeout> | undefined;
    let inFlight = false;
    let consecutiveFailures = 0;
    const beaconed = new Set<string>();

    if (queue.length > 0) {
        schedule(0);
    }

    function track(
        type: EventType,
        data: { campaignId?: string; properties?: EventProperties } = {},
    ): string {
        const { url: pageUrl, referrer } = page();
        const event: TrackedEvent = {
            event_id: uuid(),
            type,
            occurred_at: now().toISOString(),
            visitor_id: visitorId,
            session_id: sessionId,
            url: pageUrl,
            referrer,
            ...(data.campaignId ? { campaign_id: data.campaignId } : {}),
            ...(data.properties ? { properties: data.properties } : {}),
        };

        queue.push(event);

        if (queue.length > maxQueueSize) {
            queue = queue.slice(queue.length - maxQueueSize);
        }

        saveQueue();
        schedule(queue.length >= maxBatchSize ? 0 : flushIntervalMs);

        return event.event_id;
    }

    async function flush(): Promise<void> {
        if (inFlight || queue.length === 0) {
            return;
        }

        clearTimeout(timer);
        timer = undefined;
        inFlight = true;

        const batch = queue.slice(0, maxBatchSize);
        let result: SendResult;

        try {
            result = await options.transport.send(
                url,
                JSON.stringify({ events: batch }),
            );
        } catch {
            result = null;
        } finally {
            inFlight = false;
        }

        if (result !== null && !isRetryable(result.status)) {
            // Accepted (202) or permanently refused (other 4xx): either way these events are done.
            remove(batch);
            consecutiveFailures = 0;

            if (queue.length > 0) {
                schedule(0);
            }

            return;
        }

        consecutiveFailures++;
        schedule(retryDelay());
    }

    /**
     * Hand the next batch to the browser to deliver while the page unloads.
     *
     * The events stay queued because a beacon gives no confirmation. If the page survives (or the visitor returns) they are sent again, and the server discards the duplicates.
     */
    function flushWithBeacon(): void {
        const batch = queue
            .filter((event) => !beaconed.has(event.event_id))
            .slice(0, maxBatchSize);

        if (batch.length === 0) {
            return;
        }

        if (options.transport.beacon(url, JSON.stringify({ events: batch }))) {
            batch.forEach((event) => beaconed.add(event.event_id));
        }
    }

    function schedule(delayMs: number): void {
        // Keep an earlier pending flush, and never cut a retry backoff short while the server is failing.
        if (timer !== undefined && (delayMs > 0 || consecutiveFailures > 0)) {
            return;
        }

        clearTimeout(timer);
        timer = setTimeout(() => {
            timer = undefined;
            void flush();
        }, delayMs);
    }

    function retryDelay(): number {
        const exponential = retryBaseDelayMs * 2 ** (consecutiveFailures - 1);

        return Math.min(exponential, retryMaxDelayMs);
    }

    function remove(batch: TrackedEvent[]): void {
        const sent = new Set(batch.map((event) => event.event_id));
        queue = queue.filter((event) => !sent.has(event.event_id));
        saveQueue();
    }

    function loadQueue(): TrackedEvent[] {
        try {
            const stored = JSON.parse(storage.getItem(queueKey) ?? '[]');

            return Array.isArray(stored) ? stored : [];
        } catch {
            return [];
        }
    }

    function saveQueue(): void {
        if (queue.length === 0) {
            storage.removeItem(queueKey);
        } else {
            storage.setItem(queueKey, JSON.stringify(queue));
        }
    }

    function requireCampaign(campaignId: string): string {
        if (!campaignId) {
            throw new Error('Conversion Pulse: a campaign ID is required.');
        }

        return campaignId;
    }

    return {
        track,
        pageView: (properties) => track('page_view', { properties }),
        impression: (campaignId, properties) =>
            track('campaign_impression', {
                campaignId: requireCampaign(campaignId),
                properties,
            }),
        click: (campaignId, properties) =>
            track('campaign_click', {
                campaignId: requireCampaign(campaignId),
                properties,
            }),
        conversion: (data = {}) => track('conversion', data),
        flush,
        flushWithBeacon,
        pending: () => queue,
    };
}

/**
 * Network failures, rate limiting and server errors are worth retrying; other client errors will fail again.
 */
function isRetryable(status: number): boolean {
    return status === 429 || status >= 500;
}

function persistentId(store: KeyValueStore, key: string): string {
    const existing = store.getItem(key);

    if (existing) {
        return existing;
    }

    const id = randomUuid();
    store.setItem(key, id);

    return id;
}

/**
 * Wrap a storage backend so blocked or full storage (private browsing, disabled cookies, quota) degrades to memory instead of throwing.
 */
function resilient(store: KeyValueStore | undefined): KeyValueStore {
    const memory = new Map<string, string>();

    return {
        getItem(key) {
            try {
                return store?.getItem(key) ?? memory.get(key) ?? null;
            } catch {
                return memory.get(key) ?? null;
            }
        },
        setItem(key, value) {
            memory.set(key, value);

            try {
                store?.setItem(key, value);
            } catch {
                // Keep the in-memory copy.
            }
        },
        removeItem(key) {
            memory.delete(key);

            try {
                store?.removeItem(key);
            } catch {
                // Nothing persisted to remove.
            }
        },
    };
}

/**
 * Generate a v4 UUID. crypto.randomUUID is only available in secure contexts, so fall back to getRandomValues on plain-HTTP pages.
 */
function randomUuid(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
