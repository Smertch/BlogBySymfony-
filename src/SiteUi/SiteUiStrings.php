<?php

declare(strict_types=1);

namespace App\SiteUi;

use App\Enum\SiteLanguage;

/**
 * Resolved UI string for a locale: English from {@see SiteUiDefaults::ALIASES},
 * other languages from PHP maps in {@code SiteUi/locale/*.php}.
 */
final class SiteUiStrings
{
    /** @var array<string, array<string, string>> */
    private static array $localeMaps = [];

    public static function text(SiteLanguage $language, string $alias): string
    {
        $english = SiteUiDefaults::ALIASES[$alias] ?? $alias;
        if ($language === SiteLanguage::EN) {
            return $english;
        }

        $map = self::localeMap($language);

        return $map[$alias] ?? $english;
    }

    /**
     * @return array<string, string>
     */
    private static function localeMap(SiteLanguage $language): array
    {
        $code = match ($language) {
            SiteLanguage::DE => 'de',
            SiteLanguage::ES => 'es',
            SiteLanguage::UA => 'ua',
            default => '',
        };
        if ($code === '') {
            return [];
        }
        if (!isset(self::$localeMaps[$code])) {
            $path = __DIR__.'/locale/'.$code.'.php';
            self::$localeMaps[$code] = is_file($path) ? require $path : [];
        }

        return self::$localeMaps[$code];
    }
}
