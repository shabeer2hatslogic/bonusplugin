<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;


class ConfigService {

    const CONFIG_DOMAIN = 'CustomBonusSystem.config';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService) {
        $this->systemConfigService = $systemConfigService;
    }

    protected function getConfigStruct($useBonusSystem, $salesChannelId)
    {
        return (new ConfigData())->assign([
            'useBonusSystem' => $useBonusSystem,
            'disallowRedeemPoints' => $this->systemConfigService->getBool('CustomBonusSystem.config.disallowRedeemPoints', $salesChannelId),
            'showPointsInHeader' => $this->systemConfigService->getBool('CustomBonusSystem.config.showPointsInHeader', $salesChannelId),
            'disallowCustomerGroups' => $this->systemConfigService->get('CustomBonusSystem.config.disallowCustomerGroups', $salesChannelId),
            'bonusSystemConversionFactorCollect' => $this->systemConfigService->getFloat('CustomBonusSystem.config.bonusSystemConversionFactorCollect', $salesChannelId),
            'collectPointsRound' => $this->systemConfigService->get('CustomBonusSystem.config.collectPointsRound', $salesChannelId),
            'gainPointsForBackendOrder' => $this->systemConfigService->getBool('CustomBonusSystem.config.gainPointsForBackendOrder', $salesChannelId),
            'collectPointsWithoutShippingCosts' => $this->systemConfigService->getBool('CustomBonusSystem.config.collectPointsWithoutShippingCosts', $salesChannelId),
            'bonusSystemConversionFactorRedeem' => $this->systemConfigService->getFloat('CustomBonusSystem.config.bonusSystemConversionFactorRedeem', $salesChannelId),
            'redeemPointsAutomatically' => $this->systemConfigService->getBool('CustomBonusSystem.config.redeemPointsAutomatically', $salesChannelId),
            'disableVouchersWhenPointsAreInBasket' => $this->systemConfigService->getBool('CustomBonusSystem.config.disableVouchersWhenPointsAreInBasket', $salesChannelId),
            'basketAmountRedeemRestriction' => $this->systemConfigService->get('CustomBonusSystem.config.basketAmountRedeemRestriction', $salesChannelId),
            'basketAmountRedeemRestrictionValue' => $this->systemConfigService->get('CustomBonusSystem.config.basketAmountRedeemRestrictionValue', $salesChannelId),
            'pointActivationType' => $this->systemConfigService->get('CustomBonusSystem.config.pointActivationType', $salesChannelId),
            'pointActivationCondition' => (int)$this->systemConfigService->get('CustomBonusSystem.config.pointActivationCondition', $salesChannelId),
            'pointActivationAfterDays' => $this->systemConfigService->get('CustomBonusSystem.config.pointActivationAfterDays', $salesChannelId),
            'removePointsOnOrderCanceled' => $this->systemConfigService->getBool('CustomBonusSystem.config.removePointsOnOrderCanceled', $salesChannelId),
            'expiryDays' => $this->systemConfigService->get('CustomBonusSystem.config.expiryDays', $salesChannelId),
            'automaticEMailPointExpiration' => $this->systemConfigService->getBool('CustomBonusSystem.config.automaticEMailPointExpiration', $salesChannelId),
            'customerCanUnsubscribeAutomaticEMailPointExpiration' => $this->systemConfigService->getBool('CustomBonusSystem.config.customerCanUnsubscribeAutomaticEMailPointExpiration', $salesChannelId),
            'numberDaysBeforeAutomaticEMailPointExpiration' => $this->systemConfigService->get('CustomBonusSystem.config.numberDaysBeforeAutomaticEMailPointExpiration', $salesChannelId),
        ]);
    }

    /**
     * @param SalesChannelContext $context
     * @return ConfigData
     */
    public function getConfig(SalesChannelContext $context)
    {
        $customerGroup = $context->getCurrentCustomerGroup();
        return $this->getConfigWithCustomerGroup($customerGroup->getId(), $context->getSalesChannelId());
    }

    /**
     * @param string $customerGroup
     * @return ConfigData
     */
    public function getConfigWithCustomerGroup(string $customerGroupId, string $salesChannelId) {
        $useBonusSystem = $this->systemConfigService->getBool('CustomBonusSystem.config.useBonusSystem', $salesChannelId);
        $disallowedCustomerGroups = $this->systemConfigService->get('CustomBonusSystem.config.disallowCustomerGroups', $salesChannelId);

        if ($disallowedCustomerGroups && is_array($disallowedCustomerGroups)) {
            if (in_array($customerGroupId, $disallowedCustomerGroups)) {
                $useBonusSystem = false;
            }
        }
        $configStruct = $this->getConfigStruct($useBonusSystem, $salesChannelId);
        return $configStruct;
    }

    /**
     * @param string $field
     * @param string|null $salesChannelId
     * @return bool|float|int|string|null
     */
    public function getValueFromPluginConfig(string $field, string $salesChannelId = null)
    {
        return $this->systemConfigService->get(self::CONFIG_DOMAIN . '.' . $field, $salesChannelId);
    }
}
