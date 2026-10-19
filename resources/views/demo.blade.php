<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acme Outdoor · Conversion Pulse demo store</title>
    @if ($siteKey)
        <script src="{{ asset('sdk/pulse.js') }}" data-site-key="{{ $siteKey }}" defer></script>
    @endif
    <style>
        :root { color-scheme: light; --ink: #1c1917; --muted: #57534e; --line: #e7e5e4; --accent: #15803d; --surface: #fafaf9; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; color: var(--ink); background: var(--surface); }
        .notice { background: #1c1917; color: #f5f5f4; font-size: 13px; padding: 10px 16px; text-align: center; }
        .notice a { color: #bbf7d0; }
        header, main { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
        header h1 { margin: 0; font-size: 24px; }
        header p { margin: 4px 0 0; color: var(--muted); }
        .grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
        .card { background: white; border: 1px solid var(--line); border-radius: 12px; padding: 16px; }
        .card .art { height: 120px; border-radius: 8px; margin-bottom: 12px; background: linear-gradient(135deg, #d6d3d1, #a8a29e); }
        .card h2 { font-size: 16px; margin: 0 0 4px; }
        .card p { margin: 0 0 12px; color: var(--muted); }
        button { font: inherit; cursor: pointer; border-radius: 8px; border: 1px solid transparent; padding: 8px 14px; }
        .primary { background: var(--accent); color: white; }
        .secondary { background: white; border-color: var(--line); }
        .overlay { position: fixed; inset: 0; background: rgb(0 0 0 / 0.45); display: none; align-items: center; justify-content: center; padding: 16px; }
        .overlay.open { display: flex; }
        .popup { background: white; border-radius: 16px; padding: 28px; max-width: 360px; text-align: center; }
        .popup h2 { margin: 0 0 8px; }
        .popup p { color: var(--muted); }
        .popup .actions { display: flex; gap: 8px; justify-content: center; margin-top: 16px; }
        .panel { position: fixed; right: 16px; bottom: 16px; width: min(380px, calc(100vw - 32px)); background: #0c0a09; color: #e7e5e4; border-radius: 12px; padding: 14px; font-size: 12px; box-shadow: 0 10px 30px rgb(0 0 0 / 0.3); }
        .panel h3 { margin: 0 0 8px; font-size: 13px; color: white; }
        .panel ol { margin: 8px 0; padding-left: 18px; max-height: 140px; overflow: auto; font-family: ui-monospace, monospace; }
        .panel .row { display: flex; gap: 6px; flex-wrap: wrap; }
        .panel button { padding: 4px 10px; font-size: 12px; background: #292524; color: #e7e5e4; border-color: #44403c; }
        .panel pre { margin: 8px 0 0; white-space: pre-wrap; color: #bbf7d0; font-family: ui-monospace, monospace; }
        .missing { max-width: 560px; margin: 64px auto; padding: 24px; background: white; border: 1px solid var(--line); border-radius: 12px; }
        code { background: #f5f5f4; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="notice">
        Demo store for <strong>Conversion Pulse</strong>. Every interaction here sends real events through the JS SDK.
        <a href="{{ route('dashboard') }}">Open the dashboard</a>
    </div>

    @if (! $siteKey || ! $campaignId)
        <div class="missing">
            <h1>Demo data not found</h1>
            <p>Seed the demo workspace first:</p>
            <p><code>php artisan db:seed --class=DemoSeeder</code></p>
        </div>
    @else
        <header>
            <h1>Acme Outdoor</h1>
            <p>Gear for the trail. A page view was recorded when this page loaded.</p>
        </header>

        <main>
            <div class="grid">
                @foreach ([['Trail runner 2', 129], ['Featherlight tent', 349], ['Merino base layer', 79]] as [$product, $price])
                    <div class="card">
                        <div class="art"></div>
                        <h2>{{ $product }}</h2>
                        <p>${{ $price }}</p>
                        <button class="primary" data-buy="{{ $price }}">Buy now</button>
                    </div>
                @endforeach
            </div>
            <p style="margin-top: 24px">
                <button class="secondary" id="show-popup">Show the discount popup again</button>
            </p>
        </main>

        <div class="overlay" id="popup" role="dialog" aria-modal="true" aria-labelledby="popup-title">
            <div class="popup">
                <h2 id="popup-title">Wait! Take 10% off</h2>
                <p>Join our list and get a discount code for your first order.</p>
                <div class="actions">
                    <button class="primary" id="popup-cta">Get my code</button>
                    <button class="secondary" id="popup-close">No thanks</button>
                </div>
            </div>
        </div>

        <aside class="panel" aria-live="polite">
            <h3>Tracker</h3>
            <div id="queue-status">Loading SDK…</div>
            <ol id="log"></ol>
            <div class="row">
                <button id="flush">Send now</button>
                <button id="replay" disabled>Replay last event (duplicate)</button>
            </div>
            <pre id="replay-result" hidden></pre>
        </aside>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const tracker = window.ConversionPulse;
                const campaignId = @json($campaignId);
                const endpoint = @json(route('api.events.store', ['site_key' => $siteKey]));
                const popup = document.getElementById('popup');
                const log = document.getElementById('log');
                const status = document.getElementById('queue-status');
                const replay = document.getElementById('replay');
                let lastEvent = null;

                if (!tracker) {
                    status.textContent = 'The SDK did not load. Run npm run build.';
                    return;
                }

                function remember(label, eventId) {
                    lastEvent = tracker.pending().find((event) => event.event_id === eventId) ?? lastEvent;
                    replay.disabled = lastEvent === null;

                    const item = document.createElement('li');
                    item.textContent = `${label} · ${eventId.slice(0, 8)}…`;
                    log.prepend(item);
                }

                function openPopup() {
                    popup.classList.add('open');
                    remember('impression', tracker.impression(campaignId));
                }

                function refreshStatus() {
                    const pending = tracker.pending().length;
                    status.textContent = pending === 0
                        ? 'All events delivered.'
                        : `${pending} event${pending === 1 ? '' : 's'} queued, sending in batches every 5 seconds.`;
                }

                setTimeout(openPopup, 1500);
                setInterval(refreshStatus, 500);

                document.getElementById('show-popup').addEventListener('click', openPopup);
                document.getElementById('popup-close').addEventListener('click', () => popup.classList.remove('open'));
                document.getElementById('popup-cta').addEventListener('click', () => {
                    remember('click', tracker.click(campaignId));
                    popup.classList.remove('open');
                });
                document.querySelectorAll('[data-buy]').forEach((button) => {
                    button.addEventListener('click', () => {
                        remember('conversion', tracker.conversion({ campaignId, properties: { value: Number(button.dataset.buy) } }));
                    });
                });
                document.getElementById('flush').addEventListener('click', () => tracker.flush());

                replay.addEventListener('click', async () => {
                    await tracker.flush();
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'text/plain' },
                        body: JSON.stringify({ events: [lastEvent] }),
                    });
                    const result = document.getElementById('replay-result');
                    result.hidden = false;
                    result.textContent = `HTTP ${response.status} ${JSON.stringify(await response.json())}`;
                });
            });
        </script>
    @endif
</body>
</html>
