<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Doctrine\DBAL\Connection;
use CustomBonusSystem\Core\Bonus\Calculation\CalculationService;
use CustomBonusSystem\Core\Bonus\Calculation\Struct\CalculationStruct;
use CustomBonusSystem\Core\Checkout\Bonus\BonusProcessor;
use CustomBonusSystem\Core\Entity\Bonus\BonusBookingCollection;
use CustomBonusSystem\Core\Entity\Bonus\BonusBookingEntity;
use CustomBonusSystem\Core\Entity\Bonus\BonusUserPointCollection;
use CustomBonusSystem\Core\Entity\Bonus\BonusUserPointEntity;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BonusService
{
    final const BONUS_POINTS_ACTIVATION_LAST_CHECK = 'custom_bonus_points_activation_last_check';
    private readonly EntityRepository $bonusBookingRepository;
    private readonly EntityRepository $bonusUserPointRepository;
    private readonly BonusProcessor $bonusProcessor;
    private readonly CalculationService $calculationService;

    public function __construct(
        private readonly Connection $connection,
        EntityRepository $bonusBookingRepository,
        EntityRepository $bonusUserPointRepository,
        BonusProcessor $bonusProcessor,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly CartRuleLoader $cartRuleLoader,
        CalculationService $calculationService,
        protected EntityRepository $customerRepository
    ) {
        $this->bonusBookingRepository = $bonusBookingRepository;
        $this->bonusUserPointRepository = $bonusUserPointRepository;
        $this->bonusProcessor = $bonusProcessor;
        $this->calculationService = $calculationService;
    }

    /**
     * Create a new entry in table custom_bonus_system_booking
     * @param $points
     * @param $description
     * @param $customerId
     * @param $salesChannelId
     */
    public function createBookingEntry($points, $description, $customerId, $salesChannelId, Context $context): void
    {
        $this->bonusBookingRepository->create([
            [
                'points' => $points,
                'customerId' => $customerId,
                'description' => $description,
                'salesChannelId' => $salesChannelId,
            ]
        ], $context);
    }

    /**
     * Save points to user_point db table
     * @param $points
     * @param string $customerId
     */
    protected function addPointsToCustomerAccount($points, $customerId, Context $context): void
    {
        $entryId  = Uuid::randomHex();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('custom_bonus_system_user_point.customerId', $customerId));

        /** @var BonusUserPointEntity $userPointEntry */
        $userPointEntry = $this->bonusUserPointRepository->search($criteria, $context)->getEntities()->first();

        if ($userPointEntry) {
            $entryId = $userPointEntry->getId();
            $points  = $userPointEntry->getPoints() + (int) $points;
        }

        $data = [
            'id' => $entryId,
            'points' => $points,
            'customerId' => $customerId
        ];

        $this->bonusUserPointRepository->upsert([$data], $context);
    }


    /**
     * Add bookings to db and recalculate points
     */
    public function addApprovedBookingToCustomerAccount(int $points, string $customerId, string $description, string $salesChannelId, Context $context): void
    {
        $this->bonusBookingRepository->create([
            [
                'points' => $points,
                'customerId' => $customerId,
                'description' => $description,
                'salesChannelId' => $salesChannelId,
                'approved' => true,
                'createdAt' => new \DateTime()
            ]
        ], $context);
        $this->addPointsToCustomerAccount($points, $customerId, $context);
    }

    /**
     * Approve bonus bookings for a customer if $pointActivationAfterDays is reached
     */
    public function approveBonusBookingsForCustomerAfterDays(CustomerEntity $customer, int $pointActivationAfterDays, Context $context, ConfigData $bonusSettings): void
    {
        $rangeStart = $rangeEnd = new \DateTimeImmutable();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()))
            ->addAssociation('order.transactions')
            ->addAssociation('order.deliveries')
            ->addFilter(new RangeFilter('createdAt', [
                RangeFilter::GTE => $rangeStart->sub(new \DateInterval('P900D'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                RangeFilter::LTE => $rangeEnd->sub(new \DateInterval("P{$pointActivationAfterDays}D"))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]))
            ->addFilter(new EqualsFilter('approved', 0));

        $data = [];
        $points = 0;
        $bookings = $this->bonusBookingRepository->search($criteria, $context);

        /** @var BonusBookingEntity $booking */
        foreach ($bookings as $booking) {
            /**
             * Skip approval of points if Points activation condition is set to AND
             * @see https://trello.com/c/8yAzKa9c/369-prio-3-custom-230614-custom-238187-bonus-system-activate-points-on-activation-event-after-x-days
             */
            if ($bonusSettings->isPointActivationConditionAnd() && $bonusSettings->getPointActivationAfterDays() > 0) {
                /**
                 * If order is not yet paid skip approval
                 */
                if ($bonusSettings->isPointActivationType(ConfigData::POINT_ACTIVATION_ORDER_PAID_ID)
                    && $booking->getOrder()->getTransactions()->last()->getStateMachineState()->getTechnicalName() !== OrderTransactionStates::STATE_PAID
                ) {
                    continue;
                }
                /**
                 * If order is not yet completed/done skip approval
                 */
                if ($bonusSettings->isPointActivationType(ConfigData::POINT_ACTIVATION_ORDER_COMPLETED_ID)
                    && $booking->getOrder()->getStateMachineState()->getTechnicalName() !== OrderStates::STATE_COMPLETED
                ) {
                    continue;
                }
                /**
                 * If order is not yet shipped skip approval
                 */
                if ($bonusSettings->isPointActivationType(ConfigData::POINT_ACTIVATION_ORDER_SHIPPED_ID)
                    && $booking->getOrder()->getDeliveries()->last()->getStateMachineState()->getTechnicalName() !== OrderDeliveryStates::STATE_SHIPPED
                ) {
                    continue;
                }
            }

            $data[] = [
                'id' => $booking->getId(),
                'approved' => true,
            ];

            $points += $booking->getPoints();
        }

        if (!empty($data)) {
            $this->bonusBookingRepository->update($data, $context);
        }

        $this->addPointsToCustomerAccount($points, $customer->getId(), $context);
    }

    /**
     * Remove points for one customer order of a bonus system order booking
     */
    public function removePointsForOrder(OrderEntity $order, Context $context): void {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('custom_bonus_system_booking.orderId', $order->getId()));
        //$criteria->addFilter(new EqualsFilter('custom_bonus_system_booking.approved', 0));
        $bookingEntries = $this->bonusBookingRepository->search($criteria, $context);
        // @TODO: Loop over all entries and case:
        // -- If it is a credited and approved entry? Then remove the points with an booking removal entry
        // -- If is it a spend entry? Then restore points and add an booking restored entry
        /** @var BonusBookingEntity $bookingEntry */
        foreach($bookingEntries as $bookingEntry) {
            if ($bookingEntry->isApproved()) {
                $data = [
                    'points' => ($bookingEntry->getPoints() * -1),
                    'customerId' => $order->getOrderCustomer()->getCustomerId(),
                    'orderId' => $order->getId(),
                    'salesChannelId' => $order->getSalesChannelId(),
                    'description' => '',
                    'approved' => true
                ];

                $this->bonusBookingRepository->create([
                    $data
                ], $context);
                $this->addPointsToCustomerAccount(-1 * $bookingEntry->getPoints(), $order->getOrderCustomer()->getCustomerId(), $context);
            }
        }
    }

    /**
     * Set points for one customer order of a bonus system order booking to approved
     */
    public function activatePointsForOrder(OrderEntity $order, Context $context): void {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('custom_bonus_system_booking.orderId', $order->getId()));
        $criteria->addFilter(new EqualsFilter('custom_bonus_system_booking.approved', 0));
        $criteria->setLimit(1);
        $bookingEntries = $this->bonusBookingRepository->search($criteria, $context);
        /** @var BonusBookingEntity $bookingEntry */
        foreach($bookingEntries as $bookingEntry) {
            $this->bonusBookingRepository->update([
                [
                'id' => $bookingEntry->getId(),
                'approved' => true
                ]
            ], $context);
            $this->addPointsToCustomerAccount($bookingEntry->getPoints(), $order->getOrderCustomer()->getCustomerId(), $context);
        }
    }

    /**
     * Redeem points for an order to related customer
     */
    public function redeemPointsForOrder(OrderEntity $order, Context $context)
    {
        $customerId = $order->getOrderCustomer()->getCustomer()->getId();
        //$order->getAmountTotal();
        $points = $this->bonusProcessor->getPointRedeem();
        if ($points !== 0) {
            $data = [
                'points' => ($points * -1),
                'customerId' => $customerId,
                'orderId' => $order->getId(),
                'salesChannelId' => $order->getSalesChannelId(),
                'description' => '',
                'approved' => true
            ];
            $this->bonusBookingRepository->create([$data], $context);

            $this->addPointsToCustomerAccount(($points * -1), $order->getOrderCustomer()->getCustomerId(), $context);

            $this->bonusProcessor->removePointRedeem();
        }
    }

    /**
     * Save points for an order to related customer
     */
    public function storePointsForOrder(OrderEntity $order, Context $context, int $points, bool $approved = false): void
    {
        if ($points > 0) {
            $data = [
                'points' => $points,
                'customerId' => $order->getOrderCustomer()->getCustomer()->getId(),
                'orderId' => $order->getId(),
                'salesChannelId' => $order->getSalesChannelId(),
                'description' => '',
                'approved' => $approved
            ];
            $this->bonusBookingRepository->create([$data], $context);
        }
    }

    /**
     * Return bonus points a customer wants to redeem in checkout
     *
     * @return int
     */
    public function getWantToRedeem()
    {
        return $this->bonusProcessor->getPointRedeem();
    }

    /**
     * Return bonus points a customer wants to redeem in checkout, recalculate it if cart changed and if this are too
     * much points
     *
     * @return int
     */
    /**public function getRecalculatedWantToRedeem()
       {
           return $this->bonusProcessor->getRecalculatedPointRedeem();
       }*/
    /**
     * Get sum of points for current customer
     * @return false|int|mixed
     */
    public function getBonusSumForUser(SalesChannelContext $context)
    {
        $customer = $context->getCustomer();

        if (!$customer) {
            return 0;
        }

        /**$criteria = (new Criteria())
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $criteria->addFilter(new EqualsFilter('custom_bonus_system.customerId', $customer->getId()));

        $bonus = $this->bonusBookingRepository->search($criteria, $context->getContext());*/

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->from('custom_bonus_system_user_point')
            ->select('custom_bonus_system_user_point.points')
            ->where('custom_bonus_system_user_point.customer_id = :customerId')
            ->setParameter('customerId', Uuid::fromHexToBytes($customer->getId()));
        $bonus = $queryBuilder->execute()->fetchOne();
        if (!$bonus) {
            $bonus = 0;
        }
        return $bonus;
    }

    /**
     * Get all bonus entries for current customer
     * @param SalesChannelContext $context
     * @param Criteria $criteria
     * @param string|null $from
     * @param string|null $to
     * @return EntitySearchResult
     * @throws \Exception
     */
    public function getBonusForUser(SalesChannelContext $context, Criteria $criteria, string $from = null, string $to = null)
    {
        $customer = $context->getCustomer();

        $criteria->addFilter(new EqualsFilter('custom_bonus_system_booking.customerId', $customer->getId()));
        //$criteria->addFilter(new EqualsFilter('custom_bonus_system_booking.salesChannelId', $context->getSalesChannel()->getId()));

        $dateRange = [];
        if ($from) {
            $start = (new \DateTime($from))->setTime(0, 0);
            $dateRange[RangeFilter::GTE] = $start->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        if ($to) {
            $end = (new \DateTime($to))->setTime(23, 59, 99);
            $dateRange[RangeFilter::LTE] = $end->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        if (!empty($dateRange)) {
            $criteria->addFilter(new RangeFilter('custom_bonus_system_booking.createdAt', $dateRange));
        }

        $bonus = $this->bonusBookingRepository->search($criteria, $context->getContext());
        return $bonus;
    }

    public function getPointsForOrder(OrderEntity $order, Context $context): BonusBookingCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $order->getId()));

        /** @var BonusBookingCollection $entities */
        $entities = $this->bonusBookingRepository->search($criteria, $context)->getEntities();

        return $entities;
    }

    /**
     * Handle value of bonus points
     *
     * If removing bonus points so the points value is negative
     * then handle the value of negative points to that the final amount of points will not be negative
     *
     * @param string $customerId
     * @param int $points
     * @param Context $context
     */
    public function handleAmountOfAssignedPoints(string $customerId, int $points, Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('customerId', $customerId)
        );

        $userPointEntities = $this->bonusUserPointRepository->search($criteria, $context)->getEntities();

        $userPoints = 0;
        if ($userPointEntities->count() > 0) {
            $userPoints = $userPointEntities->first()->getPoints();

            if ($userPointEntities->count() > 1) {
                $userPoints = 0;

                /** @var BonusUserPointEntity $userPointEntity */
                foreach ($userPointEntities as $userPointEntity) {
                    $userPoints += $userPointEntity->getPoints();
                }
            }
        }

        // handle points negative value
        if (($points < 0) && ($diff = $userPoints + $points) < 0) {
            $points -= $diff;
        }

        return $points;
    }

    /**
     * Returns points booked for an order
     *
     * @return BonusBookingCollection|null
     */
    public function getBookedPointsForOrder(string $orderId, string $customerId, Context $context):? BonusBookingCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('orderId', $orderId),
                    new EqualsFilter('customerId', $customerId)
                ]
            )
        );

        return $this->bonusBookingRepository->search($criteria, $context)->getEntities();
    }

    /**
     * Returns the SalesChannelContext
     */
    public function createSalesChannelContext(string $salesChannelId, array $options = []): SalesChannelContext
    {
        $token = Uuid::randomHex();
        $salesChannelContext = $this->salesChannelContextFactory->create($token, $salesChannelId, $options);

        // hydrate context rule IDs - it's necessary for rules handling
        $this->cartRuleLoader->loadByToken($salesChannelContext, $token);
        return $salesChannelContext;
    }

    /**
     * Check if the order has booked some points
     * - check if some entities exist stored in the database
     */
    public function hasOrderBookedPoints(OrderEntity $order, Context $context): bool
    {
        return (bool) $this->getPointsForOrder($order, $context)->count();
    }

    /**
     * @return CalculationStruct
     */
    public function getCalculationStruct(array $settingVars, Cart $cart, SalesChannelContext $context): CalculationStruct
    {
        return $this->calculationService->buildCalculationStruct(
            $this->getWantToRedeem(),
            $this->bonusProcessor->getPointRedeemByType(),
            $this->getBonusSumForUser($context),
            $settingVars['basketAmountRedeemRestriction'],
            $settingVars['basketAmountRedeemRestrictionValue'],
            $settingVars['bonusSystemConversionFactorRedeem'],
            $settingVars['disallowRedeemPoints'],
            $settingVars['collectPointsWithoutShippingCosts'],
            $settingVars['collectPointsRound'],
            $cart,
            $context,
            $settingVars['bonusSystemConversionFactorCollect']
        );
    }

    public function updateUserPointsEntity(array $data, Context $context): void
    {
        $this->bonusUserPointRepository->upsert($data, $context);
    }

    /**
     * @param array $data
     * @param Context $context
     * @return void
     */
    public function updateBookingEntries(array $data, Context $context): void
    {
        $this->bonusBookingRepository->upsert($data, $context);
    }

    /**
     * @param array $data
     * @param Context $context
     * @return void
     */
    public function updateCustomers(array $data, Context $context): void
    {
        $this->customerRepository->update($data, $context);
    }

    /**
     * @param Context $context
     * @param string $description
     * @param string|null $customerId
     * @return void
     */
    public function resetBonusBookings(Context $context, string $description = '', string $customerId = null): void
    {
        $pointsData = [];
        $bookingData = [];

        $pointEntities = $this->getBonusPoints($context, $customerId);

        /** @var BonusUserPointEntity $pointEntity */
        foreach ($pointEntities as $pointEntity) {
            $points = $pointEntity->getPoints();
            $bookingData[] = [
                'customerId'     => $pointEntity->getCustomerId(),
                'salesChannelId' => $pointEntity->getCustomer()->getSalesChannelId(),
                'points'         => (-1 * $points),
                'description'    => $description,
                'approved'       => true
            ];

            $pointsData[] = [
                'id'     => $pointEntity->getId(),
                'points' => 0
            ];
        }

        // Reset booking entries
        if (!empty($bookingData)) {
            $this->updateBookingEntries($bookingData, $context);
        }

        // Reset user points
        if (!empty($pointsData)) {
            $this->updateUserPointsEntity($pointsData, $context);
        }
    }

    /**
     * @param Context $context
     * @param string|null $customerId
     * @param bool $skipZeroPoints
     * @return BonusUserPointCollection
     */
    public function getBonusPoints(Context $context, string $customerId = null, bool $skipZeroPoints = true): BonusUserPointCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('customer');

        if ($customerId) {
            $criteria->addFilter(new EqualsFilter('customerId', $customerId));
        }

        if ($skipZeroPoints) {
            $criteria->addFilter(new RangeFilter('points', [RangeFilter::GT => 0]));
        }
        return $this->bonusUserPointRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param int $limit
     */
    public function getCustomersWithoutApprovedPoints(Context $context, Criteria $additionalCriteria = null): array
    {
        $criteria = $additionalCriteria ?: new Criteria();
        $criteria->addFilter(new EqualsFilter('approved', false));
        $criteria->addGroupField(new FieldGrouping('customerId'));

        /** @var BonusBookingCollection $bonusPoints */
        $bonusPoints = $this->bonusBookingRepository->search($criteria, $context)->getEntities();
        return $bonusPoints->count() ? $bonusPoints->getCustomers() : [];
    }

    /**
     * @param int $days
     */
    public function getCustomersWithoutApprovedPointsUsingLastCheck(Context $context, int $lastCheck = 1, int $limit = 1000): array
    {
        $days = (-1) * $lastCheck;
        $date = (new \DateTime())->modify($days . ' days')->setTime(0, 0);

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new EqualsFilter('customer.customFields', null),
                new EqualsFilter('customer.customFields.' . self::BONUS_POINTS_ACTIVATION_LAST_CHECK, null),
                new RangeFilter('customer.customFields.' . self::BONUS_POINTS_ACTIVATION_LAST_CHECK, [
                    RangeFilter::LT => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT)
                ])
            ]
        ));

        if ($limit) {
            $criteria->setLimit($limit);
        }

        return $this->getCustomersWithoutApprovedPoints($context, $criteria);
    }
}
