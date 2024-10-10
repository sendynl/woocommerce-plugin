<?php /** @var WC_Order $order */ ?>

<?php if (!$order->meta_exists('_sendy_shipment_id')) : ?>
    <p><?php echo esc_html__('No shipment created yet', 'sendy'); ?></p>

<?php else : ?>
    <?php if ($order->meta_exists('_sendy_packages')) : ?>
        <?php foreach ($order->get_meta('_sendy_packages') as $package) : ?>
            <mark class="order-status status-processing">
                <a href="<?php echo esc_url($package['tracking_url'])?> " target="_blank" class="order-status status-processing">
                    <span><?php echo esc_html($package['package_number']) ?></span>
                </a>
            </mark>
        <?php endforeach; ?>
    <?php else : ?>
        <a href="https://app.sendy.nl/shipment/<?php esc_url($order->get_meta('_sendy_shipment_id')) ?>/edit" target="_blank">
            <?php _e('Edit shipment', 'sendy'); ?>
        </a>
    <?php endif; ?>
<?php endif; ?>
