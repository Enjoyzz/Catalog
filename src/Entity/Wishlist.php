<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Entity;

use Doctrine\ORM\Mapping as ORM;
use EnjoysCMS\Core\Users\Entity\User;
use EnjoysCMS\Module\Catalog\Repository\WishlistRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: WishlistRepository::class)]
#[ORM\Table(name: 'catalog_wishlist')]
#[ORM\UniqueConstraint(name: 'user_product', columns: ['user_id', 'product_id'])]
class Wishlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private User $user;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;


    #[ORM\ManyToOne(targetEntity: Product::class)]
    private Product $product;

    #[ORM\Column(name: 'product_options', type: 'json')]
    private array $productOptions = [];

    public function __construct(string|UuidInterface|null $id = null)
    {
        $this->id = $this->resolveId($id);
    }


    public function setId(string|UuidInterface|null $id): void
    {
        $this->id = $this->resolveId($id);
    }

    public function resolveId(string|UuidInterface|null $id): string
    {
        if ($id === null) {
            $id = Uuid::uuid4();
        }
        return (string)$id;
    }

    public function getId(): string
    {
        return $this->id;
    }


    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }


    public function getProductOptions(): array
    {
        return $this->productOptions;
    }

    public function setProductOptions(array $productOptions): void
    {
        $this->productOptions = $productOptions;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }


}
