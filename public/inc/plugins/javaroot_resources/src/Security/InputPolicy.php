<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Security;

use JavaRoot\Resources\Exception\InvalidRequestException;

final class InputPolicy
{
    public const THEMES = ['ordinary', 'dark', 'night'];
    public const RESOURCE_STATUSES = ['published', 'hidden', 'rejected', 'archived'];
    public const UPLOAD_EXTENSIONS = ['jar', 'zip', 'rar'];

    public static function slug(string $title): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($title))) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'resource-' . bin2hex(random_bytes(4));
    }

    public static function externalUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && (!isset($parts['port']) || $parts['port'] === 443)
            && in_array(strtolower($parts['host'] ?? ''), [
                'github.com',
                'www.github.com',
                'modrinth.com',
                'www.modrinth.com',
                'spigotmc.org',
                'www.spigotmc.org',
            ], true);
    }

    public static function theme(string $theme): string
    {
        if (!in_array($theme, self::THEMES, true)) {
            throw new InvalidRequestException();
        }

        return $theme;
    }

    public static function resourceStatus(string $status): string
    {
        if (!in_array($status, self::RESOURCE_STATUSES, true)) {
            throw new InvalidRequestException();
        }

        return $status;
    }

    public static function filename(string $filename): array
    {
        $filename = basename($filename);
        $filename = preg_replace('/[\x00-\x1F\x7F]+/', '_', $filename) ?? '';
        $filename = trim($filename, " .\t\r\n");
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return [$filename, $extension];
    }

    public static function sameOrigin(string $returnUrl, string $boardUrl): bool
    {
        $boardParts = parse_url(rtrim($boardUrl, '/'));
        $returnParts = parse_url($returnUrl);

        return is_array($boardParts)
            && is_array($returnParts)
            && ($returnParts['scheme'] ?? '') === ($boardParts['scheme'] ?? '')
            && strtolower($returnParts['host'] ?? '') === strtolower($boardParts['host'] ?? '')
            && (($returnParts['port'] ?? null) === ($boardParts['port'] ?? null));
    }

    public static function safeReturnUrl(string $returnUrl, string $boardUrl): string
    {
        if ($returnUrl !== '' && (self::sameOrigin($returnUrl, $boardUrl) || str_starts_with($returnUrl, '/'))) {
            return $returnUrl;
        }

        return rtrim($boardUrl, '/') . '/index.php';
    }
}
