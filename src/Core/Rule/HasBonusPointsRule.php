<?php declare(strict_types=1);


namespace CustomBonusSystem\Core\Rule;

use CustomBonusSystem\Core\Entity\Bonus\BonusUserPointEntity;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

class HasBonusPointsRule extends Rule
{
    public function __construct(protected bool $hasPoints = true)
    {
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

        return $this->hasPoints === $extension->hasPoints();
    }

    public function getConstraints(): array
    {
        return [
            'hasPoints' => [new NotNull(), new Type('bool')],
        ];
    }

    public function getName(): string
    {
        return 'customer_has_bonus_points';
    }
}
