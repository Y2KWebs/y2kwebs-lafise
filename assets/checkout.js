(function () {
    'use strict';
    async function prepare(root) {
        if (!root) throw new Error(y2kLafise.error);
        const read = (key) => root.querySelector('[data-card="' + key + '"]').value.trim();
        const number = read('number').replace(/[\s-]/g, '');
        const name = read('name');
        const expiry = read('expiry').match(/^(0[1-9]|1[0-2])\s*\/\s*([0-9]{2})$/);
        const cvv = read('cvv');
        const now = new Date();
        if (!/^[0-9]{12,19}$/.test(number) || !name || !expiry || !/^[0-9]{3,4}$/.test(cvv)
            || new Date(2000 + Number(expiry[2]), Number(expiry[1]), 1) <= now) {
            throw new Error('Comprueba el número de tarjeta, nombre, caducidad MM/AA y código de seguridad.');
        }
        const response = await fetch(y2kLafise.ajax, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
            body: new URLSearchParams({ action: 'y2k_lafise_key', csrf: y2kLafise.csrf }) });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(y2kLafise.error);
        const { encryptCard } = await import(y2kLafise.jwe);
        const encrypted = await encryptCard({ accountNumber: number, name, expirationDate: { month: expiry[1], year: expiry[2] }, cvv2: cvv }, result.data.jwk);
        root.querySelectorAll('input[data-card]').forEach(input => { input.value = ''; });
        return { y2k_card: encrypted, y2k_ticket: result.data.ticket, y2k_csrf: y2kLafise.csrf,
            y2k_width: String(Math.max(1, Math.min(9999, screen.width))), y2k_height: String(Math.max(1, Math.min(9999, screen.height))) };
    }
    window.y2kLafisePrepare = prepare;
    jQuery(function ($) {
        let busy = false;
        let ready = false;
        const form = $('form.checkout, form#order_review');
        const clear = () => { ready = false; busy = false; form.find('[data-y2k-payload]').remove(); };
        const intercept = function () {
            if ($('input[name="payment_method"]:checked').val() !== 'y2kwebs_lafise') { clear(); return true; }
            if (ready) { ready = false; return true; }
            if (busy) return false;
            busy = true;
            const root = this.querySelector('[data-y2k-card]');
            const error = root?.querySelector('[data-y2k-error]');
            if (error) error.textContent = '';
            prepare(root).then(payload => {
                form.find('[data-y2k-payload]').remove();
                Object.entries(payload).forEach(([name, value]) => $('<input>', { type: 'hidden', name, value }).attr('data-y2k-payload', '').appendTo(form));
                ready = true; busy = false;
                form.trigger('submit');
            }).catch(() => { clear(); if (error) error.textContent = y2kLafise.error; });
            return false;
        };
        $('form.checkout').on('checkout_place_order_y2kwebs_lafise', intercept);
        $('form#order_review').on('submit', intercept);
        $(document.body).on('checkout_error updated_checkout', clear);
        window.addEventListener('pagehide', () => {
            clear(); document.querySelectorAll('[data-card]').forEach(input => { input.value = ''; });
        });
    });
})();
