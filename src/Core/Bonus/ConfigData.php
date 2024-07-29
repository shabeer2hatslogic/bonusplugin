<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus;

use Shopware\Core\Framework\Struct\Struct;

class ConfigData extends Struct
{
    final const POINT_ACTIVATION_ORDER_PAID_ID = 1;
    final const POINT_ACTIVATION_ORDER_COMPLETED_ID = 2;
    final const POINT_ACTIVATION_IMMEDIATELY_AFTER_ORDERING_ID = 3;
    final const POINT_ACTIVATION_ORDER_SHIPPED_ID = 4;

    final const POINT_ACTIVATION_CONDITION_OR = 0;
    final const POINT_ACTIVATION_CONDITION_AND = 1;

    final const BASKET_AMOUNT_REDEEM_RESTRICTION_MIN_ORDER_VALUE = 0;
    final const BASKET_AMOUNT_REDEEM_RESTRICTION_MAX_VALUE = 1;

    /**
     * @var bool
     */
    protected $useBonusSystem;

    /**
     * @var float
     */
    protected $bonusSystemConversionFactorCollect;

    /**
     * @var int
     */
    protected $collectPointsRound;

    /**
     * @var bool
     */
    protected $gainPointsForBackendOrder;

    /**
     * @var bool
     */
    protected $collectPointsWithoutShippingCosts;

    /**
     * @var float
     */
    protected $bonusSystemConversionFactorRedeem;

    /**
     * @var bool
     */
    protected bool $redeemPointsAutomatically;

    /**
     * @var bool
     */
    protected bool $disableVouchersWhenPointsAreInBasket;

    /**
     * @var int
     */
    protected $basketAmountRedeemRestriction;

    /**
     * @var float
     */
    protected $basketAmountRedeemRestrictionValue;

    /**
     * @var bool
     */
    protected $disallowRedeemPoints;

    /**
     * @var bool
     */
    protected $showPointsInHeader;

    /**
     * @var array
     */
    protected $disallowCustomerGroups;

    /**
     * @var int
     */
    protected $pointActivationType;

    /**
     * @var int
     */
    protected $pointActivationCondition = self::POINT_ACTIVATION_CONDITION_OR;

    /**
     * @var int
     */
    protected $pointActivationAfterDays;

    /**
     * @var bool
     */
    protected $removePointsOnOrderCanceled;

    /**
     * @var int
     */
    protected $expiryDays;

    /**
     * @var bool
     */
    protected bool $automaticEMailPointExpiration;

    /**
     * @var bool
     */
    protected bool $customerCanUnsubscribeAutomaticEMailPointExpiration;

    /**
     * @var int
     */
    protected int $numberDaysBeforeAutomaticEMailPointExpiration;

    /**
     * @return bool
     */
    public function isUseBonusSystem(): bool
    {
        return $this->useBonusSystem;
    }

    public function setUseBonusSystem(bool $useBonusSystem): void
    {
        $this->useBonusSystem = $useBonusSystem;
    }

    public function getBonusSystemConversionFactorCollect(): float
    {
        if ($this->bonusSystemConversionFactorCollect === 0.0) {
            return 0;
        }
        return $this->bonusSystemConversionFactorCollect;
    }

    public function setBonusSystemConversionFactorCollect(float $bonusSystemConversionFactorCollect): void
    {
        $this->bonusSystemConversionFactorCollect = $bonusSystemConversionFactorCollect;
    }

    public function isCollectPointsWithoutShippingCosts(): bool
    {
        if (!$this->collectPointsWithoutShippingCosts) {
            return false;
        }
        return $this->collectPointsWithoutShippingCosts;
    }

    public function setCollectPointsWithoutShippingCosts(bool $collectPointsWithoutShippingCosts): void
    {
        $this->collectPointsWithoutShippingCosts = $collectPointsWithoutShippingCosts;
    }

    public function getBonusSystemConversionFactorRedeem(): float
    {
        if ($this->bonusSystemConversionFactorRedeem === 0.0) {
            return 0;
        }
        return $this->bonusSystemConversionFactorRedeem;
    }

    public function setBonusSystemConversionFactorRedeem(float $bonusSystemConversionFactorRedeem): void
    {
        $this->bonusSystemConversionFactorRedeem = $bonusSystemConversionFactorRedeem;
    }

    public function isRedeemPointsAutomatically(): bool
    {
        return $this->redeemPointsAutomatically;
    }

    public function setRedeemPointsAutomatically(bool $redeemPointsAutomatically): void
    {
        $this->redeemPointsAutomatically = $redeemPointsAutomatically;
    }

    public function isDisableVouchersWhenPointsAreInBasket(): bool
    {
        return $this->disableVouchersWhenPointsAreInBasket;
    }

    public function setDisableVouchersWhenPointsAreInBasket(bool $disableVouchersWhenPointsAreInBasket): void
    {
        $this->disableVouchersWhenPointsAreInBasket = $disableVouchersWhenPointsAreInBasket;
    }

    public function getCollectPointsRound(): int
    {
        return $this->collectPointsRound;
    }

    public function setCollectPointsRound(int $collectPointsRound): void
    {
        $this->collectPointsRound = $collectPointsRound;
    }

