<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @var int $code
 */

?>
<div class="notice notice-error">
    <p>
        <?php if ($code === 400 || $code === 401) : ?>
            <?php esc_html_e('Could not connect to Sendy. Please try signing in again.', 'sendy'); ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=sendy')); ?>"><?php esc_html_e('Go to settings', 'sendy'); ?></a>
        <?php elseif ($code) : ?>
            <?php /* translators: %d is the HTTP error code returned by the Sendy API */ ?>
            <?php echo esc_html(sprintf(__('Could not connect to Sendy (error %d). Please try again later.', 'sendy'), $code)); ?>
        <?php else : ?>
            <?php esc_html_e('Could not connect to Sendy. Please try again later.', 'sendy'); ?>
        <?php endif; ?>
    </p>
</div>
