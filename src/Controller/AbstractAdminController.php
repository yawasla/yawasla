<?php

declare(strict_types=1);

namespace Yawasla\Controller;

use Composer\InstalledVersions;
use Yawasla\Auth\Auth;
use Yawasla\Core\Maintenance;
use Yawasla\Core\Modules;
use Yawasla\Http\Request;
use Yawasla\Http\Router;
use Yawasla\Model\Setting;

abstract class AbstractAdminController extends AbstractController
{
    public const STRICT_ROUTES = true;

    public function __construct()
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }

    /**
     * @param string|list<string> $template
     * @param array<string, mixed> $data
     */
    protected function render(string|array $template, array $data = []): string
    {
        return parent::render($template, $data + ['admin' => [
            'menu' => $this->menu(),
            'site' => (new Setting())->get('site_name'),
            'user' => Auth::getInstance()->user(),
            'version' => InstalledVersions::getRootPackage()['pretty_version'],
            'maintenance' => Maintenance::enabled(),
        ]]);
    }

    protected function back(): string
    {
        $router = Router::getInstance();
        $back = Request::getInstance()->input('back') ?? '';
        $admin = $router->basePath() . '/admin';

        if ($back !== $admin && !str_starts_with($back, $admin . '/')) {
            return $router->path('admin');
        }

        return $back;
    }

    /** @return list<array{route: string, label: string, icon: string, active: bool}> */
    private function menu(): array
    {
        $router = Router::getInstance();
        $auth = Auth::getInstance();
        $current = $router->current();
        $menu = [];
        $active = null;

        foreach (Modules::getInstance()->enabled() as $module) {
            foreach ($module['admin'] as $entry) {
                $level = $router->routeAuth($entry['route']);

                if ($level === null || !$auth->allows($level)) {
                    continue;
                }

                $matches = $current === $entry['route'] || str_starts_with($current, $entry['route'] . '_');

                if ($matches && ($active === null || strlen($entry['route']) > strlen($menu[$active]['route']))) {
                    $active = count($menu);
                }

                $menu[] = ['route' => $entry['route'], 'label' => $entry['label'], 'icon' => $entry['icon'], 'active' => false];
            }
        }

        if ($active !== null) {
            $menu[$active]['active'] = true;
        }

        return $menu;
    }
}
