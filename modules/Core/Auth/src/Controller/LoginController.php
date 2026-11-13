<?php

declare(strict_types=1);

namespace Yawasla\Module\Core\Auth\Controller;

use Yawasla\Auth\Auth;
use Yawasla\Auth\Password;
use Yawasla\Auth\Throttle;
use Yawasla\Controller\AbstractController;
use Yawasla\Form\Form;
use Yawasla\Http\Route;
use Yawasla\Http\Router;
use Yawasla\Http\Session;
use Yawasla\I18n\Translator;
use Yawasla\Mail\Mailer;
use Yawasla\Model\Setting;
use Yawasla\Model\Token;
use Yawasla\Model\User;

final class LoginController extends AbstractController
{
    private const NOTICE = 'auth_notice';

    private const LOGIN_MINUTES = 5;

    private const RESET_MINUTES = 30;

    public function __construct()
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(): string
    {
        $throttle = new Throttle();

        if ($throttle->blocked()) {
            return $this->blocked('login');
        }

        $form = new Form([
            'email' => ['required' => true, 'max' => 255, 'type' => 'email'],
            'password' => ['required' => true, 'type' => 'password'],
        ]);

        if ($this->posted()) {
            $throttle->hit();

            if ($form->validate($_POST)) {
                $this->sendLoginLink($form->value('email'), $form->value('password'));

                return $this->message('Check your email', 'If the email and password are correct, a sign-in link has been sent. It is valid for %minutes% minutes.', self::LOGIN_MINUTES);
            }
        }

        $notice = Session::getInstance()->get(self::NOTICE, '');
        Session::getInstance()->remove(self::NOTICE);

        return $this->render('auth/login.twig', ['form' => $form, 'notice' => $notice]);
    }

    #[Route('/login/confirm/{token}', name: 'login_confirm', methods: ['GET', 'POST'])]
    public function confirm(string $token): string
    {
        $tokens = new Token();

        if ($tokens->userId($token, 'login') === null) {
            return $this->invalidLink();
        }

        $form = new Form([]);

        if ($this->posted() && $form->validate($_POST)) {
            $userId = $tokens->use($token, 'login');

            if ($userId === null || (new User())->findActive($userId) === null) {
                return $this->invalidLink();
            }

            Auth::getInstance()->login($userId);

            $router = Router::getInstance();

            return $this->redirect($router->basePath() . $router->localize('/admin'), 303);
        }

        return $this->render('auth/confirm.twig', ['form' => $form, 'token' => $token]);
    }

    #[Route('/login/reset', name: 'login_reset', methods: ['GET', 'POST'])]
    public function reset(): string
    {
        $throttle = new Throttle();

        if ($throttle->blocked()) {
            return $this->blocked('login_reset');
        }

        $form = new Form(['email' => ['required' => true, 'max' => 255, 'type' => 'email']]);

        if ($this->posted()) {
            $throttle->hit();

            if ($form->validate($_POST)) {
                $this->sendResetLink($form->value('email'));

                return $this->message('Check your email', 'If an account exists for this email, a link has been sent. It is valid for %minutes% minutes.', self::RESET_MINUTES);
            }
        }

        return $this->render('auth/reset.twig', ['form' => $form]);
    }

    #[Route('/login/reset/{token}', name: 'login_reset_token', methods: ['GET', 'POST'])]
    public function password(string $token): string
    {
        $tokens = new Token();

        if ($tokens->userId($token, 'reset') === null) {
            return $this->invalidLink();
        }

        $form = new Form([
            'password' => ['required' => true, 'type' => 'password'],
            'password_confirm' => ['required' => true, 'type' => 'password'],
        ]);

        if ($this->posted() && $form->validate($_POST) && $this->checkPassword($form)) {
            $userId = $tokens->use($token, 'reset');

            if ($userId === null) {
                return $this->invalidLink();
            }

            (new User())->changePassword($userId, Password::hash($form->value('password')));
            $tokens->cancel($userId);
            Session::getInstance()->set(self::NOTICE, Translator::getInstance()->trans('Your password has been changed. You can sign in.'));

            return $this->redirect(Router::getInstance()->path('login'), 303);
        }

        return $this->render('auth/password.twig', ['form' => $form, 'token' => $token]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'], auth: 'user')]
    public function logout(): string
    {
        if (Form::validToken($_POST)) {
            Auth::getInstance()->logout();
        }

        return $this->redirect(Router::getInstance()->path('login'), 303);
    }

    private function sendLoginLink(string $email, string $password): void
    {
        $users = new User();
        $hash = $users->passwordHash($email);

        if ($hash === null) {
            Password::hash($password);

            return;
        }

        if (!Password::verify($password, $hash)) {
            return;
        }

        $user = $users->findActiveByEmail($email);

        if ($user === null) {
            return;
        }

        $token = (new Token())->create($user['id'], 'login', self::LOGIN_MINUTES);

        $this->mail($user, 'Sign in to %site%', 'Open this link to sign in. It is valid for %minutes% minutes and works only once:', self::LOGIN_MINUTES, 'login_confirm', $token, 'If you did not try to sign in, someone knows your password: change it.');
    }

    private function sendResetLink(string $email): void
    {
        $user = (new User())->findActiveByEmail($email);

        if ($user === null) {
            return;
        }

        $token = (new Token())->create($user['id'], 'reset', self::RESET_MINUTES);

        $this->mail($user, 'Reset your password on %site%', 'Open this link to choose a new password. It is valid for %minutes% minutes and works only once:', self::RESET_MINUTES, 'login_reset_token', $token, 'If you did not ask for this email, ignore it.');
    }

    /** @param array<string, mixed> $user */
    private function mail(array $user, string $subject, string $text, int $minutes, string $route, string $token, string $warning): void
    {
        $settings = new Setting();
        $router = Router::getInstance();
        $translator = Translator::getInstance();
        $site = $settings->get('site_name');
        $url = rtrim($settings->get('site_url'), '/') . substr($router->path($route, ['token' => $token]), strlen($router->basePath()));

        $body = $translator->trans('Hello %name%,', ['%name%' => (string) $user['first_name']]) . "\n\n"
            . $translator->trans($text, ['%minutes%' => $minutes]) . "\n" . $url . "\n\n"
            . $translator->trans($warning) . "\n\n" . $site . "\n";

        (new Mailer())->send((string) $user['email'], $translator->trans($subject, ['%site%' => $site]), $body);
    }

    private function checkPassword(Form $form): bool
    {
        $password = $form->value('password');
        $translator = Translator::getInstance();

        if (!Password::isValid($password)) {
            $form->addError('password', $translator->trans('At least %min% characters, including a digit and a symbol.', ['%min%' => Password::MIN_LENGTH]));

            return false;
        }

        if ($password !== $form->value('password_confirm')) {
            $form->addError('password_confirm', $translator->trans('The passwords do not match.'));

            return false;
        }

        return true;
    }

    private function blocked(string $route): string
    {
        if ($this->posted()) {
            return $this->redirect(Router::getInstance()->path($route), 303);
        }

        return $this->message('Too many attempts', 'Please try again in an hour.');
    }

    private function invalidLink(): string
    {
        return $this->message('Invalid link', 'This link is invalid, expired or has already been used.');
    }

    private function message(string $title, string $text, int $minutes = 0): string
    {
        return $this->render('auth/message.twig', ['title' => $title, 'text' => $text, 'minutes' => $minutes]);
    }

    private function posted(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}
