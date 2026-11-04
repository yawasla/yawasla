<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Main\Controller;

use Yawasla\Controller\AbstractController;

final class ErrorController extends AbstractController
{
    public function notFound(): string
    {
        http_response_code(404);

        return $this->render('main/errors/404.twig');
    }

    /** @param list<string> $methods */
    public function methodNotAllowed(array $methods): string
    {
        http_response_code(405);
        header('Allow: ' . implode(', ', $methods));

        return $this->render('main/errors/405.twig');
    }

    public function serverError(): string
    {
        http_response_code(500);

        return $this->render('main/errors/500.twig');
    }
}
