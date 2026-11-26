<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Admin\Controller;

use Yawasla\Controller\AbstractAdminController;
use Yawasla\Form\Form;
use Yawasla\Http\Request;
use Yawasla\Http\Route;
use Yawasla\Http\Router;
use Yawasla\Http\Session;
use Yawasla\I18n\Translator;
use Yawasla\Model\Setting;

#[Route('/admin/site', auth: 'admin')]
final class SiteController extends AbstractAdminController
{
    #[Route('', name: 'admin_site', methods: ['GET', 'POST'])]
    public function index(): string
    {
        $setting = new Setting();
        $saved = [];

        foreach (array_keys(Setting::FIELDS) as $name) {
            $saved[$name] = $setting->get($name);
        }

        $form = new Form(Setting::FIELDS, $saved);

        if (Request::getInstance()->isPost() && $this->validate($form, $saved['site_url'])) {
            foreach (array_keys(Setting::FIELDS) as $name) {
                $setting->set($name, $form->value($name));
            }

            Session::getInstance()->flash('success', Translator::getInstance()->trans('Site saved.'));

            return $this->redirect(Router::getInstance()->path('admin_site'), 303);
        }

        return $this->render('admin/site.twig', ['form' => $form]);
    }

    private function validate(Form $form, string $savedUrl): bool
    {
        $valid = $form->validate(Request::getInstance()->post());
        $url = $form->value('site_url');

        if ($form->error('site_url') === '' && $url !== $savedUrl && $url !== Router::getInstance()->baseUrl()) {
            $form->addError('site_url', Translator::getInstance()->trans('Open the admin from this address to save it.'));

            return false;
        }

        return $valid;
    }
}
