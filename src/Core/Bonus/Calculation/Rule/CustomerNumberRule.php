<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Rule;

use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;

class CustomerNumberRule {
    /**
     * @var string[]
     */
    protected $numbers;

    public function __construct(?array $numbers = null)
    {
        $this->numbers = $numbers;
    }

    /**
     * @param $customerNumber
     */
    public function match($customerNumber): bool
    {
        if (!is_array($this->numbers)) {
            return false;
        }

        return RuleComparison::stringArray($customerNumber, array_map('strtolower', $this->numbers), Rule::OPERATOR_EQ);
    }
}
