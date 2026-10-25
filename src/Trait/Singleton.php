<?php

declare(strict_types=1);

namespace Yawasla\Trait;

trait Singleton
{
    private static ?self $instance = null;

    public static function getInstance(): static
    {
        return self::$instance ??= new static();
    }

    private function __construct()
    {
    }
}
