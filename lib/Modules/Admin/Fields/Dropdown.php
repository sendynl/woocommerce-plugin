<?php

namespace Sendy\WooCommerce\Modules\Admin\Fields;

use Sendy\WooCommerce\Utils\View;

final class Dropdown extends Field
{
    public function initializeView(): void
    {
        $this->view = View::fromTemplate('admin/settings/fields/dropdown.php');
    }
}
