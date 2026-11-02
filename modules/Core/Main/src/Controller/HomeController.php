<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Main\Controller;

use Yawasla\Controller\AbstractController;
use Yawasla\Http\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): string
    {
        return $this->render(['custom.twig', 'main.twig']);
    }
}
