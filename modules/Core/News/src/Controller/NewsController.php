<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\News\Controller;

use Yawasla\Controller\Controller;
use Yawasla\Http\Route;

final class NewsController extends Controller
{
    #[Route('/news/{slug}', name: 'news')]
    public function show(string $slug): string
    {
        return $this->render('news/show.twig');
    }
}
