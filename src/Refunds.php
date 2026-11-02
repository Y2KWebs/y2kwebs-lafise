<?php
namespace Y2KWebs\Lafise;

/** Fail closed: a Reversed request example alone is not a refund response contract. */
final class Refunds
{
    public function process(int $orderId, mixed $amount, string $reason): \WP_Error|bool
    {
        if (!current_user_can('edit_shop_orders')) {
            return new \WP_Error('y2k_forbidden', __('You do not have permission to refund orders.', 'y2kwebs-lafise'));
        }
        try {
            return (new Store())->locked($orderId, static function () use ($orderId, $amount): \WP_Error {
                $order = wc_get_order($orderId);
                if (!$order || $order->get_payment_method() !== 'y2kwebs_lafise' || $amount === null
                    || Money::minor(wc_format_decimal($amount, 2)) <= 0
                    || Money::minor(wc_format_decimal($amount, 2)) > Money::minor((string) $order->get_remaining_refund_amount())) {
                    return new \WP_Error('y2k_refund_invalid', __('Invalid amount or order.', 'y2kwebs-lafise'));
                }
                return new \WP_Error('y2k_refund_unverified', __('This version does not yet support automatic bank refunds. Refund the payment through LAFISE and record it as a manual refund in WooCommerce.', 'y2kwebs-lafise'));
            });
        } catch (\Throwable) {
            return new \WP_Error('y2k_refund_locked', __('The refund has not been sent. Check whether another operation is in progress.', 'y2kwebs-lafise'));
        }
    }
}
