<?php

declare(strict_types=1);

namespace Yawasla\Controller\Front;

use Yawasla\Http\Route;

final class HomeController extends FrontController
{
    #[Route('/', name: 'home')]
    public function index(): string
    {
        return $this->render('front/home.twig');
    }
}
