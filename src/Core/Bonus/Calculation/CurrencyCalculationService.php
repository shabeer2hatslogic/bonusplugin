<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation;

use Shopware\Core\Defaults;
use Shopware\Core\System\Currency\CurrencyEntity;

class CurrencyCalculationService
{
    /**
     * Calculates the default price based on current currency
     * @param CurrencyEntity $currentCurrency
     * @param $price
     * @return float
     */
    public function calculateToDefaultPrice(CurrencyEntity $currentCurrency, $price)
    {
        if ($price == 0) {
            return $price;
        }

        $defaultCurrencyId = Defaults::CURRENCY;

        if ($defaultCurrencyId != $currentCurrency->getId()) {
            $price /= $currentCurrency->getFactor();
        }

        return $price;
    }

    /**
     * Calculates the currency price based on default currency
     * @param CurrencyEntity $currentCurrency
     * @param $price
     * @return float
     */
    public function calculateToCurrencyPrice(CurrencyEntity $currentCurrency, $price) {
        if ($price == 0) {
            return $price;
        }

        $defaultCurrencyId = Defaults::CURRENCY;

        if ($defaultCurrencyId != $currentCurrency->getId()) {
            $price *= $currentCurrency->getFactor();
        }

        return $price;
    }

    public function calculateConversionFactor(CurrencyEntity $currentCurrency, $bonusSystemConversionFactorRedeem, $factorFor1Amount, $oneAmount) {
        if ($oneAmount == 1) {
            return $bonusSystemConversionFactorRedeem;
        }

        $defaultCurrencyId = Defaults::CURRENCY;

        if ($defaultCurrencyId != $currentCurrency->getId()) {
            $bonusSystemConversionFactorRedeem = $factorFor1Amount / $currentCurrency->getFactor();
        }

        return $bonusSystemConversionFactorRedeem;
    }
}
