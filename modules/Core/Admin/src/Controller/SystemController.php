<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Admin\Controller;

use Yawasla\Controller\AbstractAdminController;
use Yawasla\Core\Cache;
use Yawasla\Core\Maintenance;
use Yawasla\Core\Trash;
use Yawasla\Form\Form;
use Yawasla\Http\Route;
use Yawasla\Http\Session;
use Yawasla\I18n\Translator;

#[Route('/admin', auth: 'admin')]
final class SystemController extends AbstractAdminController
{
    #[Route('/cache', name: 'admin_cache', methods: ['POST'])]
    public function cache(): string
    {
        if (Form::validToken($_POST)) {
            Cache::clear();
            Trash::clear();
            Session::getInstance()->flash('success', Translator::getInstance()->trans('Cache cleared.'));
        }

        return $this->redirect($this->back(), 303);
    }

    #[Route('/maintenance', name: 'admin_maintenance', methods: ['POST'])]
    public function maintenance(): string
    {
        if (Form::validToken($_POST)) {
            $translator = Translator::getInstance();

            if (Maintenance::enabled()) {
                Maintenance::disable();
                Session::getInstance()->flash('success', $translator->trans('Maintenance off: the site is open to visitors.'));
            } else {
                Maintenance::enable();
                Session::getInstance()->flash('warning', $translator->trans('Maintenance on: visitors see the maintenance page.'));
            }
        }

        return $this->redirect($this->back(), 303);
    }
}
