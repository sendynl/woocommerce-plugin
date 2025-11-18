<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * @var string $option_name
 * @var array<string,string> $options
 */

?>

<select name="<?php echo esc_html($option_name); ?>">
    <?php foreach ($options as $sendy_key => $sendy_option) : ?>
    <?php foreach ($options as $sendy_key => $sendy_option) : ?>
        <option value="<?php echo esc_html($sendy_key); ?>" <?php selected($sendy_key, get_option($option_name)); ?>><?php echo esc_html($sendy_option); ?></option>
    <?php endforeach; ?>
</select>
