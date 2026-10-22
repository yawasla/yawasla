<?php

declare(strict_types=1);

namespace Yawasla\Controller\Front;

use Yawasla\Http\Route;

final class AnnouncementController extends FrontController
{
    #[Route('/annonce/{slug}', name: 'announcement')]
    public function show(string $slug): string
    {
        return $this->render('front/announcement.twig');
    }
}
