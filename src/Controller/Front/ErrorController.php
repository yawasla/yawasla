<?php

declare(strict_types=1);

namespace Yawasla\Controller\Front;

/**
 * Pages d'erreur, appelées par le bootstrap (aucune route).
 */
final class ErrorController extends FrontController
{
    public function notFound(): string
    {
        http_response_code(404);

        return $this->render('errors/404.twig');
    }

    public function serverError(): string
    {
        http_response_code(500);

        return $this->render('errors/500.twig');
    }
}
