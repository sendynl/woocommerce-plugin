<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var string $carrier
 * @var int $instance_id
 * @var array<string,mixed> $selected_pickup_point
 */

?>
<tr>
    <th><?php esc_html_e('Pick-up point', 'sendy'); ?></th>
    <td>
        <input type="hidden" id="sendy-nonce" value="<?php echo esc_html(wp_create_nonce('sendy_store_pickup_point')); ?>">
        <input type="hidden" id="sendy-instance-id" value="<?php echo esc_html($instance_id); ?>">

        <button id="sendy-pick-up-point-button" data-carrier="<?php echo esc_html($carrier); ?>">
            <?php if (is_null($selected_pickup_point)) : ?>
                <?php esc_html_e('Select pick-up-point', 'sendy'); ?>
            <?php else : ?>
                <?php esc_html_e('Change pick-up-point', 'sendy'); ?>
            <?php endif; ?>
        </button>

        <?php if ($selected_pickup_point) : ?>
            <p><b><?php esc_html_e('Selected pickup point', 'sendy'); ?></b></p>

            <p>
                <?php echo esc_html($selected_pickup_point['name']) ?><br>
                <?php echo esc_html($selected_pickup_point['street']) ?> <?php echo esc_html($selected_pickup_point['number']) ?><br>
                <?php echo esc_html($selected_pickup_point['postal_code']) ?> <?php echo esc_html($selected_pickup_point['city']) ?><br>
            </p>
        <?php endif; ?>
    </td>
</tr>
