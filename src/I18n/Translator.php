<?php

declare(strict_types=1);

namespace Yawasla\I18n;

use Yawasla\Core\Modules;
use Yawasla\Debug\Debug;
use Yawasla\Http\Router;
use Yawasla\Trait\Singleton;

final class Translator
{
    use Singleton;

    public const LOCALES = ['en', 'fr', 'ar'];

    private const RTL = ['ar'];

    private string $locale;

    /** @var array<string, string> */
    private array $messages = [];

    private function __construct()
    {
        $locale = Router::getInstance()->locale();
        $this->locale = in_array($locale, self::LOCALES, true) ? $locale : 'en';

        if ($this->locale !== 'en') {
            foreach (Modules::getInstance()->enabled() as $module) {
                $file = $module['dir'] . '/translations/' . $this->locale . '.php';

                if (is_file($file)) {
                    $this->messages += require $file;
                }
            }
        }
    }

    /** @param array<string, string|int> $params */
    public function trans(string $text, array $params = []): string
    {
        if ($this->locale !== 'en' && !isset($this->messages[$text])) {
            Debug::getInstance()->missingTranslation($text);
        }

        return strtr($this->messages[$text] ?? $text, array_map('strval', $params));
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function dir(): string
    {
        return in_array($this->locale, self::RTL, true) ? 'rtl' : 'ltr';
    }
}
