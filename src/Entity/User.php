<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Assert\NotBlank(message: "Email cannot be blank.")]
    #[Assert\Email(message: "Please enter a valid email address.")]
    #[Assert\Length(
        max: 180,
        maxMessage: "Email cannot exceed {{ limit }} characters."
    )]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    #[Assert\NotNull(message: "Roles must be set.")]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    #[Assert\NotBlank(message: "Password cannot be blank.", groups: ["registration", "password_reset"])]
    #[Assert\Length(
        min: 6,
        minMessage: "Password must be at least {{ limit }} characters long.",
        groups: ["registration", "password_reset"]
    )]
    private ?string $password = null;

    /**
     * @var Collection<int, CustomerOrder>
     */
    #[ORM\OneToMany(targetEntity: CustomerOrder::class, mappedBy: 'client', cascade: ['persist', 'remove'])]
    private Collection $customerOrders;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** Last activity in admin/staff area; cleared on logout. Drives Account Active/Inactive in user listings. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank(message: "Username cannot be blank.")]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: "Username must be at least {{ limit }} characters long.",
        maxMessage: "Username cannot exceed {{ limit }} characters."
    )]
    #[Assert\Regex(
        pattern: "/^[a-zA-Z0-9_]+$/",
        message: "Username can only contain letters, numbers, and underscores."
    )]
    private ?string $username = null;

    // src/Entity/User.php
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "Image path cannot exceed {{ limit }} characters."
    )]
    private ?string $image = null;

    // Status constants
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_DISABLED = 'DISABLED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'ACTIVE'])]
    #[Assert\NotBlank(message: "Status cannot be blank.")]
    #[Assert\Choice(
        choices: [self::STATUS_ACTIVE, self::STATUS_DISABLED, self::STATUS_ARCHIVED],
        message: "Invalid status. Must be ACTIVE, DISABLED, or ARCHIVED."
    )]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private ?bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $verificationToken = null;

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
    
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?\DateTimeImmutable $lastActivityAt): self
    {
        $this->lastActivityAt = $lastActivityAt;

        return $this;
    }

    /**
     * True if the user has a recent admin/staff session (within $ttlSeconds).
     */
    public function isLoginPresenceActive(int $ttlSeconds = 300): bool
    {
        if ($this->lastActivityAt === null) {
            return false;
        }

        $threshold = new \DateTimeImmutable(sprintf('-%d seconds', $ttlSeconds));

        return $this->lastActivityAt >= $threshold;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function __construct()
    {
        $this->customerOrders = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    // -------------------- ID --------------------
    public function getId(): ?int
    {
        return $this->id;
    }

    // -------------------- Email --------------------
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    // -------------------- UserInterface --------------------
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user has at least ROLE_USER
        if (empty($roles)) {
            $roles[] = 'ROLE_USER';
        }
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    // -------------------- PasswordAuthenticatedUserInterface --------------------
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    // -------------------- eraseCredentials --------------------
    public function eraseCredentials(): void
    {
        // If you store any temporary sensitive data, clear it here
        // $this->plainPassword = null;
    }

    // -------------------- Customer Orders --------------------
    /**
     * @return Collection<int, CustomerOrder>
     */
    public function getCustomerOrders(): Collection
    {
        return $this->customerOrders;
    }

    public function addCustomerOrder(CustomerOrder $customerOrder): self
    {
        if (!$this->customerOrders->contains($customerOrder)) {
            $this->customerOrders->add($customerOrder);
            $customerOrder->setClient($this);
        }

        return $this;
    }

    public function removeCustomerOrder(CustomerOrder $customerOrder): self
    {
        if ($this->customerOrders->removeElement($customerOrder)) {
            if ($customerOrder->getClient() === $this) {
                $customerOrder->setClient(null);
            }
        }

        return $this;
    }

    // -------------------- Lifecycle Callbacks --------------------
    #[ORM\PrePersist]
    public function prePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function setVerificationToken(?string $verificationToken): static
    {
        $this->verificationToken = $verificationToken;

        return $this;
    }
    #[ORM\Column(length: 255, nullable: true)]
private ?string $googleId = null;

public function getGoogleId(): ?string
{
    return $this->googleId;
}

public function setGoogleId(?string $googleId): static
{
    $this->googleId = $googleId;
    return $this;
}
}
