<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Rule;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;

class ProductRule {
    /**
     * @var string[]
     */
    protected $identifiers;

    public function __construct(?array $identifiers = null)
    {
        $this->identifiers = $identifiers;
    }

    public function match(?ProductEntity $product): bool
    {
        if (!is_array($this->identifiers)) {
            return false;
        }

        return RuleComparison::stringArray($product->getId(), array_map('strtolower', $this->identifiers), Rule::OPERATOR_EQ);
    }
}
