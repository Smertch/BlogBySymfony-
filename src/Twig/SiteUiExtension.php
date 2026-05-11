<?php

declare(strict_types=1);

namespace App\Twig;

use App\Enum\SiteLanguage;
use App\Service\SiteUiTranslator;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class SiteUiExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly SiteUiTranslator $siteUi,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ui', [$this, 'translateUi']),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    public function translateUi(string $alias, array $params = []): string
    {
        return $this->siteUi->trans($alias, $params);
    }

    public function getGlobals(): array
    {
        return [
            'site_language' => $this->siteUi->getLanguage(),
            'site_languages' => SiteLanguage::cases(),
        ];
    }
}
