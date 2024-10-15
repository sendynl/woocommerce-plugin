<div id="sendy-create-shipments-modal" style="display: none;">
    <input type="hidden" id="sendy_bulk_modal_nonce" value="<?php echo esc_html(wp_create_nonce('sendy_bulk_modal')); ?>">

    <div class="sendy-modal">
        <?php sendy_fields_generator($fields); ?>

        <button type="button" class="button button-primary" id="sendy-create-shipments-button">
            <?php echo esc_html__('Create shipments', 'sendy'); ?>
        </button>
    </div>
</div>
