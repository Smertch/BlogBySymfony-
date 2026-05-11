<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface as GoogleTwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'users_email_unique', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, GoogleTwoFactorInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 1])]
    private int $roleId = 1;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $email = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $password = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $twoFactorSecret = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $twoFactorRecoveryCodes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $twoFactorConfirmedAt = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $rememberToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, Post>
     */
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'user')]
    private Collection $posts;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->posts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id !== null ? (int) $this->id : null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->touch();

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        $this->touch();

        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $value): self
    {
        $this->emailVerifiedAt = $value;
        $this->touch();

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): self
    {
        $this->password = $hashedPassword;
        $this->touch();

        return $this;
    }

    public function getRole(): UserRole
    {
        return UserRole::from($this->roleId);
    }

    public function getRoleLabel(): string
    {
        return $this->getRole()->label();
    }

    public function setRole(UserRole $role): self
    {
        $this->roleId = $role->value;
        $this->touch();

        return $this;
    }

    public function isAdmin(): bool
    {
        return $this->getRole() === UserRole::Admin;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(?string $token): self
    {
        $this->rememberToken = $token;

        return $this;
    }

    public function getTwoFactorConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->twoFactorConfirmedAt;
    }

    public function setTwoFactorConfirmedAt(?\DateTimeImmutable $value): self
    {
        $this->twoFactorConfirmedAt = $value;
        $this->touch();

        return $this;
    }

    public function setTwoFactorRecoveryCodes(?string $codes): self
    {
        $this->twoFactorRecoveryCodes = $codes;
        $this->touch();

        return $this;
    }

    public function getTwoFactorRecoveryCodes(): ?string
    {
        return $this->twoFactorRecoveryCodes;
    }

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function getRoles(): array
    {
        return $this->isAdmin() ? ['ROLE_USER', 'ROLE_ADMIN'] : ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function isGoogleAuthenticatorEnabled(): bool
    {
        return $this->twoFactorSecret !== null && $this->twoFactorConfirmedAt !== null;
    }

    public function getGoogleAuthenticatorUsername(): string
    {
        return $this->email;
    }

    public function getGoogleAuthenticatorSecret(): ?string
    {
        return $this->twoFactorSecret;
    }

    public function setGoogleAuthenticatorSecret(?string $secret): self
    {
        $this->twoFactorSecret = $secret;
        $this->touch();

        return $this;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
