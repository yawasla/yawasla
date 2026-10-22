<?php

declare(strict_types=1);

namespace Yawasla\Controller\Fil;

use Yawasla\Http\Route;

final class HomeController extends FilController
{
    #[Route('/fil', name: 'fil')]
    public function index(): string
    {
        return $this->render('fil/home.twig');
    }
}
