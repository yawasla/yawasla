<?php

declare(strict_types=1);

namespace Yawasla\Controller;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Yawasla\Core\Config;
use Yawasla\Http\Router;

abstract class Controller
{
    private ?Environment $twig = null;

    public function __construct(protected Config $config, protected Router $router)
    {
    }

    /** @param array<string, mixed> $data */
    protected function render(string $template, array $data = []): string
    {
        return $this->twig()->render($template, $data);
    }

    protected function redirect(string $url, int $status = 302): string
    {
        header('Location: ' . $url, true, $status);

        return '';
    }

    private function twig(): Environment
    {
        if ($this->twig === null) {
            $root = dirname(__DIR__, 2);

            // Templates compilés hors racine web ; auto_reload recompile un template modifié (mise à jour, personnalisation)
            $this->twig = new Environment(new FilesystemLoader($root . '/templates'), [
                'cache' => $root . '/data/cache/twig',
                'auto_reload' => true,
            ]);
            $this->twig->addGlobal('dev', $this->config->isDev());
            $this->twig->addGlobal('route', $this->router->current());
            $this->twig->addFunction(new TwigFunction('path', [$this->router, 'path']));
        }

        return $this->twig;
    }
}
