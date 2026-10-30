(function () {
    'use strict';
    const root = document.querySelector('[data-status-url]');
    const frame = root.querySelector('iframe');
    const message = root.querySelector('[data-message]');
    let lastAction = '';
    let timer;
    let stopped = false;
    let busy = false;
    const started = Date.now();
    async function poll() {
        if (busy || stopped) return;
        busy = true;
        try {
            const response = await fetch(root.dataset.statusUrl, { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error('Unavailable');
            const result = await response.json();
            if (result.state === 'approved' && result.redirect) {
                const url = new URL(result.redirect, location.href);
                if (url.origin !== location.origin) throw new Error('Invalid return');
                stopped = true; location.replace(url.href); return;
            }
            if (['unknown', 'declined'].includes(result.state) || Date.now() - started > 1800000) {
                stopped = true; frame.hidden = true;
                message.textContent = result.state === 'declined' ? (['authentication_expired','authentication_setup_failed','payment_not_sent'].includes(result.reason)
                    ? 'No se completó la autenticación. Puedes volver a intentar el pago.' : 'El banco ha rechazado el pago.')
                    : 'El pago está pendiente de comprobación. Contacta con la tienda antes de volver a pagar.';
                if (result.retry) {
                    const retry = new URL(result.retry, location.href);
                    if (retry.origin === location.origin) {
                        const link = document.createElement('a'); link.href = retry.href; link.textContent = 'Volver a intentar el pago'; root.append(link);
                    }
                }
                return;
            }
            message.textContent = result.state === 'challenge' ? 'Completa la verificación con tu banco.' : 'Estamos verificando el pago…';
            if (result.action) {
                const key = JSON.stringify(result.action);
                if (key !== lastAction) {
                    const url = new URL(result.action.action);
                    if (url.protocol !== 'https:' || url.username || url.password) throw new Error('Invalid issuer');
                    const form = document.createElement('form');
                    form.method = 'POST'; form.action = url.href; form.target = frame.name;
                    for (const [name, value] of Object.entries(result.action.fields)) {
                        if (!['threeDSMethodData', 'creq', 'threeDSSessionData'].includes(name) || typeof value !== 'string') throw new Error('Invalid field');
                        const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input);
                    }
                    lastAction = key;
                    frame.hidden = result.state !== 'challenge';
                    document.body.append(form); form.submit(); form.remove();
                }
            } else { frame.hidden = true; }
        } catch (_) {
            message.textContent = 'No podemos consultar el estado. No repitas el pago; estamos comprobándolo.';
            if (Date.now() - started > 1800000) stopped = true;
        } finally {
            busy = false;
            clearTimeout(timer);
            if (!stopped) timer = setTimeout(poll, 2500);
        }
    }
    window.addEventListener('message', event => {
        if (event.origin === location.origin && event.source === frame.contentWindow && event.data?.type === 'y2k-3ds-return') {
            clearTimeout(timer); poll();
        }
    });
    window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); });
    poll();
})();
