<?php
/**
 * @var WC_Order $order
 * @var array<string,string> $shops
 * @var array<string,string> $preferences
 */
?>

<?php if (!$order->meta_exists('_sendy_shipment_id')): ?>
    <p><b><?php _e('Shop', 'sendy')?> </b></p>

    <input type="hidden" id="sendy-create-shipment-nonce" name="sendy_create_shipment_nonce" value="<?php echo esc_html(wp_create_nonce('sendy_create_shipment'))?>">

    <select id="sendy-metabox-shop-dropdown" style="width: 100%">
        <?php foreach ($shops as $id => $shop) : ?>
            <option value="<?php echo esc_html($id); ?>" <?php selected($id, get_option('sendy_previously_used_shop_id')) ?>><?php echo esc_html($shop)?> </option>
        <?php endforeach; ?>
    </select>

    <p><b><?php _e('Shipping preference', 'sendy')?> </b></p>

    <select id="sendy-metabox-preference-dropdown" style="width: 100%">
        <?php foreach ($preferences as $id => $preference) : ?>
            <option value="<?php echo esc_html($id); ?>" <?php selected($id, get_option('sendy_previously_used_preference_id')) ?>><?php echo esc_html($preference)?> </option>
        <?php endforeach; ?>
    </select>

    <p><b><?php _e('Amount of packages', 'sendy')?> </b></p>

    <input id="sendy-metabox-amount" type="number" step="1" value="<?php echo esc_html(get_option('sendy_previously_used_amount', 1)); ?>" style="width: 100%;">

    <p>
        <button class="button button-primary" id="sendy-metabox-create-shipment-button">
            <?php _e('Create shipment', 'sendy'); ?>
        </button>
    </p>
<?php else: ?>
    <?php if ($order->meta_exists('_sendy_packages')) : ?>
        <p><b><?php _e('Track and trace', 'sendy'); ?></b></p>

        <?php foreach ($order->get_meta('_sendy_packages') as $package) : ?>
            <a href="<?php echo esc_url($package['tracking_url'])?>" target="_blank">
                <span><?php echo esc_html($package['package_number']); ?></span>
            </a>
        <?php endforeach; ?>

        <p>
            <a class="button button-primary" href="<?php echo esc_url(add_query_arg(['order_id' => $order->get_id(), 'sendy_action' => 'download_label', 'sendy_download_label_nonce' => wp_create_nonce('sendy_download_label')], admin_url())); ?>">
                <?php if (count($order->get_meta('_sendy_packages')) == 1) : ?>
                    <?php _e('Print label', 'sendy'); ?>
                <?php else : ?>
                    <?php _e('Print labels', 'sendy'); ?>
                <?php endif; ?>
            </a>
        </p>
    <?php else : ?>
        <a href="https://app.sendy.nl/shipment/<?php echo $order->get_meta('_sendy_shipment_id')?>/edit" target="_blank">
            Zending bewerken
        </a>
    <?php endif; ?>
<?php endif; ?>

