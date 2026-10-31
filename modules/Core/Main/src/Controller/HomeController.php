<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Main\Controller;

use Yawasla\Controller\Controller;
use Yawasla\Http\Route;

final class HomeController extends Controller
{
    #[Route('/', name: 'home')]
    public function index(): string
    {
        return $this->render(['custom.twig', 'main.twig']);
    }
}
