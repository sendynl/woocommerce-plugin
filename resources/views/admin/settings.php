<?php /** @var string|null $name */ ?>

<div class="wrap">
    <h1><?php echo get_admin_page_title() ?></h1>

    <?php if (!sendy_is_authenticated()) : ?>
        <p><?php echo esc_html__('In order to start using the plug-in you have to authenticate with Sendy. Click the button to start', 'sendy'); ?></p>

        <p>
            <a href="<?php echo esc_url(sendy_initialize_plugin_url()); ?>" class="button button-primary">
                <?php echo esc_html__('Authenticate', 'sendy'); ?>
            </a>
        </p>
    <?php else: ?>

        <table class="form-table">
            <tbody>
            <tr>
                <th><?php echo esc_html__('Authentication', 'sendy'); ?></th>
                <td>
                    <p><?php echo esc_html__(sprintf("Authenticated as %s", $name), 'sendy'); ?></p>

                    <p>
                        <a class="button" href="<?php echo esc_url(admin_url('?sendy_logout')); ?>">
                            <?php echo esc_html__('Log out', 'sendy'); ?>
                        </a>
                    </p>
                </td>
            </tr>
            </tbody>
        </table>

        <form action="options.php" method="post">
            <?php

            settings_fields('sendy_general_settings');

            do_settings_sections('sendy');

            submit_button(__('Save settings', 'sendy'));
            ?>
        </form>
    <?php endif; ?>
</div>
