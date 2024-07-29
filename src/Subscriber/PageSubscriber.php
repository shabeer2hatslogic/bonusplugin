<?php

declare(strict_types = 1);


namespace CustomBonusSystem\Subscriber;

use CustomBonusSystem\Core\Bonus\BonusHelper;
use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Bonus\Calculation\CalculationService;
use CustomBonusSystem\Core\Bonus\Calculation\Condition\ConditionService;
use CustomBonusSystem\Core\Bonus\ConfigService;
use CustomBonusSystem\Core\Bonus\ExpiryService;
use CustomBonusSystem\Core\Checkout\Bonus\BonusProcessor;
use CustomBonusSystem\Core\Entity\Bonus\BonusBookingCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedEvent;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class PageSubscriber implements EventSubscriberInterface
{
    private readonly ConfigService $configService;
    private readonly CartService $cartService;
    private readonly TranslatorInterface $translator;
    private readonly RequestStack $requestStack;
    private readonly ConditionService $conditionService;

    public function __construct(
        ConfigService $configService,
        private readonly BonusService $bonusService,
        private readonly CalculationService $calculationService,
        CartService $cartService,
        private readonly BonusProcessor $bonusProcessor,
        private readonly ExpiryService $expiryService,
        TranslatorInterface $translator,
        RequestStack $requestStack,
        ConditionService $conditionService
    ) {
        $this->configService = $configService;
        $this->cartService = $cartService;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
        $this->conditionService = $conditionService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoadedEvent',
            HeaderPageletLoadedEvent::class => 'onHeaderPageletLoadedEvent',
            CheckoutCartPageLoadedEvent::class => 'onCheckoutPageLoadedEvent',
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutPageLoadedEvent',
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinishPageLoadedEvent',
            OffcanvasCartPageLoadedEvent::class => 'onCheckoutPageLoadedEvent',
            CustomerLoginEvent::class => 'onCustomerLoginEvent',
            CheckoutRegisterPageLoadedEvent::class => 'onCheckoutRegisterPageLoadedEvent'
        ];
    }

    public function onCheckoutFinishPageLoadedEvent(CheckoutFinishPageLoadedEvent $event)
    {
        $points = $this->bonusService->getPointsForOrder($event->getPage()->getOrder(), $event->getSalesChannelContext()->getContext());
        if (!$points instanceof BonusBookingCollection) {
            return;
        }

        $calculatedPoints = BonusHelper::getCountForCollectionPoints($points);

        if ($calculatedPoints['redeemed'] === 0 && $calculatedPoints['get'] === 0) {
            return;
        }

        $data = new ArrayStruct( [ 'useBonusSystem' => true, ]);

        $event->getPage()->assign(
            [
                'customBonusSystemPoints' =>
                    [
                        'wantToRedeem' => $calculatedPoints['redeemed'],
                        'get' => $calculatedPoints['get']
                    ]
            ]
        );
        $event->getPage()->addExtension('customBonusSystem', $data);
    }

    /**
     * Show points get on product detailpage for ordering product
     * @param ProductPageLoadedEvent $event
     */
    public function onProductPageLoadedEvent($event): void
    {
        $context = $event->getContext();

        if (!($event instanceof ProductPageLoadedEvent)) {
            return;
        }

        $bonusSettings = $this->configService->getConfig($event->getSalesChannelContext());
        $settingVars = $bonusSettings->getVars();

        if (!$settingVars['useBonusSystem']) {
            return;
        }

        if (!$settingVars['bonusSystemConversionFactorCollect']) {
            return;
        }

        $product = $event->getPage()->getProduct();
        $quantity = $product->getMinPurchase() ?: 1;
        $points = 0;
        $isProductExcluded = $this->conditionService->conditionsExcludePointsForProductMatch($product);

        if (!$isProductExcluded) {
            $bonusSystemConversionFactor = $settingVars['bonusSystemConversionFactorCollect'];
            if ($bonusSystemConversionFactor) {
                $bonusSystemConversionFactor = $this->calculationService->getConversionFactorCollect($event->getSalesChannelContext()->getCustomer(), $bonusSystemConversionFactor, $product);
            }

            $calculatedPrice = $this->calculationService->calculateProductPriceByQuantity(
                $product,
                $quantity,
                $event->getSalesChannelContext()
            );

            $points = $this->calculationService->calculateBonusPointsForAmount(
                $calculatedPrice->getTotalPrice(),
                $bonusSystemConversionFactor,
                $event->getSalesChannelContext(),
                true,
                $settingVars['collectPointsRound']
            );
        }

        if (!$isProductExcluded) {
            $event->getPage()->addExtension('customBonusSystem', $bonusSettings);
            $event->getPage()->assign(['customBonusSystemPoints' =>
                [
                    'get' => $points,
                ]
            ]);
        }
    }

    /**
     * Load Wishlist settings for wishlist icon in header
     * @param HeaderPageletLoadedEvent $event
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function onHeaderPageletLoadedEvent($event): void
    {
        $context = $event->getSalesChannelContext();
        if (!$context->getCustomer()) {
            return;
        }

        if (!($event instanceof HeaderPageletLoadedEvent)) {
            return;
        }

        $bonusSettings = $this->configService->getConfig($event->getSalesChannelContext());
        $settingVars = $bonusSettings->getVars();

        if (!$settingVars['useBonusSystem']) {
            return;
        }

        $event->getPagelet()->addExtension('customBonusSystem', $bonusSettings);
        $event->getPagelet()->assign(['customBonusSystemPoints' => $this->bonusService->getBonusSumForUser($context)]);
        $event->getPagelet()->assign(['customBonusSystemLoggedIn' => 1]);
    }

    public function onCheckoutPageLoadedEvent($event): void
    {
        $context = $event->getSalesChannelContext();
        $customer = $context->getCustomer();
        if ($event instanceof OffcanvasCartPageLoadedEvent ||
            $event instanceof CheckoutCartPageLoadedEvent ||
            $event instanceof CheckoutConfirmPageLoadedEvent
        ) {
            $bonusSettings = $this->configService->getConfig($event->getSalesChannelContext());
        } else {
            return;
        }

        if (!$bonusSettings) {
            return;
        }

        $settingVars = $bonusSettings->getVars();
        if (!$settingVars['useBonusSystem']) {
            return;
        }

        if ($customer && $customer->getGuest()) {
            return;
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);
        if ($cart->getLineItems()->count() === 0) {
            return;
        }

        $calculationStruct = $this->bonusService->getCalculationStruct($settingVars, $cart, $context);
        $canRedeemPoints = $this->bonusProcessor->isPointRedeemOk(
            $calculationStruct->getWantToRedeem(),
            $cart,
            $calculationStruct->getBonusSystemConversionFactorCurrencyRedeem(),
            $calculationStruct->getBasketAmountRedeemRestriction(),
            $calculationStruct->getBasketAmountRedeemRestrictionValue(),
            $this->bonusService->getBonusSumForUser($context),
            $context
        );

        if ($calculationStruct->getWantToRedeem() > 0 && !$canRedeemPoints) {

            // Points not allowed. Remove
            $this->bonusProcessor->removePointRedeemFromCart($cart);
            $cart = $this->cartService->recalculate($cart, $context);
            $event->getPage()->setCart($cart);
            $calculationStruct = $this->bonusService->getCalculationStruct($settingVars, $cart, $context);
        }

        $event->getPage()->assign(
            [
                'customBonusSystemPoints' =>
                    [
                        'has' => $calculationStruct->getHasPoints(),
                        'get' => $calculationStruct->getGetPoints(),
                        'oneAmount' => $calculationStruct->getOneAmount(),
                        'wantToRedeem' => $calculationStruct->getWantToRedeem(),
                        'wantToRedeemBasketDiscountType' => $calculationStruct->getWantToRedeemBasketDiscountType(),
                        'wantToRedeemBonusProductDiscountType' => $calculationStruct->getWantToRedeemBonusProductDiscountType(),
                        'pointsPossibleAmount' => $calculationStruct->getPointsPossibleAmount(),
                        'availableBasketAmountForRedeemPoints' => $calculationStruct->getAvailableBasketAmountForRedeemPoints(),
                        'factorFor1Amount' => $calculationStruct->getFactorFor1Amount(),
                        'maxRedeemPoints' => $calculationStruct->getMaxRedeemPoints(),
                        'bonusSystemConversionFactorRedeem' => $calculationStruct->getBonusSystemConversionFactorRedeem(),
                        'bonusSystemConversionFactorCurrencyRedeem' => $calculationStruct->getBonusSystemConversionFactorCurrencyRedeem(),
                    ]
            ]
        );

        $event->getPage()->addExtension('customBonusSystem', $bonusSettings);
    }

    /**
     * Check if customer has not approved order point bookings. If config pointActivationType is set up to n activation
     * days => Then check if there is some point booking to activate.
     * Check if there are bonus points that expire. Then create flash message.
     * @param CustomerLoginEvent $event
     */
    public function onCustomerLoginEvent($event): void
    {
        if (!($event instanceof CustomerLoginEvent)) {
            return;
        }

        $context = $event->getSalesChannelContext();
        $customer = $event->getCustomer();
        $bonusSettings = $this->configService->getConfig($context);
        $settingVars = $bonusSettings->getVars();
        $pointActivationAfterDays = $settingVars['pointActivationAfterDays'];
        $expiryDays = $settingVars['expiryDays'];
        $session = $this->requestStack->getMainRequest()->getSession();

        if ($settingVars['useBonusSystem'] && $pointActivationAfterDays > 0) {
            $this->bonusService->approveBonusBookingsForCustomerAfterDays($customer, $pointActivationAfterDays, $context->getContext(), $bonusSettings);
        }
        if ($settingVars['useBonusSystem'] && $expiryDays > 0) {
            $dailyExpiry = $this->expiryService->getUpcomingExpiries($customer, $context);
            if ($dailyExpiry !== []) {
                $nextExpiry = current($dailyExpiry);
                $session->getFlashBag()->add(
                    'warning',
                    $this->translator->trans('custom-bonus-system.account.bonusExpireNextMessage', ['%points%' => $nextExpiry['points'], '%expiryDays%' => $nextExpiry['expiryDays']])
                );

                //$this->addFlash(self::WARNING, $this->trans('checkout.addToCartSuccess', ['%count%' => $count]));
                //echo "<pre>"; print_R($dailyExpiry); echo "</pre>";die();
            }
        }
    }

    /**
     * Assign bonus system settings and points to the checkout register page
     *
     * @param CheckoutRegisterPageLoadedEvent $event
     */
    public function onCheckoutRegisterPageLoadedEvent(CheckoutRegisterPageLoadedEvent $event): void
    {
        $bonusSettings = $this->configService->getConfig($event->getSalesChannelContext());
        $calculationStruct = $this->bonusService->getCalculationStruct(
            $bonusSettings->getVars(),
            $event->getPage()->getCart(),
            $event->getSalesChannelContext()
        );

        $event->getPage()->addExtensions([
            'customBonusSystem' => $bonusSettings,
            'customBonusSystemPoints' => new ArrayStruct([
                'points' => $calculationStruct->getGetPoints()
            ])
        ]);
    }
}
