<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Admin\Controller;

use Yawasla\Controller\AbstractAdminController;
use Yawasla\Form\Form;
use Yawasla\Http\NotFoundException;
use Yawasla\Http\Request;
use Yawasla\Http\Route;
use Yawasla\Http\Router;
use Yawasla\Http\Session;
use Yawasla\I18n\Translator;
use Yawasla\Model\Organization;

#[Route('/admin/organizations', auth: 'admin')]
final class OrganizationController extends AbstractAdminController
{
    #[Route('', name: 'admin_organizations')]
    public function index(): string
    {
        $model = new Organization();
        $main = $model->main();

        return $this->render('admin/organizations.twig', [
            'main' => $main,
            'secondaries' => $main === null ? [] : $model->secondaries($main['id']),
        ]);
    }

    #[Route('/new', name: 'admin_organizations_new', methods: ['GET', 'POST'])]
    public function create(): string
    {
        $model = new Organization();
        $main = $model->main() ?? throw new NotFoundException();
        $form = new Form(Organization::FIELDS);

        if (Request::getInstance()->isPost() && $this->validate($form, $model, null)) {
            $model->create($this->values($form), $main['id']);

            return $this->done('Organization added.');
        }

        return $this->render('admin/organization.twig', ['form' => $form, 'organization' => null]);
    }

    #[Route('/{id}', name: 'admin_organizations_edit', methods: ['GET', 'POST'])]
    public function edit(int $id): string
    {
        $model = new Organization();
        $organization = $model->find($id) ?? throw new NotFoundException();
        $form = new Form(Organization::FIELDS, $organization);

        if (Request::getInstance()->isPost() && $this->validate($form, $model, $id)) {
            $model->update($id, $this->values($form));

            return $this->done('Organization saved.');
        }

        return $this->render('admin/organization.twig', ['form' => $form, 'organization' => $organization]);
    }

    #[Route('/{id}/delete', name: 'admin_organizations_delete', methods: ['POST'])]
    public function delete(int $id): string
    {
        $model = new Organization();
        $organization = $model->find($id) ?? throw new NotFoundException();

        if (Form::validToken(Request::getInstance()->post())) {
            $session = Session::getInstance();
            $translator = Translator::getInstance();
            $params = ['%name%' => $organization['name']];

            if ($organization['parent_id'] === null) {
                $session->flash('danger', $translator->trans('The main organization cannot be deleted.'));
            } elseif ($model->used($id)) {
                $session->flash('danger', $translator->trans('%name% cannot be deleted: news, janaza or users are linked to it.', $params));
            } else {
                $model->delete($id);
                $session->flash('success', $translator->trans('%name% deleted.', $params));
            }
        }

        return $this->redirect(Router::getInstance()->path('admin_organizations'), 303);
    }

    private function validate(Form $form, Organization $model, ?int $id): bool
    {
        $valid = $form->validate(Request::getInstance()->post());

        if ($form->error('name') === '' && $model->nameTaken($form->value('name'), $id)) {
            $form->addError('name', Translator::getInstance()->trans('This name is already used by another organization.'));

            return false;
        }

        return $valid;
    }

    /** @return array<string, string> */
    private function values(Form $form): array
    {
        $values = [];

        foreach (array_keys(Organization::FIELDS) as $name) {
            $values[$name] = $form->value($name);
        }

        return $values;
    }

    private function done(string $message): string
    {
        Session::getInstance()->flash('success', Translator::getInstance()->trans($message));

        return $this->redirect(Router::getInstance()->path('admin_organizations'), 303);
    }
}
