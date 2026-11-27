<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Install\Model;

use Medoo\Medoo;
use Yawasla\Database\Connection;
use Yawasla\Core\ModuleException;
use Yawasla\Core\ModuleManager;
use Yawasla\Core\Modules;

final class Installer
{
    private const EXTENSIONS = ['Asma'];

    /**
     * @param array<string, string> $site
     * @param array<string, string> $organization
     * @param array<string, string> $admin
     */
    public function install(array $site, array $organization, array $admin): void
    {
        $manager = new ModuleManager();
        $manager->finishUpdate();

        foreach (self::EXTENSIONS as $name) {
            if (Modules::getInstance()->find($name) === null) {
                continue;
            }

            try {
                $manager->enable($name);
            } catch (ModuleException $e) {
                trigger_error('Extension ' . $name . ' not enabled at install: ' . $e->getMessage(), E_USER_WARNING);
            }
        }

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
                'password' => $admin['password_hash'],
                'created_at' => $now,
            ]);
        });
    }
}
