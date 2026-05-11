<?php

declare(strict_types=1);

namespace App\SiteUi;

/**
 * Session key for UI language when the visitor is not authenticated (login/register).
 * Authenticated users use {@see User::getSiteLanguage()} persisted in the database.
 */
final class SiteLanguagePreference
{
    public const SESSION_KEY = 'site_language';

    private function __construct()
    {
    }
}
