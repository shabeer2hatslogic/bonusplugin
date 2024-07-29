<?php declare(strict_types=1);

namespace CustomBonusSystem\Service\ScheduledTask;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Bonus\ExpiryService;
use CustomBonusSystem\Core\Bonus\ConfigService;
use CustomBonusSystem\Core\Bonus\NotificationService;
use CustomBonusSystem\Core\Entity\Bonus\BonusUserPointEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class PointsExpirationNotificationTaskHandler extends ScheduledTaskHandler
{

    /**
     * @var ConfigService
     */
    protected ConfigService $configService;

    /**
     * @var BonusService
     */
    protected BonusService $bonusService;

    /**
     * @var ExpiryService
     */
    protected ExpiryService $expiryService;

    /**
     * @var NotificationService
     */
    protected NotificationService $notificationService;

    /**
     * @param EntityRepository $scheduledTaskRepository
     * @param ConfigService $configService
     * @param BonusService $bonusService
     * @param ExpiryService $expiryService
     * @param NotificationService $notificationService
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        ConfigService $configService,
        BonusService $bonusService,
        ExpiryService $expiryService,
        NotificationService $notificationService
    )
    {
        parent::__construct($scheduledTaskRepository);
        $this->configService = $configService;
        $this->bonusService = $bonusService;
        $this->expiryService = $expiryService;
        $this->notificationService = $notificationService;
    }

    /**
     * @return iterable
     */
    public static function getHandledMessages(): iterable
    {
        return [ PointsExpirationNotificationTask::class ];
    }

    /**
     * @return void
     */
    public function run(): void
    {
        $context = Context::createDefaultContext();

        $expiryDays = $this->configService->getValueFromPluginConfig('expiryDays') ?: 0;
        $daysBeforeExpiration = $this->configService->getValueFromPluginConfig('numberDaysBeforeAutomaticEMailPointExpiration');

        if ($expiryDays <= 0 || $daysBeforeExpiration <= 0 || $expiryDays < $daysBeforeExpiration) {
            return;
        }

        $days = $expiryDays - $daysBeforeExpiration;
        $customerPoints = $this->expiryService->getPointsExpireInDaysUsingLastCheck($days, $context);

        if ($customerPoints->count()) {
            $customerPoints = $customerPoints->getCustomerPoints();

            $today = (new \DateTime())->setTime(0, 0)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $customersData = [];
            foreach ($customerPoints as $customerPoint) {

                /** @var CustomerEntity $customer */
                $customer = $customerPoint['customer'];
                $salesChannelId = $customer->getSalesChannelId();

                // If the customer has subscribed to the points expiration notification
                if ($this->canSendPointsExpirationNotification($customer)) {
                    $isPluginActive = $this->configService->getValueFromPluginConfig('useBonusSystem', $salesChannelId);
                    $automaticEMailPointExpiration = $this->configService->getValueFromPluginConfig('automaticEMailPointExpiration', $salesChannelId);

                    // If the plugin is active and the automatic email notification is enabled
                    if ($isPluginActive && $automaticEMailPointExpiration) {
                        $currentUserPoints = $this->expiryService->getCurrentUserPoint($customer, $context);
                        if (!$currentUserPoints instanceof BonusUserPointEntity) {
                            continue;
                        }

                        $pointsToExpire = ($customerPoint['points'] > $currentUserPoints->getPoints()) ? $currentUserPoints->getPoints() : $customerPoint['points'];
                        if ($pointsToExpire) {

                            // Send notification to customer
                            $this->notificationService->pointsExpireNotification($customer, $currentUserPoints->getPoints(), $pointsToExpire, $daysBeforeExpiration, $context);
                        }
                    }
                }

                $customersData[] = [
                    'id' => $customer->getId(),
                    'customFields' => [
                        ExpiryService::BONUS_POINTS_EXPIRE_LAST_CHECK => $today
                    ]
                ];
            }

            $this->bonusService->updateCustomers($customersData, $context);
        }
    }

    /**
     * @param CustomerEntity $customer
     * @return bool
     */
    private function canSendPointsExpirationNotification(CustomerEntity $customer): bool
    {
        $canSendPointsExpirationNotification = !$customer->hasExtension('customBonusSystemUserPoint');
        if ($customer->hasExtension('customBonusSystemUserPoint')) {
            /** @var BonusUserPointEntity $userPoint */
            $userPoint = $customer->getExtension('customBonusSystemUserPoint');
            $canSendPointsExpirationNotification = $userPoint->isCanSendPointsExpirationNotification();
        }

        return $canSendPointsExpirationNotification;
    }
}
