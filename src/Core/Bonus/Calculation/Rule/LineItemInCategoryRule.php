<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;

class LineItemInCategoryRule {
    /**
     * @var array
     */
    protected $categoryIds;

    public function __construct(?array $categoryIds = [])
    {
        $this->categoryIds = $categoryIds;
    }

    /**
     * @param LineItem $lineItem
     */
    public function match(LineItem $lineItem): bool
    {
        return $this->matchesOneOfCategory($lineItem);
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws PayloadKeyNotFoundException
     */
    private function matchesOneOfCategory(LineItem $lineItem): bool
    {
        if (!is_array($this->categoryIds)) {
            return false;
        }

        $categoryIds = (array) $lineItem->getPayloadValue('categoryIds');
        return RuleComparison::uuids($categoryIds, $this->categoryIds, Rule::OPERATOR_EQ);
    }
}
