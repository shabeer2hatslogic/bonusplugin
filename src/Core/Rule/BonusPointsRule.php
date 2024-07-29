<?php declare(strict_types=1);


namespace CustomBonusSystem\Core\Rule;

use CustomBonusSystem\Core\Entity\Bonus\BonusUserPointEntity;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

class BonusPointsRule extends Rule
{
    protected int $count;

    public function __construct(protected string $operator = self:: OPERATOR_EQ, ?int $count = null)
    {
        $this->count = (int) $count;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getSalesChannelContext()->getCustomer()) {
            return false;
        }

        /** @var BonusUserPointEntity $extension */
        if (!$extension = $customer->getExtension('customBonusSystemUserPoint')) {
            return false;
        }

        return RuleComparison::numeric($extension->getPoints(), $this->count, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'count' => RuleConstraints::int(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }

    public function getName(): string
    {
        return 'customer_bonus_points';
    }
}
