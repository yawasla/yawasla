<?php

declare(strict_types=1);

namespace Yawasla\Controller\Admin;

use Yawasla\Controller\Controller;

abstract class AdminController extends Controller
{
    public function __construct()
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }
}
