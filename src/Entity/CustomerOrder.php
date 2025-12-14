<?php

namespace App\Entity;

use App\Repository\CustomerOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomerOrderRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CustomerOrder
{
    // --------------------
    // Status Constants
    // --------------------
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const PAYMENT_UNPAID = 'UNPAID';
    public const PAYMENT_PAID = 'PAID';
    public const PAYMENT_PARTIALLY_PAID = 'PARTIALLY_PAID';

    // --------------------
    // Properties
    // --------------------
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Client must be selected.")]
    private ?User $client = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    // No validation constraints - order number is auto-generated
    private ?string $orderNumber = null;

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\NotBlank(message: "Order status is required.")]
    #[Assert\Choice(
        choices: [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED
        ],
        message: "Invalid order status. Must be PENDING, IN_PROGRESS, COMPLETED, or CANCELLED."
    )]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotNull(message: "Total price cannot be null.")]
    #[Assert\PositiveOrZero(message: "Total price must be zero or positive.")]
    private ?string $totalPrice = '0.00';

    #[ORM\Column(type: 'string', length: 20)]
    #[Assert\NotBlank(message: "Payment status is required.")]
    #[Assert\Choice(
        choices: [
            self::PAYMENT_UNPAID,
            self::PAYMENT_PAID,
            self::PAYMENT_PARTIALLY_PAID
        ],
        message: "Invalid payment status. Must be UNPAID, PAID, or PARTIALLY_PAID."
    )]
    private ?string $paymentStatus = self::PAYMENT_UNPAID;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull(message: "Order date is required.")]
    #[Assert\Type(\DateTimeImmutable::class)]
    #[Assert\GreaterThanOrEqual(
        value: "today",
        message: "Order date cannot be before the current date."
    )]
    private ?\DateTimeImmutable $orderedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Assert\Type(\DateTimeImmutable::class)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: "Notes cannot exceed {{ limit }} characters."
    )]
    private ?string $notes = null;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $orderItems;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    // Track original status for validation
    private ?string $originalStatus = null;

    // --------------------
    // Constructor
    // --------------------
    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
    }

    // --------------------
    // Lifecycle Callbacks
    // --------------------
    #[ORM\PostLoad]
    public function postLoad(): void
    {
        // Store original status when entity is loaded
        $this->originalStatus = $this->status;
    }

    // --------------------
    // Lifecycle Callbacks
    // --------------------
    #[ORM\PrePersist]
    public function prePersist(): void
    {
        if (!$this->orderedAt) {
            $this->orderedAt = new \DateTimeImmutable();
        }
        // Ensure orderedAt is not in the past
        $now = new \DateTimeImmutable();
        if ($this->orderedAt < $now->setTime(0, 0, 0)) {
            $this->orderedAt = $now;
        }
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        // Ensure orderedAt is not in the past on update
        if ($this->orderedAt !== null) {
            $now = new \DateTimeImmutable();
            if ($this->orderedAt < $now->setTime(0, 0, 0)) {
                throw new \InvalidArgumentException('Order date cannot be before the current date.');
            }
        }
    }

    // --------------------
    // Getters & Setters
    // --------------------
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): self
    {
        // Prevent assigning order to a disabled client
        if ($client !== null && $client->getStatus() !== User::STATUS_ACTIVE) {
            throw new \InvalidArgumentException('Cannot assign an order to a disabled client. Only active clients can receive orders.');
        }
        
        $this->client = $client;
        return $this;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): self
    {
        $this->orderNumber = $orderNumber;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        // Prevent changing status from COMPLETED to any other status
        if ($this->originalStatus === self::STATUS_COMPLETED && $status !== self::STATUS_COMPLETED) {
            throw new \InvalidArgumentException('Cannot change status from COMPLETED to another status. Completed orders cannot be modified.');
        }
        
        // Prevent setting status to COMPLETED if payment is not PAID
        if ($status === self::STATUS_COMPLETED && $this->paymentStatus !== self::PAYMENT_PAID) {
            throw new \InvalidArgumentException('Cannot complete an order that is not fully paid. Payment status must be PAID before completing an order.');
        }
        
        $this->status = $status;
        
        // Set completedAt when status changes to COMPLETED
        if ($status === self::STATUS_COMPLETED && $this->completedAt === null) {
            $this->completedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getOriginalStatus(): ?string
    {
        return $this->originalStatus;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function getTotalPriceFloat(): float
    {
        return (float) $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): self
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    public function getOrderedAt(): ?\DateTimeImmutable
    {
        return $this->orderedAt;
    }

    public function setOrderedAt(\DateTimeImmutable $orderedAt): self
    {
        $this->orderedAt = $orderedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        // Validate that completed date is not before order date
        if ($completedAt !== null && $this->orderedAt !== null) {
            if ($completedAt < $this->orderedAt) {
                throw new \InvalidArgumentException('Completed date cannot be before the order date.');
            }
        }
        
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
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
            $orderItem->setOrder($this);
        }
        return $this;
    }

    public function removeOrderItem(OrderItem $orderItem): self
    {
        if ($this->orderItems->removeElement($orderItem)) {
            if ($orderItem->getOrder() === $this) {
                $orderItem->setOrder(null);
            }
        }
        return $this;
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
}
