<input type="hidden" value="false" name="<?php echo esc_html($option_name); ?>">

<label>
    <input type="checkbox" value="true" name="<?php echo esc_html($option_name); ?>" <?php checked(get_option($option_name)); ?>> <?php echo esc_html($extra_description); ?>
</label>
