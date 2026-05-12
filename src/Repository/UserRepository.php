<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface, PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->findOneBy(['email' => $identifier]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function save(User $user, bool $flush = true): void
    {
        $em = $this->getEntityManager();
        $em->persist($user);
        if ($flush) {
            $em->flush();
        }
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countWithTwoFactor(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.twoFactorConfirmedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return User[]
     */
    public function findLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Server-side search + 2FA filter with pagination for the admin user list.
     *
     * @return array{items: User[], total: int}
     */
    public function searchPaginated(?string $query, ?string $twoFactorFilter, int $page = 1, int $perPage = 10): array
    {
        $qb = $this->createQueryBuilder('u');

        if (null !== $query && '' !== $query) {
            $qb->andWhere('LOWER(u.name) LIKE :q OR LOWER(u.email) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($query).'%');
        }

        if ('confirmed' === $twoFactorFilter) {
            $qb->andWhere('u.twoFactorConfirmedAt IS NOT NULL');
        } elseif ('null' === $twoFactorFilter) {
            $qb->andWhere('u.twoFactorConfirmedAt IS NULL');
        }

        $qb->orderBy('u.id', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery(), false);

        return [
            'items' => iterator_to_array($paginator),
            'total' => \count($paginator),
        ];
    }
}
