<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Service
{
    // --------------------
    // Status Constants
    // --------------------
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_COMPLETED = 'COMPLETED';

    // --------------------
    // Properties
    // --------------------
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: "Service name cannot be blank.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Service name cannot exceed {{ limit }} characters."
    )]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: "Description cannot exceed {{ limit }} characters."
    )]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotNull(message: "Price is required.")]
    #[Assert\PositiveOrZero(message: "Price must be zero or positive.")]
    private ?string $price = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull(message: "Duration is required.")]
    #[Assert\Positive(message: "Duration must be positive.")]
    private ?int $durationInHours = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: "Status cannot be blank.", groups: ["edit"])]
    #[Assert\Choice(
        choices: [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_COMPLETED],
        message: "Invalid status.",
        groups: ["edit"]
    )]
    private ?string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'integer', options: ["default" => 0])]
    #[Assert\NotNull(message: "Revisions must be set.")]
    #[Assert\PositiveOrZero(message: "Revisions must be zero or positive.")]
    private ?int $revisions = 0;

    #[ORM\OneToMany(mappedBy: 'service', targetEntity: OrderItem::class, orphanRemoval: true)]
    private Collection $orderItems;

    #[ORM\Column(type: 'datetime_immutable')]
    // No validation needed - set automatically by PrePersist lifecycle callback
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    // No validation needed - set automatically by PrePersist/PreUpdate lifecycle callbacks
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    // --------------------
    // Constructor
    // --------------------
    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
    }

    // --------------------
    // Getters & Setters
    // --------------------
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price !== null ? (float)$this->price : null;
    }

    public function setPrice(float $price): self
    {
        $this->price = (string)$price;
        return $this;
    }

    public function getDurationInHours(): ?int
    {
        return $this->durationInHours;
    }

    public function setDurationInHours(int $durationInHours): self
    {
        $this->durationInHours = $durationInHours;
        return $this;
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

    public function getRevisions(): ?int
    {
        return $this->revisions;
    }

    public function setRevisions(int $revisions): self
    {
        $this->revisions = $revisions;
        return $this;
    }

    /**
     * @return Collection|OrderItem[]
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    public function addOrderItem(OrderItem $orderItem): self
    {
        if (!$this->orderItems->contains($orderItem)) {
            $this->orderItems[] = $orderItem;
            $orderItem->setService($this);
        }

        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): self
    {
        if ($this->orderItems->removeElement($orderItem)) {
            if ($orderItem->getService() === $this) {
                $orderItem->setService(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    // --------------------
    // Lifecycle Callbacks
    // --------------------
    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // --------------------
    // Helper Methods
    // --------------------
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
