<?php declare(strict_types=1);

namespace CustomBonusSystem\Subscriber;

use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Bonus\ConfigData;
use CustomBonusSystem\Core\Bonus\ConfigService;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderStateSubscriber implements EventSubscriberInterface
{
    /**
     * @var ConfigService
     */
    private $configService;

    public function __construct(
        ConfigService $configService,
        private readonly BonusService $bonusService
    )
    {
        $this->configService = $configService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'state_enter.order_transaction.state.paid' => 'onOrderStatePaid',
            'state_enter.order.state.completed' => 'onOrderStateCompleted',
            'state_enter.order.state.cancelled' => 'onOrderStateCancelled',
            'state_enter.order_delivery.state.shipped' => 'onOrderStateShipped',
        ];
    }

    /**
     * Automatic point remove if order status = canceled
     * @param OrderStateMachineStateChangeEvent $event
     */
    public function onOrderStateCancelled(OrderStateMachineStateChangeEvent $event): void
    {
        if (!($event instanceof OrderStateMachineStateChangeEvent)) {
            return;
        }

        $order = $event->getOrder();
        $context = $event->getContext();

        $bonusSettings = $this->configService->getConfigWithCustomerGroup($order->getOrderCustomer()->getCustomer()->getGroupId(), $event->getSalesChannelId());
        $settingVars = $bonusSettings->getVars();

        if (!$settingVars['useBonusSystem'] || !$settingVars['removePointsOnOrderCanceled']) {
            return;
        }

        $this->bonusService->removePointsForOrder($order, $context);
    }

    /**
     * Automatic point activation if order status = paid
     * @param OrderStateMachineStateChangeEvent $event
     */
    public function onOrderStatePaid(OrderStateMachineStateChangeEvent $event): void
    {
        if (!($event instanceof OrderStateMachineStateChangeEvent)) {
            return;
        }

        $order = $event->getOrder();
        $context = $event->getContext();

        $bonusSettings = $this->configService->getConfigWithCustomerGroup($order->getOrderCustomer()->getCustomer()->getGroupId(), $event->getSalesChannelId());
        $settingVars = $bonusSettings->getVars();

        /**
         * Do not activate order points if on of the following conditions matched
         * @see https://trello.com/c/8yAzKa9c/369-prio-3-custom-230614-custom-238187-bonus-system-activate-points-on-activation-event-after-x-days
         */
        if (!$settingVars['useBonusSystem']
            || $settingVars['pointActivationType'] != ConfigData::POINT_ACTIVATION_ORDER_PAID_ID
            || ($bonusSettings->isPointActivationConditionAnd() && $bonusSettings->getPointActivationAfterDays() > 0)
        ) {
            return;
        }

        $this->bonusService->activatePointsForOrder($order, $context);
    }

    /**
     * Automatic point activation if order status = completed
     * @param OrderStateMachineStateChangeEvent $event
     */
    public function onOrderStateCompleted(OrderStateMachineStateChangeEvent $event): void
    {
        if (!($event instanceof OrderStateMachineStateChangeEvent)) {
            return;
        }

        $order = $event->getOrder();
        $context = $event->getContext();

        $bonusSettings = $this->configService->getConfigWithCustomerGroup($order->getOrderCustomer()->getCustomer()->getGroupId(), $event->getSalesChannelId());

        $settingVars = $bonusSettings->getVars();

        /**
         * Do not activate order points if on of the following conditions matched
         * @see https://trello.com/c/8yAzKa9c/369-prio-3-custom-230614-custom-238187-bonus-system-activate-points-on-activation-event-after-x-days
         */
        if (!$settingVars['useBonusSystem']
            || $settingVars['pointActivationType'] != ConfigData::POINT_ACTIVATION_ORDER_COMPLETED_ID
            || ($bonusSettings->isPointActivationConditionAnd() && $bonusSettings->getPointActivationAfterDays() > 0)
        ) {
            return;
        }

        $this->bonusService->activatePointsForOrder($order, $context);
    }

    /**
     * Automatic point activation if order delivery status = shipped
     * @param OrderStateMachineStateChangeEvent $event
     * @return void
     */
    public function onOrderStateShipped(OrderStateMachineStateChangeEvent $event)
    {
        if (!($event instanceof OrderStateMachineStateChangeEvent)) {
            return;
        }

        $order = $event->getOrder();
        $context = $event->getContext();
        $bonusSettings = $this->configService->getConfigWithCustomerGroup($order->getOrderCustomer()->getCustomer()->getGroupId(), $event->getSalesChannelId());

        if (!$bonusSettings->isUseBonusSystem()
            || !$bonusSettings->isPointActivationType(ConfigData::POINT_ACTIVATION_ORDER_SHIPPED_ID)
            || ($bonusSettings->isPointActivationConditionAnd() && $bonusSettings->getPointActivationAfterDays() > 0)
        ) {
            return;
        }

        $this->bonusService->activatePointsForOrder($order, $context);
    }
}
