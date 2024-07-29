<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Condition;

use Shopware\Core\Framework\Struct\Struct;

class Condition extends Struct
{
    /**
     * @var string
     */
    protected $id;

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
    protected $streamCondition;

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

    public function __construct($condition)
    {
        $this->setId($condition['id']);
        $this->setName($condition['name']);
        $this->setActive((int) $condition['active'] == 1);
        $this->setType((int) $condition['type']);
        $this->setSubType((int) $condition['sub_type']);
        $this->setFactor((float) $condition['factor']);

        if ($condition['valid_from']) {
            $date = new \DateTime($condition['valid_from']);
            $this->setValidFrom($date);
        }

        if ($condition['valid_until']) {
            $date = new \DateTime($condition['valid_until']);
            $this->setValidUntil($date);
        }

        if (array_key_exists('stream_condition', $condition) && $condition['stream_condition']) {
            $streamCondition = json_decode((string) $condition['stream_condition']);
            if (is_object($streamCondition)) {
                $this->setStreamCondition($this->toArray($streamCondition->streamIds));
            }
        }

        if ($condition['category_condition']) {
            $categoryCondition = json_decode((string) $condition['category_condition']);
            if (is_object($categoryCondition)) {
                $this->setCategoryCondition($this->toArray($categoryCondition->categoryIds));
            }
        }

        if ($condition['product_condition']) {
            $productCondition = json_decode((string) $condition['product_condition']);
            if (is_object($productCondition)) {
                $this->setProductCondition($this->toArray($productCondition->identifiers));
            }
        }

        if ($condition['customer_number_condition']) {
            $customerNumberCondition = json_decode((string) $condition['customer_number_condition']);
            if (is_object($customerNumberCondition)) {
                $this->setCustomerNumberCondition($this->toArray($customerNumberCondition->numbers));
            }
        }

        if ($condition['customer_group_condition']) {
            $customerGroupCondition = json_decode((string) $condition['customer_group_condition']);
            if (is_object($customerGroupCondition)) {
                $this->setCustomerGroupCondition($this->toArray($customerGroupCondition->customerGroupIds));
            }
        }
    }

    protected function toArray($sourceArray)
    {
        if (count($sourceArray) == 0) {
            return $sourceArray;
        }

        $destArray = [];

        foreach($sourceArray as $key => $value) {
            $destArray[$value] = $value;
        }

        return $destArray;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

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
}
