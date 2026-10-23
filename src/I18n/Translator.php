<?php

declare(strict_types=1);

namespace Yawasla\I18n;

/**
 * Textes de l'interface écrits en anglais dans le code : l'anglais n'a pas de fichier, une chaîne non traduite reste en anglais.
 */
final class Translator
{
    public const LOCALES = ['en', 'fr', 'ar'];

    private const RTL = ['ar'];

    private string $locale;

    /** @var array<string, string> anglais → traduction */
    private array $messages = [];

    public function __construct(string $locale, string $dir)
    {
        // Liste blanche : la langue sert à construire un chemin de fichier
        $this->locale = in_array($locale, self::LOCALES, true) ? $locale : 'en';

        if ($this->locale !== 'en') {
            $this->messages = require $dir . '/' . $this->locale . '.php';
        }
    }

    /** @param array<string, string|int> $params remplacements, ex. ['%name%' => 'Ali'] */
    public function trans(string $text, array $params = []): string
    {
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
