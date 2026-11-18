<?php

declare(strict_types=1);

namespace Yawasla\Command;

use Throwable;
use Yawasla\Form\Form;
use Yawasla\Model\Setting;

final class ConfigCommand implements Command
{
    public function description(): string
    {
        return 'Change the site address used in emails: config site_url <url>';
    }

    public function run(array $args): int
    {
        if (count($args) !== 2 || $args[0] !== 'site_url') {
            fwrite(STDERR, "Usage: php bin/console config site_url <url>\n");

            return 1;
        }

        $url = rtrim(trim($args[1]), '/');

        if (mb_strlen($url) > Setting::FIELDS['site_url']['max'] || !Form::allowedUrl($url)) {
            fwrite(STDERR, "Invalid address: a full address starting with https:// is required.\n");

            return 1;
        }

        try {
            (new Setting())->set('site_url', $url);
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage() . "\n");

            return 1;
        }

        echo "site_url: {$url}\n";

        return 0;
    }
}
