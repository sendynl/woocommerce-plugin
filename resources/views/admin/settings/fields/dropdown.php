<?php
/**
 * @var string $option_name
 * @var array<string,string> $options
 */
?>

<select name="<?php echo esc_html($option_name); ?>">
    <?php foreach ($options as $key => $option) : ?>
        <option value="<?php echo esc_html($key); ?>" <?php selected($key, get_option($option_name)); ?>><?php echo esc_html($option); ?></option>
    <?php endforeach; ?>
</select>
