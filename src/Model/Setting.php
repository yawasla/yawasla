<?php

declare(strict_types=1);

namespace Yawasla\Model;

use Yawasla\Database\Connection;

final class Setting
{
    public const FIELDS = [
        'site_url' => ['required' => true, 'max' => 255, 'type' => 'url'],
        'site_name' => ['required' => true, 'max' => 255],
        'site_description' => ['required' => true, 'max' => 500],
    ];

    public function get(string $name): string
    {
        $value = Connection::getInstance()->medoo()->get('config', 'value', ['name' => $name]);

        return is_string($value) ? $value : '';
    }

    public function set(string $name, string $value): void
    {
        $db = Connection::getInstance()->medoo();

        if ($db->has('config', ['name' => $name])) {
            $db->update('config', ['value' => $value], ['name' => $name]);

            return;
        }

        $db->insert('config', ['name' => $name, 'value' => $value, 'created_at' => date('Y-m-d H:i:s')]);
    }
}
