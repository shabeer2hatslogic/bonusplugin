<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Rule;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;

class CategoryRule {

    /**
     * @var array
     */
    protected $categoryIds;

    public function __construct(?array $categoryIds = [])
    {
        $this->categoryIds = $categoryIds;
    }

    /**
     * @param array $categoryIds
     */
    public function match(?array $categoryIds = null): bool
    {
        return $this->matchesOneOfCategory($categoryIds);
    }

    private function matchesOneOfCategory(array $categoryIds): bool
    {
        if (!is_array($this->categoryIds)) {
            return false;
        }

        return RuleComparison::uuids($categoryIds, $this->categoryIds, Rule::OPERATOR_EQ);
    }
}
