<?php

declare(strict_types=1);

namespace JavaRoot\Resources\Service;

use JavaRoot\Resources\Infrastructure\Database\PreferenceRepository;
use JavaRoot\Resources\Security\InputPolicy;

final class ThemeService
{
    public function __construct(private PreferenceRepository $preferences)
    {
    }

    public function current(): string
    {
        global $mybb;

        $theme = 'ordinary';
        if (!empty($mybb->user['uid'])) {
            $stored = $this->preferences->theme((int)$mybb->user['uid']);
            if ($stored !== null) {
                $theme = $stored;
            }
        } elseif (isset($_COOKIE['javaroot_theme'])) {
            $theme = (string)$_COOKIE['javaroot_theme'];
        }

        return in_array($theme, InputPolicy::THEMES, true) ? $theme : 'ordinary';
    }

    public function save(string $theme, string $returnUrl): string
    {
        global $mybb;

        $theme = InputPolicy::theme($theme);
        if (!empty($mybb->user['uid'])) {
            $uid = (int)$mybb->user['uid'];
            $this->preferences->saveTheme($uid, $theme);
            if (isset($_COOKIE['javaroot_theme'])) {
                my_unsetcookie('javaroot_theme');
            }
        } else {
            my_setcookie('javaroot_theme', $theme, TIME_NOW + 31536000, true, 'lax');
        }

        $boardUrl = rtrim((string)$mybb->settings['bburl'], '/');

        return InputPolicy::safeReturnUrl($returnUrl, $boardUrl);
    }

    public function themes(): array
    {
        global $lang;

        return [
            ['id' => 'ordinary', 'name' => $lang->javaroot_theme_ordinary, 'description' => 'Светлое оформление для повседневного использования.'],
            ['id' => 'dark', 'name' => $lang->javaroot_theme_dark, 'description' => 'Тёмное оформление с графитовым фоном.'],
            ['id' => 'night', 'name' => $lang->javaroot_theme_night, 'description' => 'Почти чёрное оформление для ночной работы.'],
        ];
    }
}
