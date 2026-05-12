<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SiteLanguage;
use App\Repository\SiteTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SiteTranslationRepository::class)]
#[ORM\Table(name: 'site_translation')]
#[ORM\UniqueConstraint(name: 'UNIQ_site_translation_alias_language', columns: ['alias', 'language_type'])]
class SiteTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 190)]
    #[ORM\Column(length: 190)]
    private string $alias = '';

    #[ORM\Column(name: 'language_type', length: 3, enumType: SiteLanguage::class)]
    private SiteLanguage $languageType = SiteLanguage::EN;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    private string $translate = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    public function getLanguageType(): SiteLanguage
    {
        return $this->languageType;
    }

    public function setLanguageType(SiteLanguage $languageType): self
    {
        $this->languageType = $languageType;

        return $this;
    }

    public function getTranslate(): string
    {
        return $this->translate;
    }

    public function setTranslate(string $translate): self
    {
        $this->translate = $translate;

        return $this;
    }

    public function __toString(): string
    {
        return $this->alias.' · '.$this->languageType->value;
    }
}
