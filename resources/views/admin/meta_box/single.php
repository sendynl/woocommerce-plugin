<?php

use Sendy\WooCommerce\Enums\ProcessingMethod;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var WC_Order $order
 * @var array<string,string> $shops
 * @var array<string,string> $preferences
 */

?>

<?php if (!$order->meta_exists('_sendy_shipment_id')): ?>
    <p><b><?php esc_html_e('Shop', 'sendy')?> </b></p>

    <input type="hidden" id="sendy-create-shipment-nonce" name="sendy_create_shipment_nonce" value="<?php echo esc_html(wp_create_nonce('sendy_create_shipment'))?>">

    <select id="sendy-metabox-shop-dropdown" style="width: 100%">
        <?php foreach ($shops as $id => $sendy_shop) : ?>
            <option value="<?php echo esc_html($id); ?>" <?php selected($id, get_option('sendy_previously_used_shop_id')) ?>><?php echo esc_html($sendy_shop)?> </option>
        <?php endforeach; ?>
    </select>

    <?php if (get_option('sendy_processing_method') === ProcessingMethod::WooCommerce) : ?>

        <p><b><?php esc_html_e('Shipping preference', 'sendy')?> </b></p>

        <select id="sendy-metabox-preference-dropdown" style="width: 100%">
            <?php foreach ($preferences as $id => $sendy_preference) : ?>
                <option value="<?php echo esc_html($id); ?>" <?php selected($id, get_option('sendy_previously_used_preference_id')) ?>><?php echo esc_html($sendy_preference)?> </option>
            <?php endforeach; ?>
        </select>

        <p><b><?php esc_html_e('Amount of packages', 'sendy')?> </b></p>

        <input id="sendy-metabox-amount" type="number" step="1" value="<?php echo esc_html(get_option('sendy_previously_used_amount', 1)); ?>" style="width: 100%;">

    <?php endif; ?>

    <p>
        <button class="button button-primary" id="sendy-metabox-create-shipment-button">
            <?php esc_html_e('Create shipment', 'sendy'); ?>
        </button>
    </p>
<?php else: ?>
    <?php if ($order->meta_exists('_sendy_packages')) : ?>
        <p><b><?php esc_html_e('Track and trace', 'sendy'); ?></b></p>

        <?php foreach ($order->get_meta('_sendy_packages') as $sendy_package) : ?>
            <a href="<?php echo esc_url($sendy_package['tracking_url'])?>" target="_blank">
                <span><?php echo esc_html($sendy_package['package_number']); ?></span>
            </a>
        <?php endforeach; ?>

        <p>
            <a class="button button-primary" href="<?php echo esc_url(add_query_arg(['order_id' => $order->get_id(), 'sendy_action' => 'download_label', 'sendy_download_label_nonce' => wp_create_nonce('sendy_download_label')], admin_url())); ?>">
                <?php if (count($order->get_meta('_sendy_packages')) == 1) : ?>
                    <?php esc_html_e('Print label', 'sendy'); ?>
                <?php else : ?>
                    <?php esc_html_e('Print labels', 'sendy'); ?>
                <?php endif; ?>
            </a>
        </p>
    <?php else : ?>
        <a href="<?php echo esc_url(sprintf("https://app.sendy.nl/shipment/%s/edit", $order->get_meta('_sendy_shipment_id'))); ?>" target="_blank">
            Zending bewerken
        </a>
    <?php endif; ?>
<?php endif; ?>

