<?php
namespace Y2KWebs\Lafise;

/** Fail closed: a Reversed request example alone is not a refund response contract. */
final class Refunds
{
    public function process(int $orderId, mixed $amount, string $reason): \WP_Error|bool
    {
        if (!current_user_can('edit_shop_orders')) {
            return new \WP_Error('y2k_forbidden', __('No tienes permisos para reembolsar pedidos.', 'y2kwebs-lafise'));
        }
        try {
            return (new Store())->locked($orderId, static function () use ($orderId, $amount): \WP_Error {
                $order = wc_get_order($orderId);
                if (!$order || $order->get_payment_method() !== 'y2kwebs_lafise' || $amount === null
                    || Money::minor(wc_format_decimal($amount, 2)) <= 0
                    || Money::minor(wc_format_decimal($amount, 2)) > Money::minor((string) $order->get_remaining_refund_amount())) {
                    return new \WP_Error('y2k_refund_invalid', __('Importe o pedido no válido.', 'y2kwebs-lafise'));
                }
                return new \WP_Error('y2k_refund_unverified', __('Esta versión no implementa todavía devoluciones bancarias automáticas. Realiza la devolución en LAFISE y regístrala como reembolso manual en WooCommerce.', 'y2kwebs-lafise'));
            });
        } catch (\Throwable) {
            return new \WP_Error('y2k_refund_locked', __('No se ha enviado la devolución. Revisa si hay otra operación en curso.', 'y2kwebs-lafise'));
        }
    }
}
