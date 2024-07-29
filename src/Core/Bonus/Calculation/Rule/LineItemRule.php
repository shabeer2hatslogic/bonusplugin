<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;

class LineItemRule {
    /**
     * @var string[]
     */
    protected $identifiers;

    public function __construct(?array $identifiers = null)
    {
        $this->identifiers = $identifiers;
    }

    /**
     * @param LineItem $lineItem
     */
    public function match(LineItem $lineItem): bool
    {
        if (!is_array($this->identifiers)) {
            return false;
        }

        return RuleComparison::stringArray($lineItem->getReferencedId(), array_map('strtolower', $this->identifiers), Rule::OPERATOR_EQ);
    }
}
