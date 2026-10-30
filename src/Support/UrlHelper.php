<?php

declare(strict_types=1);

namespace App\Support;

final class UrlHelper
{
    public static function domain(?string $url): string
    {
        if ($url === null || trim($url) === '') {
            return '';
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return '';
        }

        return preg_replace('/^www\./i', '', strtolower($host)) ?? strtolower($host);
    }
}
