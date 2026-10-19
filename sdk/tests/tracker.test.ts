import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { createTracker } from '../src/tracker';
import type {
    KeyValueStore,
    SendResult,
    TrackedEvent,
    TrackerOptions,
} from '../src/tracker';

class MemoryStore implements KeyValueStore {
    items = new Map<string, string>();

    getItem(key: string): string | null {
        return this.items.get(key) ?? null;
    }

    setItem(key: string, value: string): void {
        this.items.set(key, value);
    }

    removeItem(key: string): void {
        this.items.delete(key);
    }
}

class FakeTransport {
    sent: { url: string; events: TrackedEvent[] }[] = [];
    beacons: TrackedEvent[][] = [];
    responses: SendResult[] = [];

    async send(url: string, body: string): Promise<SendResult> {
        this.sent.push({ url, events: JSON.parse(body).events });

        return this.responses.length > 0
            ? (this.responses.shift() as SendResult)
            : { status: 202 };
    }

    beacon(_url: string, body: string): boolean {
        this.beacons.push(JSON.parse(body).events);

        return true;
    }

    sentEventIds(): string[] {
        return this.sent.flatMap((request) =>
            request.events.map((event) => event.event_id),
        );
    }
}

let transport: FakeTransport;
let storage: MemoryStore;
let sessionStorage: MemoryStore;
let counter: number;

function makeTracker(overrides: Partial<TrackerOptions> = {}) {
    return createTracker({
        siteKey: 'pk_test',
        endpoint: 'https://pulse.test/api/v1/events',
        transport,
        storage,
        sessionStorage,
        now: () => new Date('2026-10-19T12:00:00.000Z'),
        uuid: () =>
            `00000000-0000-4000-8000-${String(++counter).padStart(12, '0')}`,
        page: () => ({
            url: 'https://shop.example.com/pricing',
            referrer: 'https://google.com/',
        }),
        ...overrides,
    });
}

beforeEach(() => {
    vi.useFakeTimers();
    transport = new FakeTransport();
    storage = new MemoryStore();
    sessionStorage = new MemoryStore();
    counter = 0;
});

afterEach(() => {
    vi.useRealTimers();
});

describe('event payloads', () => {
    it('sends events with IDs, timestamps, visitor context and the site key', async () => {
        const tracker = makeTracker();

        tracker.impression('01jcampaign', { variant: 'b' });
        await tracker.flush();

        expect(transport.sent).toHaveLength(1);
        expect(transport.sent[0].url).toBe(
            'https://pulse.test/api/v1/events?site_key=pk_test',
        );
        expect(transport.sent[0].events[0]).toEqual({
            event_id: '00000000-0000-4000-8000-000000000001',
            type: 'campaign_impression',
            campaign_id: '01jcampaign',
            occurred_at: '2026-10-19T12:00:00.000Z',
            visitor_id: expect.any(String),
            session_id: expect.any(String),
            url: 'https://shop.example.com/pricing',
            referrer: 'https://google.com/',
            properties: { variant: 'b' },
        });
    });

    it('keeps the same visitor ID across trackers sharing storage', async () => {
        makeTracker().pageView();
        const visitorId = storage.getItem('cpulse:visitor');

        const tracker = makeTracker();
        tracker.pageView();
        await tracker.flush();

        expect(visitorId).not.toBeNull();
        expect(transport.sent.at(-1)?.events.at(-1)?.visitor_id).toBe(
            visitorId,
        );
    });

    it('refuses campaign events without a campaign ID', () => {
        const tracker = makeTracker();

        expect(() => tracker.click('')).toThrow('a campaign ID is required');
        expect(tracker.pending()).toHaveLength(0);
    });
});

describe('batching', () => {
    it('flushes queued events after the flush interval', async () => {
        const tracker = makeTracker({ flushIntervalMs: 5000 });

        tracker.pageView();
        tracker.pageView();
        await vi.advanceTimersByTimeAsync(4999);
        expect(transport.sent).toHaveLength(0);
        await vi.advanceTimersByTimeAsync(1);

        expect(transport.sent).toHaveLength(1);
        expect(transport.sent[0].events).toHaveLength(2);
    });

    it('splits a full queue into batches of the maximum size', async () => {
        const tracker = makeTracker({ maxBatchSize: 20 });

        for (let i = 0; i < 25; i++) {
            tracker.pageView();
        }
        await vi.runAllTimersAsync();

        expect(transport.sent.map((request) => request.events.length)).toEqual([
            20, 5,
        ]);
        expect(tracker.pending()).toHaveLength(0);
    });

    it('drops the oldest events when the queue is full', () => {
        const tracker = makeTracker({ maxQueueSize: 3 });

        const ids = [1, 2, 3, 4].map(() => tracker.pageView());

        expect(tracker.pending().map((event) => event.event_id)).toEqual(
            ids.slice(1),
        );
    });
});

