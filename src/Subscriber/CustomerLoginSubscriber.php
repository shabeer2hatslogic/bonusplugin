<?php

namespace CustomBonusSystem\Subscriber;

use CustomBonusSystem\Core\Bonus\ConfigService;
use CustomBonusSystem\Core\Bonus\ExpiryService;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerLoginSubscriber implements EventSubscriberInterface
{
    private readonly ConfigService $configService;

    public function __construct(ConfigService $configService, private readonly ExpiryService $expiryService)
    {
        $this->configService = $configService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin',
        ];
    }

    public function onCustomerLogin(CustomerLoginEvent $event)
    {
        $config = $this->configService->getConfig($event->getSalesChannelContext());

        if ($config->getExpiryDays() <= 0) {
            return;
        }

        if (!$this->expiryService->needsCheck($event->getCustomer(), $event->getSalesChannelContext())) {
            return;
        }

        $this->expiryService->runCheckForCustomer($event->getCustomer(), $event->getSalesChannelContext());
    }
}
