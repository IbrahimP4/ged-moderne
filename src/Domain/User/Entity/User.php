<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\ValueObject\UserId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'user_id', length: 36)]
    private UserId $id;

    #[ORM\Column(name: 'username', length: 100, unique: true)]
    private string $username;

    #[ORM\Column(name: 'email', length: 255, unique: true)]
    private string $email;

    #[ORM\Column(name: 'hashed_password', length: 255)]
    private string $hashedPassword;

    #[ORM\Column(name: 'is_admin', options: ['default' => false])]
    private bool $isAdmin = false;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        UserId $id,
        string $username,
        string $email,
        string $hashedPassword,
        bool $isAdmin,
    ) {
        $this->id             = $id;
        $this->username       = $username;
        $this->email          = $email;
        $this->hashedPassword = $hashedPassword;
        $this->isAdmin        = $isAdmin;
        $this->createdAt      = new \DateTimeImmutable();
    }

    public static function create(
        string $username,
        string $email,
        string $hashedPassword,
        bool $isAdmin = false,
    ): self {
        return new self(
            UserId::generate(),
            $username,
            $email,
            $hashedPassword,
            $isAdmin,
        );
    }

    public function getId(): UserId
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getHashedPassword(): string
    {
        return $this->hashedPassword;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function changePassword(string $newHashedPassword): void
    {
        $this->hashedPassword = $newHashedPassword;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /**
     * Promouvoir ou rétrograder un utilisateur.
     * Un admin ne peut pas modifier son propre rôle.
     */
    public function changeRole(bool $makeAdmin, User $changedBy): void
    {
        if ($this->id->equals($changedBy->getId())) {
            throw new \DomainException('Un administrateur ne peut pas modifier son propre rôle.');
        }

        $this->isAdmin = $makeAdmin;
    }
}
