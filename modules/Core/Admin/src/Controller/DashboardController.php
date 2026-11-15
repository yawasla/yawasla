<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Admin\Controller;

use Yawasla\Auth\Auth;
use Yawasla\Controller\AbstractAdminController;
use Yawasla\Http\Route;

#[Route('/admin', auth: 'admin')]
final class DashboardController extends AbstractAdminController
{
    #[Route('', name: 'admin')]
    public function index(): string
    {
        return $this->render('admin/index.twig', ['name' => (string) Auth::getInstance()->user()['first_name']]);
    }
}
