<?php

namespace Sendy\WooCommerce\Utils;

use InvalidArgumentException;

class View
{
    private string $filename;

    private const VIEW_PATH = '/resources/views/';

    public const ALLOWED_TAGS = [
        'a'          => [
            'class'  => [],
            'href'   => [],
            'rel'    => [],
            'title'  => [],
            'target' => []
        ],
        'abbr'       => [
            'title' => [],
        ],
        'b'          => [],
        'blockquote' => [
            'cite' => [],
        ],
        'br'         => [],
        'button'     => [
            'class'    => [],
            'id'       => [],
            'disabled' => [],
        ],
        'cite'       => [
            'title' => [],
        ],
        'code'       => [],
        'del'        => [
            'datetime' => [],
            'title'    => [],
        ],
        'dd'         => [],
        'div'        => [
            'class' => [],
            'id'    => [],
            'title' => [],
            'style' => [],
        ],
        'dl'         => [],
        'dt'         => [],
        'em'         => [],
        'h1'         => [],
        'h2'         => [],
        'h3'         => [],
        'h4'         => [],
        'h5'         => [],
        'h6'         => [],
        'hr'         => [
            'class' => []
        ],
        'i'          => [
            'class' => []
        ],
        'img'        => [
            'alt'    => [],
            'class'  => [],
            'height' => [],
            'src'    => [],
            'width'  => [],
        ],
        'input'      => [
            'id'    => [],
            'class'  => [],
            'name' => [],
            'value'    => [],
            'type'  => [],
        ],
        'li'         => [
            'class' => [],
        ],
        'ol'         => [
            'class' => [],
        ],
        'p'          => [
            'class' => [],
        ],
        'path'       => [
            'fill'            => [],
            'd'               => [],
            'class'           => [],
            'data-v-19c3f3ae' => []
        ],
        'q'          => [
            'cite'  => [],
            'title' => [],
        ],
        'script'     => [
            'type' => [],
            'id'   => [],
        ],
        'span'       => [
            'class'       => [],
            'title'       => [],
            'style'       => [],
            'data-tip'    => [],
            'data-target' => [],
        ],
        'strike'     => [],
        'strong'     => [],
        'svg'        => [
            'aria-hidden'     => [],
            'focusable'       => [],
            'data-prefix'     => [],
            'data-icon'       => [],
            'role'            => [],
            'xmlns'           => [],
            'viewbox'         => [],
            'class'           => [],
            'data-v-19c3f3ae' => [],
        ],
        'table'      => [
            'class' => []
        ],
        'tbody'      => [
            'class' => []
        ],
        'thead'      => [
            'class' => []
        ],
        'tr'         => [
            'class'     => [],
            'data-name' => [],
        ],
        'td'         => [
            'class'   => [],
            'colspan' => [],
        ],
        'ul'         => [
            'id'    => [],
            'class' => [],
        ],
    ];

    public function __construct(string $filename)
    {
        $this->filename = $filename;
    }

    public static function fromTemplate(string $template): self
    {
        $file = SENDY_WC_PLUGIN_DIR_PATH . self::VIEW_PATH . $template;

        if (file_exists($file)) {
            return new self($file);
        }

        throw new InvalidArgumentException('Cannot find template: ' . $template);
    }

    public function render($data = []): string
    {
        ob_start();

        extract($data);

        require $this->filename;

        return ob_get_clean();
    }
}