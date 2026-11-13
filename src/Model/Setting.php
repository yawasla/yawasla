<?php

declare(strict_types=1);

namespace Yawasla\Model;

use Yawasla\Database\Connection;

final class Setting
{
    public function get(string $name): string
    {
        $value = Connection::getInstance()->medoo()->get('config', 'value', ['name' => $name]);

        return is_string($value) ? $value : '';
    }
}
