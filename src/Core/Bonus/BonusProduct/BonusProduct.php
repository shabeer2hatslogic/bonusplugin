<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\BonusProduct;

use Shopware\Core\Framework\Struct\Struct;

class BonusProduct extends Struct
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var bool
     */
    protected $onlyBuyableWithPoints;

    /**
     * @var float|null
     */
    protected $value;

    /**
     * @var string
     */
    protected $productId;

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
     * @var int|null
     */
    protected $maxOrderQuantity;

    public function __construct($bonusProduct)
    {

        $this->setId($bonusProduct['id']);
        $this->setActive((int) $bonusProduct['active'] == 1);
        $this->setProductId($bonusProduct['product_id']);
        $this->setOnlyBuyableWithPoints((int) $bonusProduct['only_buyable_with_points'] == 1);
        $this->setValue((float) $bonusProduct['value']);
        $this->setMaxOrderQuantity($bonusProduct['max_order_quantity']);
        // type
        // value


        if ($bonusProduct['valid_from']) {
            $date = new \DateTime($bonusProduct['valid_from']);
            $this->setValidFrom($date);
        }

        if ($bonusProduct['valid_until']) {
            $date = new \DateTime($bonusProduct['valid_until']);
            $this->setValidUntil($date);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function isOnlyBuyableWithPoints(): bool
    {
        return $this->onlyBuyableWithPoints;
    }

    public function setOnlyBuyableWithPoints(bool $onlyBuyableWithPoints): void
    {
        $this->onlyBuyableWithPoints = $onlyBuyableWithPoints;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(?float $value): void
    {
        $this->value = $value;
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

    public function getMaxOrderQuantity(): ?int
    {
        return $this->maxOrderQuantity;
    }

    public function setMaxOrderQuantity(?int $maxOrderQuantity): void
    {
        $this->maxOrderQuantity = $maxOrderQuantity;
    }
}
