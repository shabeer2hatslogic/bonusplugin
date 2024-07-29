<?php declare(strict_types=1);

namespace CustomBonusSystem\Service\ScheduledTask;

use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Bonus\ConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class BonusPointActivationTaskHandler extends ScheduledTaskHandler
{
    final const CONFIG_DOMAIN = 'CustomBonusSystem.config';

    /**
     * @var ConfigService
     */
    protected ConfigService $configService;

    /**
     * @var BonusService
     */
    protected BonusService $bonusService;

    /**
     * @param EntityRepository $scheduledTaskRepository
     * @param SystemConfigService $systemConfigService
     * @param ConfigService $configService
     * @param BonusService $bonusService
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        protected SystemConfigService $systemConfigService,
        ConfigService $configService,
        BonusService $bonusService
    )
    {
        parent::__construct($scheduledTaskRepository);
        $this->configService = $configService;
        $this->bonusService = $bonusService;
    }

    /**
     * @return iterable
     */
    public static function getHandledMessages(): iterable
    {
        return [ BonusPointActivationTask::class ];
    }

    /**
     * @return void
     */
    public function run(): void
    {
        $context = Context::createDefaultContext();
        $customers = $this->bonusService->getCustomersWithoutApprovedPointsUsingLastCheck($context);

        if (!empty($customers)) {
            $today = (new \DateTime())->setTime(0, 0)->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $customersData = [];

            /** @var CustomerEntity $customer */
            foreach ($customers as $customer) {
                $salesChannelId = $customer->getSalesChannelId();
                $isPluginActive = $this->getValueFromPluginConfig('useBonusSystem', $salesChannelId);
                $pointActivationAfterDays = $this->getValueFromPluginConfig('pointActivationAfterDays', $salesChannelId);

                if ($isPluginActive && $pointActivationAfterDays > 0) {
                    $salesChannelContext = $this->bonusService->createSalesChannelContext(
                        $salesChannelId,
                        [
                            SalesChannelContextService::CUSTOMER_ID => $customer->getId()
                        ]
                    );

                    $bonusSettings = $this->configService->getConfig($salesChannelContext);
                    $this->bonusService->approveBonusBookingsForCustomerAfterDays($customer, $pointActivationAfterDays, $context, $bonusSettings);
                }

                $customersData[] = [
                    'id' => $customer->getId(),
                    'customFields' => [
                        BonusService::BONUS_POINTS_ACTIVATION_LAST_CHECK => $today
                    ]
                ];
            }

            // Sets a new last check date for customers
            if (!empty($customersData)) {
                $this->bonusService->updateCustomers($customersData, $context);
            }
        }
    }

    /**
     * @param string|null $salesChannelId
     * @return bool|float|int|mixed[]|string|null
     */
    protected function getValueFromPluginConfig(string $field, string $salesChannelId = null): bool|float|int|array|string|null
    {
        return $this->systemConfigService->get(self::CONFIG_DOMAIN . '.' . $field, $salesChannelId);
    }
}