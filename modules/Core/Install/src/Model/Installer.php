<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Install\Model;

use Medoo\Medoo;
use Yawasla\Database\Connection;
use Yawasla\Database\Migrator;

final class Installer
{
    /**
     * @param array<string, string> $site
     * @param array<string, string> $organization
     * @param array<string, string> $admin
     */
    public function install(array $site, array $organization, array $admin): void
    {
        (new Migrator())->run();

        $now = date('Y-m-d H:i:s');

        Connection::getInstance()->medoo()->action(function (Medoo $db) use ($site, $organization, $admin, $now): void {
            foreach (['site_url', 'site_name', 'site_description'] as $name) {
                $db->insert('config', ['name' => $name, 'value' => $site[$name], 'created_at' => $now]);
            }

            $db->insert('organization', [
                'name' => $organization['name'],
                'address' => $organization['address'] ?: null,
                'zip_code' => $organization['zip_code'] ?: null,
                'city' => $organization['city'] ?: null,
                'country' => $organization['country'] ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $organizationId = (int) $db->id();

            $db->insert('user', [
                'organization_id' => $organizationId,
                'email' => $admin['email'],
                'first_name' => $admin['first_name'],
                'last_name' => $admin['last_name'],
                'roles' => json_encode(['admin'], JSON_THROW_ON_ERROR),
                'password_hash' => $admin['password_hash'],
                'created_at' => $now,
            ]);
        });
    }
}
