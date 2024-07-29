import '../core/component/custom-bonus-system-has-points';
import '../core/component/custom-bonus-system-customer-points';

const { merge } = Shopware.Utils.object;

Shopware.Application.addServiceProviderDecorator('ruleConditionDataProviderService', (ruleConditionService) => {
    ruleConditionService.addCondition('customer_has_bonus_points', {
        component: 'custom-bonus-system-has-points',
        label: 'custom-bonus-system.customRule.customerHasBonusPoints.label',
        scopes: ['global']
    });
    ruleConditionService.addCondition('customer_bonus_points', {
        component: 'custom-bonus-system-customer-points',
        label: 'custom-bonus-system.customRule.customerBonusPoints.label',
        scopes: ['global']
    });

    return ruleConditionService;
});
