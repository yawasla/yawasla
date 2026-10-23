<?php

declare(strict_types=1);

namespace Yawasla\Controller\Admin;

use Yawasla\Controller\Controller;
use Yawasla\Core\Config;
use Yawasla\Http\Router;

/**
 * Base du back-office : toute la protection de la zone se fait ici, jamais méthode par méthode.
 */
abstract class AdminController extends Controller
{
    public function __construct(Config $config, Router $router)
    {
        parent::__construct($config, $router);

        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }

    protected function locale(): string
    {
        return $this->router->locale();
    }
}
