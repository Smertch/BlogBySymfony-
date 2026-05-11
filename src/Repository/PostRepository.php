<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * @return Post[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * All posts by a user, newest first (for account dashboard).
     *
     * @return Post[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Post[]
     */
    public function findLatestForFeed(int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Public feed with optional title/content search, comments/likes/authors eager loaded.
     *
     * @return Post[]
     */
    public function feedSearch(?string $query, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.comments', 'c')->addSelect('c')
            ->leftJoin('c.user', 'cu')->addSelect('cu')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($query !== null && $query !== '') {
            $qb->andWhere('LOWER(p.title) LIKE :q OR LOWER(p.content) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($query).'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Server-side title search with pagination for the admin posts list.
     *
     * @return array{items: Post[], total: int}
     */
    public function searchPaginated(?string $query, int $page = 1, int $perPage = 10): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u');

        if ($query !== null && $query !== '') {
            $qb->andWhere('LOWER(p.title) LIKE :q OR LOWER(u.name) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($query).'%');
        }

        $qb->orderBy('p.id', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery(), false);

        return [
            'items' => iterator_to_array($paginator),
            'total' => \count($paginator),
        ];
    }

    /**
     * Delete every post whose id appears in $ids.
     *
     * @param int[] $ids
     */
    public function deleteByIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        return (int) $this->createQueryBuilder('p')
            ->delete()
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
