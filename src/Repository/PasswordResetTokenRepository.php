<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PasswordResetToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findByEmail(string $email): ?PasswordResetToken
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function deleteByEmail(string $email): void
    {
        $token = $this->findByEmail($email);
        if ($token !== null) {
            $this->getEntityManager()->remove($token);
            $this->getEntityManager()->flush();
        }
    }

    public function upsert(string $email, string $hashedToken): void
    {
        $existing = $this->findByEmail($email);
        if ($existing !== null) {
            $existing->setToken($hashedToken);
        } else {
            $this->getEntityManager()->persist(new PasswordResetToken($email, $hashedToken));
        }
        $this->getEntityManager()->flush();
    }
}
