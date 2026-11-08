<?php

declare(strict_types=1);

namespace Yawasla\Controller;

use Twig\Environment;
use Twig\Extension\ProfilerExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\Template;
use Twig\TwigFunction;
use Yawasla\Core\Modules;
use Yawasla\Debug\Debug;
use Yawasla\Form\Form;
use Yawasla\Http\Router;
use Yawasla\Http\Vite;
use Yawasla\I18n\Translator;

abstract class AbstractController
{
    private ?Environment $twig = null;

    /**
     * @param string|list<string> $template
     * @param array<string, mixed> $data
     */
    protected function render(string|array $template, array $data = []): string
    {
        $html = $this->twig()->resolveTemplate($template)->render($data);
        $debug = Debug::getInstance();

        if (!$debug->enabled()) {
            return $html;
        }

        header('Cache-Control: no-store');
        $toolbar = $this->twig()->render('main/debug/toolbar.twig', $debug->collect());
        $end = strripos($html, '</body>');

        return $end === false ? $html . $toolbar : substr_replace($html, $toolbar, $end, 0);
    }

    protected function redirect(string $path, int $status = 302): string
    {
        if (!preg_match('#^/(?![/\\\\])[^\x00-\x20\x7f\\\\]*$#', $path)) {
            $path = Router::getInstance()->basePath() . '/';
        }

        header('Location: ' . $path, true, $status);

        return '';
    }

    protected function redirectExternal(string $url, int $status = 302): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return $this->redirect('/', $status);
        }

        header('Location: ' . $url, true, $status);

        return '';
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, mixed> $vars
     */
    private function dump(array $context, array $vars = []): string
    {
        $debug = Debug::getInstance();

        if (!$debug->enabled()) {
            return '';
        }

        $file = '';
        $line = 0;
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $i => $frame) {
            if (($frame['object'] ?? null) instanceof Template) {
                $file = 'templates/' . $frame['object']->getTemplateName();
                foreach ($frame['object']->getDebugInfo() as $php => $twig) {
                    if ($php <= ($trace[$i - 1]['line'] ?? 0)) {
                        $line = $twig;
                        break;
                    }
                }
                break;
            }
        }

        foreach ($vars ?: [$context] as $var) {
            $debug->dump($var, $file, $line);
        }

        return '';
    }

    private function module(string $name): string
    {
        $module = Modules::getInstance()->get($name);

        if ($module === null) {
            return '';
        }

        $block = $module['namespace'] . 'Block';
        $data = class_exists($block) ? (new $block())() : [];

        return $this->twig()->render($module['id'] . '/block.twig', $data);
    }

    private function twig(): Environment
    {
        if ($this->twig === null) {
            $root = dirname(__DIR__, 2);

            $modules = Modules::getInstance();
            $paths = array_filter(array_map(fn (array $module): string => $module['dir'] . '/templates', $modules->enabled()), 'is_dir');

            $this->twig = new Environment(new FilesystemLoader([...array_values($paths), $root . '/templates']), [
                'cache' => $root . '/data/cache/twig',
                'auto_reload' => true,
                'strict_variables' => Debug::getInstance()->enabled(),
            ]);
            $translator = Translator::getInstance();
            $router = Router::getInstance();
            $public = dirname($_SERVER['SCRIPT_FILENAME']);

            $this->twig->addGlobal('route', $router->current());
            $this->twig->addGlobal('canonical', $router->canonical());
            $this->twig->addGlobal('locale', $translator->locale());
            $this->twig->addGlobal('dir', $translator->dir());
            $this->twig->addFunction(new TwigFunction('path', [$router, 'path']));
            $this->twig->addFunction(new TwigFunction('asset', fn (string $file): string => $router->basePath() . '/assets/' . $file . '?v=' . filemtime($public . '/assets/' . $file)));
            $this->twig->addFunction(new TwigFunction('vite', [new Vite($root, $public . '/assets', $router->basePath() . '/assets'), 'tags'], ['is_safe' => ['html']]));
            $this->twig->addFunction(new TwigFunction('module', $this->module(...), ['is_safe' => ['html']]));
            $this->twig->addFunction(new TwigFunction('csrf', [Form::class, 'field'], ['is_safe' => ['html']]));
            $this->twig->addFilter(new TwigFilter('trans', [$translator, 'trans']));
            $this->twig->addFunction(new TwigFunction('dump', $this->dump(...), ['needs_context' => true, 'is_variadic' => true]));

            if (Debug::getInstance()->enabled()) {
                $this->twig->addExtension(new ProfilerExtension(Debug::getInstance()->profile()));
            }
        }

        return $this->twig;
    }
}
