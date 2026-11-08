<?php

declare(strict_types=1);

namespace Yawasla\Form;

use Yawasla\Core\Config;
use Yawasla\Http\Session;
use Yawasla\I18n\Translator;

final class Form
{
    public const GLOBAL_ERROR = '_form';

    private const TOKEN = '_csrf';

    /** @var array<string, string> */
    private array $values = [];

    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, array{required?: bool, max?: int, type?: string}> $fields
     * @param array<string, mixed> $values
     */
    public function __construct(private readonly array $fields, array $values = [])
    {
        foreach ($fields as $name => $field) {
            $this->values[$name] = is_string($values[$name] ?? null) ? $values[$name] : '';
        }
    }

    public static function token(): string
    {
        $session = Session::getInstance();
        $token = $session->get(self::TOKEN);

        if (!is_string($token)) {
            $token = bin2hex(random_bytes(32));
            $session->set(self::TOKEN, $token);
        }

        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::TOKEN . '" value="' . self::token() . '">';
    }

    /** @param array<string, mixed> $input */
    public static function validToken(array $input): bool
    {
        return is_string($input[self::TOKEN] ?? null) && hash_equals(self::token(), $input[self::TOKEN]);
    }

    /** @param array<string, mixed> $input */
    public function validate(array $input): bool
    {
        $this->errors = [];

        if (!self::validToken($input)) {
            $this->addError(self::GLOBAL_ERROR, $this->trans('Your session has expired. Please try again.'));
        }

        foreach ($this->fields as $name => $field) {
            $this->values[$name] = $this->normalize($input[$name] ?? '', $field['type'] ?? 'text');
            $error = $this->check($this->values[$name], $field);

            if ($error !== null) {
                $this->addError($name, $error);
            }
        }

        return $this->errors === [];
    }

    public function addError(string $name, string $message): void
    {
        $this->errors[$name] ??= $message;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function error(string $name): string
    {
        return $this->errors[$name] ?? '';
    }

    public function value(string $name): string
    {
        return $this->values[$name] ?? '';
    }

    private function normalize(mixed $value, string $type): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return '';
        }

        return match ($type) {
            'password' => $value,
            'email' => mb_strtolower(trim($value)),
            'url' => rtrim(trim($value), '/'),
            default => trim($value),
        };
    }

    /** @param array{required?: bool, max?: int, type?: string} $field */
    private function check(string $value, array $field): ?string
    {
        if ($value === '') {
            return ($field['required'] ?? false) ? $this->trans('This field is required.') : null;
        }

        if (isset($field['max']) && mb_strlen($value) > $field['max']) {
            return $this->trans('Maximum %max% characters.', ['%max%' => $field['max']]);
        }

        return match ($field['type'] ?? 'text') {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? $this->trans('Invalid email address.') : null,
            'url' => $this->isAllowedUrl($value) ? null : $this->trans('Enter a full address starting with https://'),
            default => null,
        };
    }

    private function isAllowedUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($value);

        $schemes = Config::getInstance()->get('APP_ENV') === 'dev' ? ['https', 'http'] : ['https'];

        return in_array($parts['scheme'] ?? '', $schemes, true)
            && ($parts['host'] ?? '') !== ''
            && !isset($parts['user'])
            && !isset($parts['query'])
            && !isset($parts['fragment']);
    }

    /** @param array<string, string|int> $params */
    private function trans(string $text, array $params = []): string
    {
        return Translator::getInstance()->trans($text, $params);
    }
}
