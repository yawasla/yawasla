<?php

declare(strict_types=1);

namespace Yawasla\Controller;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Yawasla\Core\Config;
use Yawasla\Http\Router;
use Yawasla\I18n\Translator;

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

    protected function locale(): string
    {
        return $this->config->get('APP_LANG', 'en');
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
            $translator = new Translator($this->locale(), $root . '/translations');

            $this->twig->addGlobal('dev', filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOL));
            $this->twig->addGlobal('route', $this->router->current());
            $this->twig->addGlobal('locale', $translator->locale());
            $this->twig->addGlobal('dir', $translator->dir());
            $this->twig->addFunction(new TwigFunction('path', [$this->router, 'path']));
            $this->twig->addFilter(new TwigFilter('trans', [$translator, 'trans']));
        }

        return $this->twig;
    }
}
