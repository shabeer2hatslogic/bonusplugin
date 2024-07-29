<?php

declare(strict_types = 1);


namespace CustomBonusSystem\Core\Bonus\Calculation\Struct;

class CalculationStruct {
    protected $hasPoints;
    protected $getPoints;
    protected $oneAmount;
    protected $wantToRedeem;
    protected $wantToRedeemBasketDiscountType;
    protected $wantToRedeemBonusProductDiscountType;
    protected $pointsPossibleAmount;
    protected $availableBasketAmountForRedeemPoints;
    protected $factorFor1Amount;
    protected $maxRedeemPoints;
    protected $bonusSystemConversionFactorRedeem;
    protected $bonusSystemConversionFactorCurrencyRedeem;
    protected $basketAmountRedeemRestriction;
    protected $basketAmountRedeemRestrictionValue;

    /**
     * @return mixed
     */
    public function getHasPoints()
    {
        return $this->hasPoints;
    }

    public function setHasPoints(mixed $hasPoints): void
    {
        $this->hasPoints = $hasPoints;
    }

    /**
     * @return mixed
     */
    public function getGetPoints()
    {
        return $this->getPoints;
    }

    public function setGetPoints(mixed $getPoints): void
    {
        $this->getPoints = $getPoints;
    }

    /**
     * @return mixed
     */
    public function getOneAmount()
    {
        return $this->oneAmount;
    }

    public function setOneAmount(mixed $oneAmount): void
    {
        $this->oneAmount = $oneAmount;
    }

    /**
     * @return mixed
     */
    public function getWantToRedeem()
    {
        return $this->wantToRedeem;
    }

    public function setWantToRedeem(mixed $wantToRedeem): void
    {
        $this->wantToRedeem = $wantToRedeem;
    }

    /**
     * @return mixed
     */
    public function getWantToRedeemBasketDiscountType()
    {
        return $this->wantToRedeemBasketDiscountType;
    }

    public function setWantToRedeemBasketDiscountType(mixed $wantToRedeemBasketDiscountType): void
    {
        $this->wantToRedeemBasketDiscountType = $wantToRedeemBasketDiscountType;
    }

    /**
     * @return mixed
     */
    public function getWantToRedeemBonusProductDiscountType()
    {
        return $this->wantToRedeemBonusProductDiscountType;
    }

    public function setWantToRedeemBonusProductDiscountType(mixed $wantToRedeemBonusProductDiscountType): void
    {
        $this->wantToRedeemBonusProductDiscountType = $wantToRedeemBonusProductDiscountType;
    }

    /**
     * @return mixed
     */
    public function getPointsPossibleAmount()
    {
        return $this->pointsPossibleAmount;
    }

    public function setPointsPossibleAmount(mixed $pointsPossibleAmount): void
    {
        $this->pointsPossibleAmount = $pointsPossibleAmount;
    }

    /**
     * @return mixed
     */
    public function getAvailableBasketAmountForRedeemPoints()
    {
        return $this->availableBasketAmountForRedeemPoints;
    }

    public function setAvailableBasketAmountForRedeemPoints(mixed $availableBasketAmountForRedeemPoints): void
    {
        $this->availableBasketAmountForRedeemPoints = $availableBasketAmountForRedeemPoints;
    }

    /**
     * @return mixed
     */
    public function getFactorFor1Amount()
    {
        return $this->factorFor1Amount;
    }

    public function setFactorFor1Amount(mixed $factorFor1Amount): void
    {
        $this->factorFor1Amount = $factorFor1Amount;
    }

    /**
     * @return mixed
     */
    public function getMaxRedeemPoints()
    {
        return $this->maxRedeemPoints;
    }

    public function setMaxRedeemPoints(mixed $maxRedeemPoints): void
    {
        $this->maxRedeemPoints = $maxRedeemPoints;
    }

    /**
     * @return mixed
     */
    public function getBonusSystemConversionFactorRedeem()
    {
        return $this->bonusSystemConversionFactorRedeem;
    }

    public function setBonusSystemConversionFactorRedeem(mixed $bonusSystemConversionFactorRedeem): void
    {
        $this->bonusSystemConversionFactorRedeem = $bonusSystemConversionFactorRedeem;
    }

    /**
     * @return mixed
     */
    public function getBonusSystemConversionFactorCurrencyRedeem()
    {
        return $this->bonusSystemConversionFactorCurrencyRedeem;
    }

    public function setBonusSystemConversionFactorCurrencyRedeem(mixed $bonusSystemConversionFactorCurrencyRedeem): void
    {
        $this->bonusSystemConversionFactorCurrencyRedeem = $bonusSystemConversionFactorCurrencyRedeem;
    }

    /**
     * @return mixed
     */
    public function getBasketAmountRedeemRestriction()
    {
        return $this->basketAmountRedeemRestriction;
    }

    public function setBasketAmountRedeemRestriction(mixed $basketAmountRedeemRestriction): void
    {
        $this->basketAmountRedeemRestriction = $basketAmountRedeemRestriction;
    }

    /**
     * @return mixed
     */
    public function getBasketAmountRedeemRestrictionValue()
    {
        return $this->basketAmountRedeemRestrictionValue;
    }

    public function setBasketAmountRedeemRestrictionValue(mixed $basketAmountRedeemRestrictionValue): void
    {
        $this->basketAmountRedeemRestrictionValue = $basketAmountRedeemRestrictionValue;
    }
}