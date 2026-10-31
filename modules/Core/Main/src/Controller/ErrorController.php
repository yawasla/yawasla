<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Main\Controller;

use Yawasla\Controller\Controller;

final class ErrorController extends Controller
{
    public function notFound(): string
    {
        http_response_code(404);

        return $this->render('main/errors/404.twig');
    }

    public function serverError(): string
    {
        http_response_code(500);

        return $this->render('main/errors/500.twig');
    }
}
