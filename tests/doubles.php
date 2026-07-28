<?php

/**
 * Test doubles for the parts of WooCommerce and the Sendy SDK the tests need.
 *
 * WooCommerce itself is not loaded in the WordPress test environment, so a
 * minimal wc_get_order() backed by a registry of fake orders stands in for it.
 */

use Sendy\Api\Connection;
use Sendy\Api\Http\Request;
use Sendy\Api\Http\Response;
use Sendy\Api\Http\Transport\TransportInterface;
use Sendy\WooCommerce\ApiClientFactory;

if (! function_exists('wc_get_order')) {
    function wc_get_order($order_id)
    {
        return Sendy_Fake_Order::find((int) $order_id);
    }
}

/**
 * Minimal WC_Order stub for type hinting in the test environment.
 * WooCommerce is not loaded in the test environment, so we provide
 * a base class that the fake order can extend to satisfy type hints.
 */
class WC_Order
{
}

class Sendy_Fake_Order extends WC_Order
{
    /** @var array<int,self> */
    private static array $orders = [];

    private int $id;

    /** @var array<string,mixed> */
    private array $meta;

    private ?string $status = null;

    private ?string $status_note = null;

    private bool $saved = false;

    public function __construct(int $id, array $meta = [])
    {
        $this->id = $id;
        $this->meta = $meta;

        self::$orders[$id] = $this;
    }

    /** @return self|false */
    public static function find(int $id)
    {
        return self::$orders[$id] ?? false;
    }

    public static function reset(): void
    {
        self::$orders = [];
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function meta_exists(string $key): bool
    {
        return array_key_exists($key, $this->meta);
    }

    public function get_meta(string $key)
    {
        return $this->meta[$key] ?? '';
    }

    public function update_meta_data(string $key, $value): void
    {
        $this->meta[$key] = $value;
    }

    public function get_order_number(): string
    {
        return (string) $this->id;
    }

    public function get_date_created(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01T10:00:00+00:00');
    }

    public function get_shipping_first_name(): string
    {
        return 'John';
    }

    public function get_shipping_last_name(): string
    {
        return 'Doe';
    }

    public function get_shipping_company(): string
    {
        return '';
    }

    public function get_shipping_country(): string
    {
        return 'NL';
    }

    public function get_shipping_address_1(): string
    {
        return 'Teststraat 12';
    }

    public function get_shipping_address_2(): string
    {
        return '';
    }

    public function get_shipping_postcode(): string
    {
        return '1234 AB';
    }

    public function get_shipping_city(): string
    {
        return 'Amsterdam';
    }

    public function get_shipping_phone(): string
    {
        return '';
    }

    public function get_billing_email(): string
    {
        return 'john@example.com';
    }

    public function get_items(): array
    {
        return [];
    }

    public function get_shipping_methods(): array
    {
        return [new Sendy_Fake_Shipping_Method()];
    }

    public function set_status(string $status, string $note = ''): void
    {
        $this->status = $status;
        $this->status_note = $note;
    }

    public function save(): void
    {
        $this->saved = true;
    }

    public function get_status(): ?string
    {
        return $this->status;
    }

    public function get_status_note(): ?string
    {
        return $this->status_note;
    }

    public function was_saved(): bool
    {
        return $this->saved;
    }
}

class Sendy_Fake_Shipping_Method
{
    public function get_instance_id(): int
    {
        return 1;
    }
}

class Sendy_Fake_Transport implements TransportInterface
{
    /** @var Response[] */
    private array $responses;

    public ?Request $lastRequest = null;

    /** @var Request[] */
    public array $requests = [];

    public function __construct(Response ...$responses)
    {
        $this->responses = $responses;
    }

    public function send(Request $request): Response
    {
        $this->lastRequest = $request;
        $this->requests[] = $request;

        if (count($this->responses) > 1) {
            return array_shift($this->responses);
        }

        return $this->responses[0];
    }

    public function getUserAgent(): string
    {
        return 'PHPUnit';
    }
}

/**
 * Points ApiClientFactory's memoized connection at canned HTTP responses.
 *
 * Call set_api_connection(null) in tearDown() so the connection cannot leak
 * into other tests.
 */
trait Sendy_Fakes_Api_Connection
{
    private function fake_transport(Response ...$responses): Sendy_Fake_Transport
    {
        $transport = new Sendy_Fake_Transport(...$responses);

        update_option('sendy_access_token', 'access-token', false);
        update_option('sendy_refresh_token', 'refresh-token', false);
        update_option('sendy_token_expires', time() + 3600, false);

        $this->set_api_connection(
            (new Connection())
                ->setTransport($transport)
                ->setAccessToken('access-token')
                ->setRefreshToken('refresh-token')
                ->setTokenExpires(time() + 3600)
        );

        return $transport;
    }

    private function set_api_connection(?Connection $connection): void
    {
        $property = new ReflectionProperty(ApiClientFactory::class, 'connection');
        $property->setAccessible(true);
        $property->setValue(null, $connection);
    }
}
