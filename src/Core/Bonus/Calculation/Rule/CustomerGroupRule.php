<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Rule;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;

class CustomerGroupRule {
    /**
     * @var string[]
     */
    protected $customerGroupIds;

    public function __construct(?array $customerGroupIds = null)
    {
        $this->customerGroupIds = $customerGroupIds;
    }

    /**
     * @param $customerGroupId
     */
    public function match($customerGroupId): bool
    {
        if (!is_array($this->customerGroupIds)) {
            return false;
        }

        return RuleComparison::stringArray($customerGroupId, array_map('strtolower', $this->customerGroupIds), Rule::OPERATOR_EQ);
    }
}
