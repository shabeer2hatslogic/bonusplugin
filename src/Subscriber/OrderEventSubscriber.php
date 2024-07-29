<?php declare(strict_types=1);

namespace CustomBonusSystem\Subscriber;

use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Struct\ArrayEntity;
use CustomBonusSystem\Core\Bonus\ConfigData;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Bonus\ConfigService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use CustomBonusSystem\Core\Entity\Bonus\BonusBookingEntity;

class OrderEventSubscriber implements EventSubscriberInterface
{
    public const TMP_BONUS_POINTS_KEY = 'tmpBonusPoints';

    /**
     * @var ConfigService
     */
    private ConfigService $configService;

    /**
     * @var BonusService
     */
    private BonusService $bonusService;

    /**
     * @param ConfigService $configService
     * @param BonusService $bonusService
     */
    public function __construct(ConfigService $configService, BonusService $bonusService)
    {
        $this->configService = $configService;
        $this->bonusService = $bonusService;
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'onCartConvertedEvent',
            CheckoutOrderPlacedEvent::class => 'onCheckoutOrderPlacedEvent'
        ];
    }

    /**
     * @param CartConvertedEvent $event
     * @return void
     */
    public function onCartConvertedEvent(CartConvertedEvent $event): void
    {
        $settingVars = $this->configService->getConfig($event->getSalesChannelContext())->getVars();
        $calculationStruct = $this->bonusService->getCalculationStruct($settingVars, $event->getCart(), $event->getSalesChannelContext());

        // Sets collected points to the temporary bonus points custom field
        // this value is used to store collected points in another step
        $convertedCart = $event->getConvertedCart();
        $convertedCart['customFields'][self::TMP_BONUS_POINTS_KEY]['get'] = $calculationStruct->getGetPoints();
        $event->setConvertedCart($convertedCart);
    }

    /**
     * @param CheckoutOrderPlacedEvent $event
     * @return void
     */
    public function onCheckoutOrderPlacedEvent(CheckoutOrderPlacedEvent $event): void
    {
        $context = $event->getContext();
        $order = $event->getOrder();

        if (!$order) {
            return;
        }

        $bonusSettings = $this->configService->getConfigWithCustomerGroup($order->getOrderCustomer()->getCustomer()->getGroupId(), $event->getSalesChannelId());
        if (!$bonusSettings) {
            return;
        }

        $settingVars = $bonusSettings->getVars();
        if (!$settingVars['useBonusSystem']) {
            return;
        }

        if ($order->getOrderCustomer()->getCustomer()->getGuest()) {
            return;
        }

        if ($settingVars['bonusSystemConversionFactorCollect']) {
            $approved = false;
            if ($settingVars['pointActivationType'] == ConfigData::POINT_ACTIVATION_IMMEDIATELY_AFTER_ORDERING_ID &&
                ($bonusSettings->isPointActivationConditionOr() || $bonusSettings->getPointActivationAfterDays() < 1)
            ) {
                $approved = true;
            }

            $points = 0;
            $customFields = $order->getCustomFields();
            if ($customFields && isset($customFields[self::TMP_BONUS_POINTS_KEY]) && $customFields[self::TMP_BONUS_POINTS_KEY]['get']) {
                $points = $customFields[self::TMP_BONUS_POINTS_KEY]['get'];

                unset($customFields[self::TMP_BONUS_POINTS_KEY]);
                $order->setCustomFields($customFields);
            }

            $isAdminApiContext = $context->getSource() instanceof AdminSalesChannelApiSource;
            $canStorePointsForAdminApiContext = $bonusSettings->isGainPointsForBackendOrder();

            if (!$isAdminApiContext || $canStorePointsForAdminApiContext) {
                $this->bonusService->storePointsForOrder($order, $context, $points);
            }

            if ($approved) {
                $this->bonusService->activatePointsForOrder($order, $context);
            }
        }

        $this->bonusService->redeemPointsForOrder($order, $context);

        $orderId = $order->getId();
        $customerId = $order->getOrderCustomer()->getCustomerId();

        $data = [
            'points' => [
                'earned' => 0,
                'spent' => 0
            ]
        ];

        // Assign booked (earned and spent) points to the order
        if ($bookedPoints = $this->bonusService->getBookedPointsForOrder($orderId, $customerId, $event->getContext())) {

            /** @var BonusBookingEntity $bookedPoint */
            foreach ($bookedPoints as $bookedPoint) {
                $points = $bookedPoint->getPoints();

                // earned points
                if ($points && $points > 0) {
                    if ($bookedPoint->isApproved()) {
                        $data['points']['earned'] = $points;
                    }
                }

                // spent points
                if ($points && $points < 0) {
                    $data['points']['spent'] = $points;
                }
            }
        }

        $order->addExtension('customBonusSystem', new ArrayEntity($data));
    }
}
