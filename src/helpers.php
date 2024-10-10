<?php

if (! function_exists('sendy_initialize_plugin_url')) {
    function sendy_initialize_plugin_url(): string
    {
        return 'https://app.sendy.nl/plugin/initialize?' . http_build_query([
            'client_id' => get_option('sendy_client_id'),
            'client_secret' => get_option('sendy_client_secret'),
            'redirect_uri' => sendy_oauth_redirect_url(),
            'name' => get_bloginfo('name'),
            'type' => 'woocommerce',
            'state' => wp_create_nonce('sendy_oauth_callback_nonce'),
        ]);
    }
}

if (! function_exists('sendy_oauth_redirect_url')) {
    function sendy_oauth_redirect_url(): string {
        return admin_url('?sendy_oauth_callback');
    }
}

if (! function_exists('sendy_fields_generator')) {
    function sendy_fields_generator(array $fields)
    {
        foreach ($fields as $field) {
            switch ($field['type']) {
                case 'select':
                    woocommerce_wp_select($field);
                    break;
            }
        }
    }
}

if (! function_exists('sendy_is_authenticated')) {
    function sendy_is_authenticated(): bool {
        return get_option('sendy_access_token') != '';
    }
}

if (! function_exists('sendy_flash_admin_notice')) {
    function sendy_flash_admin_notice(string $type, string $message): void
    {
        $messages = get_option('sendy_flash_admin_messages', []);

        $messages[] = [
            'type' => $type,
            'message' => $message,
        ];

        update_option('sendy_flash_admin_messages', $messages);
    }
}

if (! function_exists('sendy_parse_address')) {

    /**
     * Extract the street, number and addition from a given string
     *
     * @param string $address
     * @return object{street: string, house_number:string|null, house_number_addition:string|null}
     */
    function sendy_parse_address(string $address): stdClass {
        $address = trim($address);
        $parts = explode(' ', $address);
        $partsCount = count($parts);

        $numberPart = null;
        $number = null;
        $addition = null;

        // Check the parts if they might contain any house number
        if ($partsCount == 2) {
            if (is_numeric(end($parts))) { // House number should be 1
                $numberPart = end($parts);
                $streetPart = $parts[0];
            } elseif (preg_match('/^([0-9]+)([a-zA-Z]+)$/', end($parts))) { // House number should be 1a
                $numberPart = end($parts);
                $streetPart = rtrim(substr($address, 0, (0 - strlen($numberPart))));
            } elseif (preg_match('/^([0-9]+)([\-\\\+\/]+?)([0-9a-zA-Z]+)$/', end($parts))) { // House number should be 1-1someting
                $numberPart = end($parts);
                $streetPart = rtrim(substr($address, 0, (0 - strlen($numberPart))));
            } else {
                $streetPart = $address;
            }
        } elseif ($partsCount >= 3) {
            if (is_numeric(end($parts))) { // House number 1
                $numberPart = end($parts);
                $streetPart = rtrim(substr($address, 0, (0 - strlen($numberPart))));
            } elseif (preg_match('/^([0-9]+)([\-\\\+\/]?)([0-9a-zA-Z]+)$/', end($parts))) { // House number 1a
                $numberPart = end($parts);
                $streetPart = rtrim(substr($address, 0, (0 - strlen($numberPart))));
            } elseif (preg_match('/^([0-9]+)([a-zA-Z\s\-]+)$/', $parts[$partsCount - 2] . ' ' . $parts[$partsCount - 1])) { // House number 1 a
                $numberPart = $parts[$partsCount - 2] . ' ' . $parts[$partsCount - 1];
                $streetPart = rtrim(substr($address, 0, (0 - strlen($numberPart))));
            } else {
                $streetPart = $address;
            }
        } else {
            $streetPart = $address;
        }

        // Convert the number to an object
        if ($numberPart) {
            $houseNumberDetails = sendy_parse_house_number($numberPart);
            $number = $houseNumberDetails->number;
            $addition = $houseNumberDetails->addition;
        }

        $object = new \stdClass();
        $object->street = trim($streetPart);
        $object->number = $number;
        $object->addition = $addition;

        return $object;
    }
}

if (! function_exists('sendy_parse_house_number')) {
    /**
     * Split the house number and addition
     *
     * @param string $number
     * @return object{house_number:string, house_number_addition:string|null}
     */
    function sendy_parse_house_number(string $number): stdClass {
        $addition = null;

        if (! ctype_digit($number)) {
            $addition = ltrim($number, '0123456789');
            $number = substr($number, 0, (0 - strlen($addition)));
        }

        $object = new \stdClass();
        $object->number = trim($number);
        $object->addition = $addition ? trim($addition) : null;

        return $object;
    }
}
