<?php

namespace Sendy\WooCommerce\Modules\Admin\Fields;

use Sendy\WooCommerce\Utils\View;

final class Checkbox extends Field
{
    public function initializeView(): void
    {
        $this->view = View::fromTemplate('admin/settings/fields/checkbox.php');
    }
}
