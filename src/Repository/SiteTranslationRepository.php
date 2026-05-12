<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SiteTranslation;
use App\Enum\SiteLanguage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteTranslation>
 */
final class SiteTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteTranslation::class);
    }

    /**
     * @return array<string, string> alias => translated text (non-empty strings only)
     */
    public function getMapForLanguage(SiteLanguage $language): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.alias', 't.translate')
            ->andWhere('t.languageType = :lang')
            ->setParameter('lang', $language);

        /** @var list<array{alias: string, translate: string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $text = trim((string) $row['translate']);
            if ('' !== $text) {
                $map[(string) $row['alias']] = $text;
            }
        }

        return $map;
    }

    /**
     * Search by alias / translation text with optional language filter and pagination.
     *
     * @return array{items: SiteTranslation[], total: int}
     */
    public function searchPaginated(?string $query, ?string $languageFilter, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('t');

        if (null !== $query && '' !== $query) {
            $qb->andWhere('LOWER(t.alias) LIKE :q OR LOWER(t.translate) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($query).'%');
        }

        if (null !== $languageFilter && '' !== $languageFilter && 'all' !== $languageFilter) {
            $lang = SiteLanguage::tryFrom(strtoupper($languageFilter));
            if (null !== $lang) {
                $qb->andWhere('t.languageType = :lang')->setParameter('lang', $lang);
            }
        }

        $qb->orderBy('t.alias', 'ASC')
            ->addOrderBy('t.languageType', 'ASC')
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery(), false);

        return [
            'items' => iterator_to_array($paginator),
            'total' => \count($paginator),
        ];
    }
}
