<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\SiteLanguage;
use App\Repository\SiteTranslationRepository;
use App\SiteUi\SiteLanguagePreference;
use App\SiteUi\SiteUiStrings;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

final class SiteUiTranslator implements ResetInterface
{
    /** @var array<string, string>|null */
    private ?array $map = null;

    private ?string $mapForLanguage = null;

    public function __construct(
        private readonly SiteTranslationRepository $translations,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    public function getLanguage(): SiteLanguage
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            return $user->getSiteLanguage();
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return SiteLanguage::EN;
        }

        $raw = $request->getSession()->get(SiteLanguagePreference::SESSION_KEY);
        if (\is_string($raw)) {
            $lang = SiteLanguage::tryFrom(strtoupper($raw));
            if ($lang !== null) {
                return $lang;
            }
        }

        return SiteLanguage::EN;
    }

    /**
     * @param array<string, string> $params Placeholders e.g. ['%name%' => 'Ann']
     */
    public function trans(string $alias, array $params = []): string
    {
        $text = $this->lookup($alias);
        if ($params === []) {
            return $text;
        }

        return strtr($text, $params);
    }

    public function clearRuntimeCache(): void
    {
        $this->map = null;
        $this->mapForLanguage = null;
    }

    public function reset(): void
    {
        $this->clearRuntimeCache();
    }

    private function lookup(string $alias): string
    {
        $map = $this->getMap();
        if (isset($map[$alias])) {
            return $map[$alias];
        }

        return SiteUiStrings::text($this->getLanguage(), $alias);
    }

    /**
     * @return array<string, string>
     */
    private function getMap(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return [];
        }

        $lang = $this->getLanguage();
        $langKey = $lang->value;

        if ($this->map !== null && $this->mapForLanguage === $langKey) {
            return $this->map;
        }

        $this->mapForLanguage = $langKey;
        $this->map = $this->translations->getMapForLanguage($lang);

        return $this->map;
    }
}
