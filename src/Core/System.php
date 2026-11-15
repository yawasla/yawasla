<?php

declare(strict_types=1);

namespace Yawasla\Core;

use Throwable;

final class System
{
    /** @return list<array{string, string}> */
    public static function tasks(): array
    {
        $messages = [];

        Trash::purge();

        if (Modules::getInstance()->updatePending()) {
            try {
                (new ModuleManager())->finishUpdate();
                $messages[] = ['success', 'Update finished.'];
            } catch (Throwable $e) {
                trigger_error((string) $e, E_USER_WARNING);
                $messages[] = ['danger', 'The update could not be finished. Details are in data/logs/php.log.'];
            }
        }

        return $messages;
    }
}