describe('delivery and retries', () => {
    it('forgets events once the server accepts them', async () => {
        const tracker = makeTracker();

        tracker.pageView();
        await tracker.flush();

        expect(tracker.pending()).toHaveLength(0);
        expect(storage.getItem('cpulse:queue:pk_test')).toBeNull();
    });

    it.each([
        ['a network error', null],
        ['rate limiting', { status: 429 }],
        ['a server error', { status: 503 }],
    ])(
        'retries the same events after %s',
        async (_reason, failure: SendResult) => {
            const tracker = makeTracker({ retryBaseDelayMs: 1000 });
            transport.responses = [failure];

            tracker.pageView();
            await tracker.flush();
            expect(tracker.pending()).toHaveLength(1);
            await vi.advanceTimersByTimeAsync(1000);

            expect(transport.sent).toHaveLength(2);
            expect(transport.sent[1].events[0].event_id).toBe(
                transport.sent[0].events[0].event_id,
            );
            expect(tracker.pending()).toHaveLength(0);
        },
    );

    it('backs off exponentially while the server keeps failing', async () => {
        const tracker = makeTracker({
            retryBaseDelayMs: 1000,
            retryMaxDelayMs: 4000,
        });
        transport.responses = [null, null, null, null];

        tracker.pageView();
        await tracker.flush();
        await vi.advanceTimersByTimeAsync(1000);
        await vi.advanceTimersByTimeAsync(2000);
        await vi.advanceTimersByTimeAsync(3999);
        expect(transport.sent).toHaveLength(3);
        await vi.advanceTimersByTimeAsync(1);

        expect(transport.sent).toHaveLength(4);
    });

    it('does not cut a backoff short when the queue fills up', async () => {
        const tracker = makeTracker({
            maxBatchSize: 2,
            retryBaseDelayMs: 1000,
        });
        transport.responses = [null];

        tracker.pageView();
        await tracker.flush();
        tracker.pageView();
        tracker.pageView();
        await vi.advanceTimersByTimeAsync(999);

        expect(transport.sent).toHaveLength(1);
    });

    it.each([400, 401, 403, 413, 422])(
        'drops a batch the server refuses with %i',
        async (status) => {
            const tracker = makeTracker();
            transport.responses = [{ status }];

            tracker.pageView();
            await tracker.flush();
            await vi.runAllTimersAsync();

            expect(transport.sent).toHaveLength(1);
            expect(tracker.pending()).toHaveLength(0);
        },
    );

    it('resends events left over from a previous page load', async () => {
        const firstPage = makeTracker();
        transport.responses = [null];
        const eventId = firstPage.pageView();
        await firstPage.flush();

        makeTracker();
        await vi.advanceTimersByTimeAsync(0);

        expect(transport.sentEventIds().at(-1)).toBe(eventId);
    });
});

describe('page unload', () => {
    it('beacons pending events but keeps them until the server confirms', async () => {
        const tracker = makeTracker();
        const eventId = tracker.pageView();

        tracker.flushWithBeacon();

        expect(transport.beacons[0].map((event) => event.event_id)).toEqual([
            eventId,
        ]);
        expect(tracker.pending()).toHaveLength(1);
    });

    it('does not beacon the same events twice', () => {
        const tracker = makeTracker();
        tracker.pageView();

        tracker.flushWithBeacon();
        tracker.flushWithBeacon();

        expect(transport.beacons).toHaveLength(1);
    });
});

describe('storage', () => {
    it('keeps working in memory when storage throws', async () => {
        const blocked: KeyValueStore = {
            getItem: () => {
                throw new Error('SecurityError');
            },
            setItem: () => {
                throw new Error('QuotaExceededError');
            },
            removeItem: () => {
                throw new Error('SecurityError');
            },
        };
        const tracker = makeTracker({
            storage: blocked,
            sessionStorage: blocked,
        });

        tracker.pageView();
        await tracker.flush();

        expect(transport.sent).toHaveLength(1);
        expect(transport.sent[0].events[0].visitor_id).toEqual(
            expect.any(String),
        );
    });
});
