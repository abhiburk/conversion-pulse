import { createTracker } from './tracker';
import type { Tracker, Transport } from './tracker';

declare global {
    interface Window {
        ConversionPulse?: Tracker;
    }
}

/**
 * Browser entry point. Configure it from the script tag:
 *
 * <script src="https://app.example.com/sdk/pulse.js" data-site-key="pk_..." defer></script>
 *
 * Optional attributes: data-endpoint (defaults to /api/v1/events on the script's origin) and data-auto-page-view="false".
 */
function boot(): void {
    const script = document.currentScript;

    if (!(script instanceof HTMLScriptElement) || window.ConversionPulse) {
        return;
    }

    const siteKey = script.dataset.siteKey;

    if (!siteKey) {
        console.warn('Conversion Pulse: missing data-site-key attribute.');

        return;
    }

    const tracker = createTracker({
        siteKey,
        endpoint:
            script.dataset.endpoint ??
            new URL('/api/v1/events', script.src).href,
        transport: browserTransport(),
        storage: safeStorage(() => window.localStorage),
        sessionStorage: safeStorage(() => window.sessionStorage),
        page: () => ({
            url: window.location.href,
            referrer: document.referrer || null,
        }),
    });

    window.ConversionPulse = tracker;

    if (script.dataset.autoPageView !== 'false') {
        tracker.pageView();
    }

    window.addEventListener('pagehide', () => tracker.flushWithBeacon());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            tracker.flushWithBeacon();
        }
    });
}

/**
 * Batches are sent as text/plain so the request is a "simple" CORS request: no preflight, and sendBeacon accepts it.
 */
function browserTransport(): Transport {
    return {
        async send(url, body) {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body,
                    headers: { 'Content-Type': 'text/plain' },
                    keepalive: true,
                    credentials: 'omit',
                });

                return { status: response.status };
            } catch {
                return null;
            }
        },
        beacon(url, body) {
            return (
                typeof navigator.sendBeacon === 'function' &&
                navigator.sendBeacon(
                    url,
                    new Blob([body], { type: 'text/plain' }),
                )
            );
        },
    };
}

/**
 * Accessing window.localStorage itself throws when storage is blocked, so resolve it lazily.
 */
function safeStorage(resolve: () => Storage): Storage | undefined {
    try {
        return resolve();
    } catch {
        return undefined;
    }
}

boot();
