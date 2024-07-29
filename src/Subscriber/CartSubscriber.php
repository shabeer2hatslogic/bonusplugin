<?php declare(strict_types=1);

namespace CustomBonusSystem\Subscriber;

use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Bonus\ConfigService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use CustomBonusSystem\Core\Checkout\Bonus\BonusProcessor;
use CustomBonusSystem\Storefront\Controller\BonusController;
use CustomBonusSystem\Core\Events\BonusPointsDiscountRemovedEvent;


class CartSubscriber implements EventSubscriberInterface
{
    const PREMS_BONUS_SYSTEM_DONT_REDEEM_POINTS_AUTOMATICALLY = 'custom_bonus_system_dont_redeem_points_automatically';

    /**
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    /**
     * @var BonusService
     */
    protected BonusService $bonusService;

    /**
     * @var ConfigService
     */
    protected ConfigService $configService;

    /**
     * @var BonusController
     */
    protected BonusController $bonusController;

    /**
     * @var Processor
     */
    protected Processor $processor;

    /**
     * @var BonusProcessor
     */
    protected BonusProcessor $bonusProcessor;

    /**
     * @var SessionInterface
     */
    protected SessionInterface $session;

    /**
     * @param RequestStack $requestStack
     * @param BonusService $bonusService
     * @param ConfigService $configService
     * @param BonusController $bonusController
     * @param Processor $processor
     * @param BonusProcessor $bonusProcessor
     */
    public function __construct(
        RequestStack $requestStack,
        BonusService $bonusService,
        ConfigService $configService,
        BonusController $bonusController,
        Processor $processor,
        BonusProcessor $bonusProcessor
    )
    {
        $this->requestStack = $requestStack;
        $this->bonusService = $bonusService;
        $this->configService = $configService;
        $this->bonusController = $bonusController;
        $this->processor = $processor;
        $this->bonusProcessor = $bonusProcessor;

        try {
            $this->session = $this->requestStack->getSession();
            if ($this->requestStack->getMainRequest()->hasSession()) {
                $this->session = $this->requestStack->getMainRequest()->getSession();
            }
        } catch(\Exception $e) {
            $this->session = null;
        }
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CartChangedEvent::class                 => 'onCartChanged',
            CheckoutOrderPlacedEvent::class         => 'onCheckoutOrderPlaced',
            BonusPointsDiscountRemovedEvent::class  => 'onBonusPointsDiscountRemoved'
        ];
    }

    /**
     * @param CartChangedEvent $event
     * @return void
     */
    public function onCartChanged(CartChangedEvent $event): void
    {
        $salesChannelContext = $event->getContext();
        $bonusSettings = $this->configService->getConfig($salesChannelContext);
        $settingVars = $bonusSettings->getVars();

        $isPluginActive = $bonusSettings->isUseBonusSystem();
        $canRedeemPointsAutomatically = $bonusSettings->isRedeemPointsAutomatically();

        if (!$isPluginActive || !$canRedeemPointsAutomatically) {
            return;
        }

        $cart = $event->getCart();
        if ($cart->getLineItems()->count() === 0) {
            $this->clearSessionSettings();
            return;
        }

        if ($this->session != null && $this->session->has(self::PREMS_BONUS_SYSTEM_DONT_REDEEM_POINTS_AUTOMATICALLY)) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $behavior = new CartBehavior($salesChannelContext->getPermissions());

        // Remove bonus points discount
        $this->bonusProcessor->removePointRedeemByType();
        $cart = $this->processor->process($cart, $salesChannelContext, $behavior);

        // Calculate max redeem points
        $calculationStruct = $this->bonusService->getCalculationStruct($settingVars, $cart, $salesChannelContext);
        $points = $calculationStruct->getMaxRedeemPoints();

        // Set bonus points discount
        $request->request->set('bonuspoints', $points);
        $this->bonusController->redeemPoints($cart, $salesChannelContext, $request);
    }

    /**
     * @param BonusPointsDiscountRemovedEvent $event
     * @return void
     */
    public function onBonusPointsDiscountRemoved(BonusPointsDiscountRemovedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $bonusSettings = $this->configService->getConfig($salesChannelContext);

        $isPluginActive = $bonusSettings->isUseBonusSystem();
        $canRedeemPointsAutomatically = $bonusSettings->isRedeemPointsAutomatically();

        if (!$isPluginActive || !$canRedeemPointsAutomatically) {
            return;
        }

        $this->session->set(self::PREMS_BONUS_SYSTEM_DONT_REDEEM_POINTS_AUTOMATICALLY, true);
    }

    /**
     * @return void
     */
    public function onCheckoutOrderPlaced(): void
    {
        $this->clearSessionSettings();
    }

    /**
     * @return void
     */
    private function clearSessionSettings(): void
    {
        if ($this->session != null & $this->session->has(self::PREMS_BONUS_SYSTEM_DONT_REDEEM_POINTS_AUTOMATICALLY)) {
            $this->session->remove(self::PREMS_BONUS_SYSTEM_DONT_REDEEM_POINTS_AUTOMATICALLY);
        }
    }
}