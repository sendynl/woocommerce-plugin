<?php

use Sendy\WooCommerce\Enums\ProcessingMethod;

/**
 * Base class for the AJAX endpoint tests.
 *
 * Signs in an administrator with the WooCommerce capabilities the endpoints
 * require, resets the fake orders and the flashed notices, defaults the
 * processing method to WooCommerce, and clears the faked API connection
 * again afterwards.
 */
abstract class Sendy_Ajax_TestCase extends WP_Ajax_UnitTestCase
{
    use Sendy_Fakes_Api_Connection;

    public function setUp(): void
    {
        parent::setUp();

        Sendy_Fake_Order::reset();

        $user = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user);
        wp_get_current_user()->add_cap('manage_woocommerce');
        wp_get_current_user()->add_cap('edit_shop_orders');

        update_option('sendy_processing_method', ProcessingMethod::WooCommerce);
        delete_option('sendy_flash_admin_messages');
    }

    public function tearDown(): void
    {
        $this->set_api_connection(null);

        parent::tearDown();
    }

    /**
     * Fire the AJAX endpoint and return the decoded JSON response.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    protected function dispatch_ajax(string $action, array $post): array
    {
        $_POST = array_merge(['action' => $action], $post);

        try {
            $this->_handleAjax($action);
            $this->fail('The AJAX handler was expected to send a JSON response and die');
        } catch (WPAjaxDieContinueException $exception) {
            // wp_send_json() ends in an empty wp_die(), which the AJAX test
            // case converts into this exception after buffering the output.
        }

        return json_decode($this->_last_response, true);
    }
}
