(function () {
    'use strict';
    const { createElement: el, useEffect, useRef } = window.wp.element;
    const data = window.wc.wcSettings.getSetting('y2kwebs_lafise_data', {});
    const title = window.wp.htmlEntities.decodeEntities(data.title || 'Tarjeta — LAFISE');
    function Fields(props) {
        const root = useRef(null);
        useEffect(() => props.eventRegistration.onPaymentSetup(async () => {
            try {
                const paymentMethodData = await window.y2kLafisePrepare(root.current.querySelector('[data-y2k-card]'));
                return { type: props.emitResponse.responseTypes.SUCCESS, meta: { paymentMethodData } };
            } catch (_) {
                return { type: props.emitResponse.responseTypes.ERROR, message: window.y2kLafise.error };
            }
        }), [props.eventRegistration, props.emitResponse]);
        // HTML is generated exclusively by Gateway::fields; no merchant or bank HTML is accepted.
        return el('div', { ref: root, dangerouslySetInnerHTML: { __html: data.fields || '' } });
    }
    window.wc.wcBlocksRegistry.registerPaymentMethod({ name: 'y2kwebs_lafise', label: el('span', null, title),
        content: el(Fields), edit: el('span', null, title), canMakePayment: () => true,
        ariaLabel: title, supports: { features: ['products'] } });
})();
