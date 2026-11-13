<?php

declare(strict_types=1);

namespace Yawasla\Controller;

abstract class AbstractAdminController extends AbstractController
{
    public const STRICT_ROUTES = true;

    public function __construct()
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }
}
