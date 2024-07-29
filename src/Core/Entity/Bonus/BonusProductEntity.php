<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Entity\Bonus;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BonusProductEntity extends Entity
{
    use EntityIdTrait;

    public const BUY_WITH_POINTS_ONLY_SESSION_KEY = 'custom-bonus-product';

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var ProductEntity|null
     */
    protected $product;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var \DateTimeInterface|null
     */
    protected $validFrom;

    /**
     * @var \DateTimeInterface|null
     */
    protected $validUntil;

    /**
     * @var bool
     */
    protected $onlyBuyableWithPoints;

    /**
     * @var int
     */
    protected $type;

    /**
     * @var float|null
     */
    protected $value;

    /**
     * @var int|null
     */
    protected $maxOrderQuantity;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * @return ProductEntity|null
     */
    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    /**
     * @param ProductEntity|null $product
     */
    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function isOnlyBuyableWithPoints(): bool
    {
        return $this->onlyBuyableWithPoints;
    }

    public function setOnlyBuyableWithPoints(bool $onlyBuyableWithPoints): void
    {
        $this->onlyBuyableWithPoints = $onlyBuyableWithPoints;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(?float $value): void
    {
        $this->value = $value;
    }

    public function getMaxOrderQuantity(): ?int
    {
        return $this->maxOrderQuantity;
    }

    public function setMaxOrderQuantity(?int $maxOrderQuantity): void
    {
        $this->maxOrderQuantity = $maxOrderQuantity;
    }
}
