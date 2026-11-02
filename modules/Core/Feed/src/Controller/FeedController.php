<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Feed\Controller;

use Yawasla\Controller\AbstractController;
use Yawasla\Http\Route;

final class FeedController extends AbstractController
{
    #[Route('/feed', name: 'feed')]
    public function index(): string
    {
        return $this->render('feed/index.twig');
    }
}
