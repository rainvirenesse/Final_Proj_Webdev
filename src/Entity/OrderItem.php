<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use ApiPlatform\Metadata\ApiResource;

#[ApiResource]

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Many OrderItems belong to one CustomerOrder
    #[ORM\ManyToOne(targetEntity: CustomerOrder::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Order must be specified.")]
    private ?CustomerOrder $order = null;

    // Many OrderItems belong to one Service (nullable - can be product or service)
    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Service $service = null;

    // Many OrderItems belong to one Product (nullable - can be product or service)
    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Product $product = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: "Item name cannot be blank.")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Item name cannot exceed {{ limit }} characters."
    )]
    private ?string $serviceName = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotNull(message: "Price is required.")]
    #[Assert\PositiveOrZero(message: "Price must be zero or positive.")]
    private ?string $price = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull(message: "Quantity is required.")]
    #[Assert\Positive(message: "Quantity must be positive.")]
    private ?int $quantity = 1;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull(message: "Revisions field is required.")]
    #[Assert\PositiveOrZero(message: "Revisions must be zero or positive.")]
    private ?int $revisions = 0;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: "Status cannot be blank.")]
    #[Assert\Choice(
        choices: ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'],
        message: "Invalid status."
    )]
    private ?string $status = 'PENDING';

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull(message: "Created at timestamp is required.")]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull(message: "Updated at timestamp is required.")]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Assert\Type(\DateTimeInterface::class)]
    private ?\DateTimeInterface $deliveryDate = null;

    // --------------------
    // Constructor
    // --------------------
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // --------------------
    // Lifecycle Callbacks
    // --------------------
    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // --------------------
    // Getters & Setters
    // --------------------
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?CustomerOrder
    {
        return $this->order;
    }

    public function setOrder(?CustomerOrder $order): self
    {
        $this->order = $order;
        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): self
    {
        $this->service = $service;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    public function setServiceName(string $serviceName): self
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getDeliveryDate(): ?\DateTimeInterface
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(?\DateTimeInterface $deliveryDate): self
    {
        // Validate that delivery date is not before order date
        if ($deliveryDate !== null && $this->order !== null && $this->order->getOrderedAt() !== null) {
            $orderDate = $this->order->getOrderedAt();
            // Convert DateTimeImmutable to DateTime for comparison if needed
            if ($orderDate instanceof \DateTimeImmutable) {
                $orderDateTime = new \DateTime($orderDate->format('Y-m-d H:i:s'));
            } else {
                $orderDateTime = $orderDate;
            }
            
            if ($deliveryDate < $orderDateTime) {
                throw new \InvalidArgumentException('Delivery date cannot be before the order date.');
            }
        }
        
        $this->deliveryDate = $deliveryDate;
        return $this;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        // Ensure either product or service is set
        if ($this->product === null && $this->service === null) {
            $context->buildViolation('Either a product or service must be selected for this order item.')
                ->atPath('product')
                ->addViolation();
        }
        
        // Ensure not both product and service are set
        if ($this->product !== null && $this->service !== null) {
            $context->buildViolation('An order item cannot have both a product and a service. Please select only one.')
                ->atPath('product')
                ->addViolation();
        }
    }
}
