<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var WC_Order $order
 */

?>

<?php echo esc_html($order->get_shipping_method()); ?>
