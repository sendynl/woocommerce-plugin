<?php

/**
 * Test doubles for the parts of WooCommerce and the Sendy SDK the tests need.
 *
 * WooCommerce itself is not loaded in the WordPress test environment, so a
 * minimal wc_get_order() backed by a registry of fake orders stands in for it.
 */

use Sendy\Api\Http\Request;
use Sendy\Api\Http\Response;
use Sendy\Api\Http\Transport\TransportInterface;

if (! function_exists('wc_get_order')) {
    function wc_get_order($order_id)
    {
        return Sendy_Fake_Order::find((int) $order_id);
    }
}

class Sendy_Fake_Order
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

class Sendy_Fake_Transport implements TransportInterface
{
    private Response $response;

    public ?Request $lastRequest = null;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public function send(Request $request): Response
    {
        $this->lastRequest = $request;

        return $this->response;
    }

    public function getUserAgent(): string
    {
        return 'PHPUnit';
    }
}
