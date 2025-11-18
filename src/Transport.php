<?php

namespace Sendy\WooCommerce;

use Sendy\Api\Exceptions\TransportException;
use Sendy\Api\Http\Request;
use Sendy\Api\Http\Response;
use Sendy\Api\Http\Transport\TransportInterface;

final class Transport implements TransportInterface
{

    public function send(Request $request): Response
    {
        $args = [
            'method' => $request->getMethod(),
            'headers' => $request->getHeaders(),
            'body' => $request->getBody(),
        ];

        if ($request->getMethod() === 'GET') {
            unset($args['body']);
        }

        $response = wp_remote_request($request->getUrl(), $args);

        if (is_wp_error($response)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new TransportException($response->get_error_message());
        }

        return new Response(
            wp_remote_retrieve_response_code($response),
            wp_remote_retrieve_headers($response)->getAll(),
            wp_remote_retrieve_body($response)
        );
    }

    public function getUserAgent(): string
    {
        return 'WP_Http/' . get_bloginfo('version');
    }
}
