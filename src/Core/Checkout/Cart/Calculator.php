<?php

namespace CustomBonusSystem\Core\Checkout\Cart;

use CustomBonusSystem\Core\Bonus\Calculation\CalculationService;
use CustomBonusSystem\Core\Bonus\ConfigService;
use CustomBonusSystem\Core\Entity\Bonus\BonusProductEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Calculator
{
    private readonly ConfigService $configService;

    public function __construct(private readonly CalculationService $calculationService, ConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function calculatePointsForProduct(BonusProductEntity $bonusProduct, CalculatedPrice $price, SalesChannelContext $salesChannelContext, int $quantity = 1)
    {
        $config = $this->configService->getConfig($salesChannelContext);

        switch($bonusProduct->getType()) {
            case 0:
                $redeemFactor = 0;
                if ($config->getBonusSystemConversionFactorRedeem()  && !$config->isDisallowRedeemPoints()) {
                    $redeemFactor = $this->calculationService->getConversionFactorRedeem($salesChannelContext->getCustomer(), $config->getBonusSystemConversionFactorRedeem());
                }
                if ($redeemFactor === 0) {
                    throw new \RuntimeException('the redeem factor cannot be 0');
                }
                // need ceil so that there are no comma bonus points
                $value = ceil($this->calculationService->calculateBonusPointsForAmount($price->getTotalPrice(), $redeemFactor, $salesChannelContext, true));
                break;
            case 1:
                $value = $bonusProduct->getValue() * $quantity;
                break;
            default:
                throw new \RuntimeException('no type given');
        }

        return $value;
    }

    /**
     * Calculate max order quantity for bonus product
     *
     * @param float $bonusProductPoints
     * @param int $currentBonusSumForCustomer
     * @return float
     */
    public function calculateMaxOrderQuantityForBonusProduct(float $bonusProductPoints, int $currentBonusSumForCustomer): float
    {
        return floor($currentBonusSumForCustomer / $bonusProductPoints);
    }
}
