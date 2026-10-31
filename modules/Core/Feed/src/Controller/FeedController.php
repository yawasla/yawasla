<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Feed\Controller;

use Yawasla\Controller\Controller;
use Yawasla\Http\Route;

final class FeedController extends Controller
{
    #[Route('/feed', name: 'feed')]
    public function index(): string
    {
        return $this->render('feed/index.twig');
    }
}
