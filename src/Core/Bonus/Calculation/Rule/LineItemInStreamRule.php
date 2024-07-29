<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Rule;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;

class LineItemInStreamRule {

    /**
     * @var array
     */
    protected $productGroupIds;

    public function __construct(?array $productGroupIds = [])
    {
        $this->productGroupIds = $productGroupIds;
    }

    public function match(array $productGroupIds): bool
    {
        return $this->matchesOneOfProductStream($productGroupIds);
    }

    private function matchesOneOfProductStream(array $productGroupIds): bool
    {
        if (!is_array($this->productGroupIds)) {
            return false;
        }

        return RuleComparison::uuids($productGroupIds, $this->productGroupIds, Rule::OPERATOR_EQ);
    }
}
