<?php

namespace Sendy\WooCommerce\Modules\Admin\Fields;

use Sendy\WooCommerce\Utils\View;

abstract class Field
{
    protected string $optionName;
    protected ?string $extraDescription;

    protected View $view;

    public function __construct(
        string $optionName,
        string $extraDescription = null
    )
    {
        $this->optionName = $optionName;
        $this->extraDescription = $extraDescription;
    }

    /**
     * Initialize the view which is used to render the field
     *
     * This method is called from the render method when rendering the view. Therefor it's only necessary to implement
     * this method on the child classes without calling it.
     *
     * @return void
     */
    abstract function initializeView(): void;

    final public function render(array $parameters = []): void
    {
        $this->initializeView();

        echo wp_kses(
            $this->view->render(array_merge($parameters, [
                'option_name' => $this->optionName,
                'extra_description' => $this->extraDescription,
            ])),
            View::ALLOWED_TAGS
        );
    }
}
