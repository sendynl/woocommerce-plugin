<?php

if (! defined('ABSPATH')) {
    exit;
}

/** @var WC_Order $order */

?>

<p><strong><?php esc_html_e('Shipping method', 'sendy'); ?>:</strong> <?php echo esc_html($order->get_shipping_method()); ?></p>

<?php if ($order->meta_exists('_sendy_pickup_point_id')) : ?>
    <p>
        <strong><?php esc_html_e('Chosen pick-up point', 'sendy'); ?>:</strong><br>

        <?php echo esc_html($order->get_meta('_sendy_pickup_point_data')['name']) ?> (<?php echo esc_html($order->get_meta('_sendy_pickup_point_id')) ?>)<br>
        <?php echo esc_html($order->get_meta('_sendy_pickup_point_data')['street']) ?> <?php echo esc_html($order->get_meta('_sendy_pickup_point_data')['number']) ?><br>
        <?php echo esc_html($order->get_meta('_sendy_pickup_point_data')['postal_code']) ?> <?php echo esc_html($order->get_meta('_sendy_pickup_point_data')['city']) ?>
    </p>

<?php endif; ?>