    public function isGainPointsForBackendOrder(): bool
    {
        return $this->gainPointsForBackendOrder;
    }
    
    public function setGainPointsForBackendOrder(bool $gainPointsForBackendOrder): void
    {
        $this->gainPointsForBackendOrder = $gainPointsForBackendOrder;
    }

    public function getBasketAmountRedeemRestriction(): int
    {
        if ($this->basketAmountRedeemRestriction === 0) {
            return 0;
        }
        return (int) $this->basketAmountRedeemRestriction;
    }

    public function setBasketAmountRedeemRestriction(int $basketAmountRedeemRestriction): void
    {
        $this->basketAmountRedeemRestriction = (int) $basketAmountRedeemRestriction;
    }

    public function getBasketAmountRedeemRestrictionValue(): float
    {
        if ($this->basketAmountRedeemRestrictionValue === 0.0) {
            return 0;
        }
        return (float) $this->basketAmountRedeemRestrictionValue;
    }

    public function setBasketAmountRedeemRestrictionValue(float $basketAmountRedeemRestrictionValue): void
    {
        $this->basketAmountRedeemRestrictionValue = (float) $basketAmountRedeemRestrictionValue;
    }

    public function isDisallowRedeemPoints(): bool
    {
        return (bool) $this->disallowRedeemPoints;
    }

    public function setDisallowRedeemPoints(bool $disallowRedeemPoints): void
    {
        $this->disallowRedeemPoints = $disallowRedeemPoints;
    }

    public function isShowPointsInHeader(): bool
    {
        return $this->showPointsInHeader;
    }

    public function setShowPointsInHeader(bool $showPointsInHeader): void
    {
        $this->showPointsInHeader = $showPointsInHeader;
    }

    public function getDisallowCustomerGroups(): array
    {
        return $this->disallowCustomerGroups;
    }

    public function setDisallowCustomerGroups(array $disallowCustomerGroups): void
    {
        $this->disallowCustomerGroups = $disallowCustomerGroups;
    }

    public function getPointActivationType(): int
    {
        if ($this->pointActivationType === 0) {
            return 0;
        }
        return (int) $this->pointActivationType;
    }

    public function setPointActivationType(int $pointActivationType): void
    {
        $this->pointActivationType = (int) $pointActivationType;
    }

    public function isPointActivationType(int $type): bool
    {
        return (int)$this->pointActivationType === $type;
    }

    public function getPointActivationCondition(): int
    {
        return $this->pointActivationCondition;
    }

    public function setPointActivationCondition(int $pointActivationCondition): void
    {
        $this->pointActivationCondition = $pointActivationCondition;
    }

    public function isPointActivationConditionOr(): bool
    {
        return $this->pointActivationCondition === self::POINT_ACTIVATION_CONDITION_OR;
    }

    public function isPointActivationConditionAnd(): bool
    {
        return $this->pointActivationCondition === self::POINT_ACTIVATION_CONDITION_AND;
    }

    public function isRemovePointsOnOrderCanceled(): bool
    {
        return $this->removePointsOnOrderCanceled;
    }

    public function setRemovePointsOnOrderCanceled(bool $removePointsOnOrderCanceled): void
    {
        $this->removePointsOnOrderCanceled = $removePointsOnOrderCanceled;
    }

    public function getPointActivationAfterDays(): int
    {
        if ($this->pointActivationAfterDays === 0) {
            return 0;
        }
        return (int) $this->pointActivationAfterDays;
    }

    public function setPointActivationAfterDays(int $pointActivationAfterDays): void
    {
        $this->pointActivationAfterDays = (int) $pointActivationAfterDays;
    }

    public function getExpiryDays(): ?int
    {
        return $this->expiryDays;
    }

    public function setExpiryDays(int $expiryDays): void
    {
        $this->expiryDays = $expiryDays;
    }

    public function isAutomaticEMailPointExpiration(): bool
    {
        return $this->automaticEMailPointExpiration;
    }

    public function setAutomaticEMailPointExpiration(bool $automaticEMailPointExpiration): void
    {
        $this->automaticEMailPointExpiration = $automaticEMailPointExpiration;
    }

    public function isCustomerCanUnsubscribeAutomaticEMailPointExpiration(): bool
    {
        return $this->customerCanUnsubscribeAutomaticEMailPointExpiration;
    }

    public function setCustomerCanUnsubscribeAutomaticEMailPointExpiration(bool $customerCanUnsubscribeAutomaticEMailPointExpiration): void
    {
        $this->customerCanUnsubscribeAutomaticEMailPointExpiration = $customerCanUnsubscribeAutomaticEMailPointExpiration;
    }

    public function getNumberDaysBeforeAutomaticEMailPointExpiration(): int
    {
        return $this->numberDaysBeforeAutomaticEMailPointExpiration;
    }

    public function setNumberDaysBeforeAutomaticEMailPointExpiration(int $numberDaysBeforeAutomaticEMailPointExpiration): void
    {
        $this->numberDaysBeforeAutomaticEMailPointExpiration = $numberDaysBeforeAutomaticEMailPointExpiration;
    }
}
