<?php /** @var array<string,mixed> $pickup_point */ ?>

<p><b><?php esc_html_e('Your order will be delivered at the selected pick-up point:', 'sendy'); ?></b></p>

<p>
    <?php echo esc_html($pickup_point['name']) ?><br>
    <?php echo esc_html($pickup_point['street']) ?> <?php echo esc_html($pickup_point['number']) ?><br>
    <?php echo esc_html($pickup_point['postal_code']) ?> <?php echo esc_html($pickup_point['city']) ?><br>
</p>
