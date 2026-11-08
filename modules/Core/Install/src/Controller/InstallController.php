<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Install\Controller;

use PDOException;
use Throwable;
use Yawasla\Auth\Password;
use Yawasla\Controller\AbstractController;
use Yawasla\Core\Installation;
use Yawasla\Database\Connection;
use Yawasla\Database\ConnectionException;
use Yawasla\Form\Form;
use Yawasla\Http\NotFoundException;
use Yawasla\Http\Route;
use Yawasla\Http\Router;
use Yawasla\Http\Session;
use Yawasla\I18n\Translator;
use Yawasla\Module\Core\Install\Model\Installer;
use Yawasla\Module\Core\Install\Requirements;

final class InstallController extends AbstractController
{
    private const SESSION_KEY = 'install';

    private const TEMPLATES = [1 => 'site', 2 => 'organization', 3 => 'admin', 4 => 'confirm'];

    #[Route('/install', name: 'install', methods: ['GET', 'POST'])]
    public function index(): string
    {
        $this->denyIfInstalled();

        $requirements = new Requirements();

        if (!$requirements->prepareStorage()) {
            return htmlspecialchars(Translator::getInstance()->trans('The data/ directory must be writable.'), ENT_QUOTES);
        }

        $errors = $requirements->errors();

        if ($errors !== []) {
            return $this->requirements($errors, $requirements->databaseMissing());
        }

        $state = $this->state();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->show($state, $this->form($state));
        }

        if (isset($_POST['back'])) {
            return $this->back($state);
        }

        return $state['step'] === 4 ? $this->finish($state) : $this->submit($state);
    }

    /** @param list<string> $errors */
    private function requirements(array $errors, bool $databaseMissing): string
    {
        $createFailed = false;

        if ($databaseMissing && isset($_POST['create_database']) && Form::validToken($_POST)) {
            try {
                Connection::createDatabase();

                return $this->reload();
            } catch (PDOException $e) {
                error_log((string) $e);
                $createFailed = true;
            }
        }

        return $this->render('install/requirements.twig', [
            'errors' => $errors,
            'databaseMissing' => $databaseMissing,
            'createFailed' => $createFailed,
            'charset' => Connection::CHARSET,
            'collation' => Connection::COLLATION,
        ]);
    }

    private function denyIfInstalled(): void
    {
        try {
            $installed = Installation::getInstance()->installed();
        } catch (ConnectionException) {
            return;
        }

        if ($installed) {
            throw new NotFoundException();
        }
    }

    /** @return array{step: int, site: array<string, string>, organization: array<string, string>, admin: array<string, string>} */
    private function state(): array
    {
        return Session::getInstance()->get(self::SESSION_KEY, ['step' => 1, 'site' => [], 'organization' => [], 'admin' => []]);
    }

    /** @param array{step: int, site: array<string, string>, organization: array<string, string>, admin: array<string, string>} $state */
    private function form(array $state): Form
    {
        return match ($state['step']) {
            1 => new Form([
                'site_url' => ['required' => true, 'max' => 255, 'type' => 'url'],
                'site_name' => ['required' => true, 'max' => 255],
                'site_description' => ['required' => true, 'max' => 500],
            ], $state['site']),
            2 => new Form([
                'name' => ['required' => true, 'max' => 255],
                'address' => ['max' => 255],
                'zip_code' => ['max' => 20],
                'city' => ['max' => 100],
                'country' => ['max' => 100],
            ], ['name' => $state['site']['site_name'] ?? ''] + array_filter($state['organization'], fn (string $value): bool => $value !== '')),
            3 => new Form([
                'email' => ['required' => true, 'max' => 255, 'type' => 'email'],
                'first_name' => ['required' => true, 'max' => 100],
                'last_name' => ['required' => true, 'max' => 100],
                'password' => ['required' => !isset($state['admin']['password_hash']), 'type' => 'password'],
                'password_confirm' => ['required' => !isset($state['admin']['password_hash']), 'type' => 'password'],
            ], $state['admin']),
            default => new Form([]),
        };
    }

    /** @param array{step: int, site: array<string, string>, organization: array<string, string>, admin: array<string, string>} $state */
    private function show(array $state, Form $form): string
    {
        return $this->render('install/' . self::TEMPLATES[$state['step']] . '.twig', [
            'step' => $state['step'],
            'form' => $form,
            'state' => $state,
            'passwordSet' => isset($state['admin']['password_hash']),
        ]);
    }

    /** @param array{step: int, site: array<string, string>, organization: array<string, string>, admin: array<string, string>} $state */
    private function back(array $state): string
    {
        if (Form::validToken($_POST) && $state['step'] > 1) {
            $state['step']--;
            Session::getInstance()->set(self::SESSION_KEY, $state);
        }

        return $this->reload();
    }

    /** @param array{step: int, site: array<string, string>, organization: array<string, string>, admin: array<string, string>} $state */
    private function submit(array $state): string
    {
        $form = $this->form($state);
        $valid = $form->validate($_POST);

        if ($state['step'] === 3) {
            $valid = $this->checkPassword($form) && $valid;
        }

        if (!$valid) {
            return $this->show($state, $form);
        }

        match ($state['step']) {
            1 => $state['site'] = [
                'site_url' => $form->value('site_url'),
                'site_name' => $form->value('site_name'),
                'site_description' => $form->value('site_description'),
            ],
            2 => $state['organization'] = [
                'name' => $form->value('name'),
                'address' => $form->value('address'),
                'zip_code' => $form->value('zip_code'),
                'city' => $form->value('city'),
                'country' => $form->value('country'),
            ],
            3 => $state['admin'] = [
                'email' => $form->value('email'),
                'first_name' => $form->value('first_name'),
                'last_name' => $form->value('last_name'),
                'password_hash' => $form->value('password') !== '' ? Password::hash($form->value('password')) : $state['admin']['password_hash'],
            ],
        };

        $state['step']++;
        Session::getInstance()->set(self::SESSION_KEY, $state);

        return $this->reload();
    }

    private function checkPassword(Form $form): bool
    {
        $password = $form->value('password');
        $translator = Translator::getInstance();

        if ($password !== '' && !Password::isValid($password)) {
            $form->addError('password', $translator->trans('At least %min% characters, including a digit and a symbol.', ['%min%' => Password::MIN_LENGTH]));

            return false;
        }

        if ($password !== $form->value('password_confirm')) {
            $form->addError('password_confirm', $translator->trans('The passwords do not match.'));

            return false;
        }

        return true;
    }

    /** @param array{step: int, site: array<string, string>, organization: array<string, string>, admin: array<string, string>} $state */
    private function finish(array $state): string
    {
        $form = new Form([]);

        if (!$form->validate($_POST)) {
            return $this->show($state, $form);
        }

        try {
            (new Installer())->install($state['site'], $state['organization'], $state['admin']);
        } catch (Throwable $e) {
            error_log((string) $e);
            $form->addError(Form::GLOBAL_ERROR, Translator::getInstance()->trans('The installation failed. Details are in data/logs/php.log.'));

            return $this->show($state, $form);
        }

        Installation::getInstance()->complete();

        $session = Session::getInstance();
        $session->remove(self::SESSION_KEY);
        $session->regenerate();

        return $this->render('install/done.twig', ['step' => 5, 'adminUrl' => $this->adminUrl()]);
    }

    private function adminUrl(): string
    {
        $locale = Translator::getInstance()->locale();

        return Router::getInstance()->basePath() . '/admin' . ($locale === 'en' ? '' : '/' . $locale);
    }

    private function reload(): string
    {
        return $this->redirect(Router::getInstance()->path('install'), 303);
    }
}
