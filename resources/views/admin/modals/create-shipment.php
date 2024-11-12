<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var array $fields
 */

?>
<div id="sendy-create-shipments-modal">
    <input type="hidden" id="sendy_bulk_modal_nonce" value="<?php echo esc_html(wp_create_nonce('sendy_bulk_modal')); ?>">

    <div id="sendy-modal">
        <?php sendy_fields_generator($fields); ?>

        <button type="button" class="button button-primary" id="sendy-create-shipments-button">
            <?php esc_html_e('Create shipments', 'sendy'); ?>
        </button>
    </div>
</div>
