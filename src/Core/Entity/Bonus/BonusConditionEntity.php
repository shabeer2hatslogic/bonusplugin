<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Entity\Bonus;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BonusConditionEntity extends Entity
{
    use EntityIdTrait;

    final const TYPE_EXCLUDE_PRODUCTS = 1;
    final const TYPE_INDIVIDUAL_BONUS_FACTOR_FOR_CUSTOMER = 2;
    final const TYPE_INDIVIDUAL_BONUS_FACTOR_FOR_PRODUCT_OR_STREAM = 3;

    final const SUB_TYPE_CONVERSION_FACTOR_COLLECT = 1;
    final const SUB_TYPE_CONVERSION_FACTOR_REDEEM = 2;

    final const SUB_TYPE_EXCLUDE_FOR_COLLECT = 1;
    final const SUB_TYPE_EXCLUDE_FOR_REDEEM = 2;

    /**
     * @var string
     */
    protected $name;

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
     * @var int
     */
    protected $type;

    /**
     * @var int
     */
    protected $subType;

    /**
     * @var float|null
     */
    protected $factor;

    /**
     * @var string[]|null
     */
    protected $categoryCondition;

    /**
     * @var string[]|null
     */
    protected $productCondition;

    /**
     * @var string[]|null
     */
    protected $customerNumberCondition;

    /**
     * @var string[]|null
     */
    protected $customerGroupCondition;

    /**
     * @var string[]|null
     */
    protected $streamCondition;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function getSubType(): int
    {
        return $this->subType;
    }

    public function setSubType(int $subType): void
    {
        $this->subType = $subType;
    }

    public function getFactor(): ?float
    {
        return $this->factor;
    }

    public function setFactor(?float $factor): void
    {
        $this->factor = $factor;
    }

    /**
     * @return string[]|null
     */
    public function getCategoryCondition(): ?array
    {
        return $this->categoryCondition;
    }

    /**
     * @param string[]|null $categoryCondition
     */
    public function setCategoryCondition(?array $categoryCondition): void
    {
        $this->categoryCondition = $categoryCondition;
    }

    /**
     * @return string[]|null
     */
    public function getProductCondition(): ?array
    {
        return $this->productCondition;
    }

    /**
     * @param string[]|null $productCondition
     */
    public function setProductCondition(?array $productCondition): void
    {
        $this->productCondition = $productCondition;
    }

    /**
     * @return string[]|null
     */
    public function getCustomerNumberCondition(): ?array
    {
        return $this->customerNumberCondition;
    }

    /**
     * @param string[]|null $customerNumberCondition
     */
    public function setCustomerNumberCondition(?array $customerNumberCondition): void
    {
        $this->customerNumberCondition = $customerNumberCondition;
    }

    /**
     * @return string[]|null
     */
    public function getCustomerGroupCondition(): ?array
    {
        return $this->customerGroupCondition;
    }

    /**
     * @param string[]|null $customerGroupCondition
     */
    public function setCustomerGroupCondition(?array $customerGroupCondition): void
    {
        $this->customerGroupCondition = $customerGroupCondition;
    }

    /**
     * @return string[]|null
     */
    public function getStreamCondition(): ?array
    {
        return $this->streamCondition;
    }

    /**
     * @param string[]|null $streamCondition
     */
    public function setStreamCondition(?array $streamCondition): void
    {
        $this->streamCondition = $streamCondition;
    }
}
