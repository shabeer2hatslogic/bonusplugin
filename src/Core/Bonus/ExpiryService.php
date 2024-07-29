<?php

namespace CustomBonusSystem\Core\Bonus;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use CustomBonusSystem\Core\Entity\Bonus\BonusBookingCollection;
use CustomBonusSystem\Core\Entity\Bonus\BonusBookingEntity;
use CustomBonusSystem\Core\Entity\Bonus\BonusUserPointEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ExpiryService
{
    public const BONUS_POINTS_EXPIRE_LAST_CHECK = 'custom_bonus_points_expire_last_check';
    final public const BOOKING_POINT_ALREADY_CHECKED = 'alreadyChecked';
    final public const BOOKING_POINT_CHECKED_AT = 'checkedAt';
    final public const BOOKING_POINT_IS_EXPIRY_BOOKING = 'isExpiryBooking';
    final public const BOOKING_POINT_EXPIRED_IDS = 'expiredIds';
    private readonly ConfigService $configService;

    public function __construct(
        private readonly EntityRepository $bookingPointsRepository,
        private readonly EntityRepository $userPointRepository,
        ConfigService $configService
    ) {
        $this->configService = $configService;
    }

    public function runCheckForCustomer(CustomerEntity $customer, SalesChannelContext $salesChannelContext, ?\DateTimeInterface $startFrom = null): void
    {
        $config = $this->configService->getConfig($salesChannelContext);
        if ($config->getExpiryDays() <= 0) {
            return;
        }

        $currentUserPoints = $this->getCurrentUserPoint($customer, $salesChannelContext->getContext());
        if (!$currentUserPoints instanceof BonusUserPointEntity) {
            return;
        }

        $startAt = $startFrom;
        if (!$startAt instanceof \DateTimeInterface) {
            $startAt = $currentUserPoints->getLastCheckedAt();
            if ($startAt instanceof \DateTimeImmutable) {
                $startAt = $startAt->modify('- '.$config->getExpiryDays().' days');
            }
        }

        $date = (new \DateTime())->modify('-' . $config->getExpiryDays() . ' days')->setTime(0, 0);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));
        $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::LTE => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));

        if ($startAt) {
            $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::GTE => $startAt->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));
        }

        $bookingPoints = $this->bookingPointsRepository->search($criteria, $salesChannelContext->getContext())->getEntities();

        $sum = 0;
        $checkIds = [];
        /** @var BonusBookingEntity $bookingPoint */
        foreach ($bookingPoints as $bookingPoint) {
            $alreadyChecked = $bookingPoint->getCustomFields()[self::BOOKING_POINT_ALREADY_CHECKED] ?? false;
            if ($alreadyChecked) {
                continue;
            }
            $checkIds[] = $bookingPoint;
            $sum += $bookingPoint->getPoints();
        }

        $enrichData = array_map(static fn(BonusBookingEntity $bookingPoint) => [
            'id' => $bookingPoint->getId(),
            'customFields' => array_replace(
                $bookingPoint->getCustomFields() ?? [],
                [
                    self::BOOKING_POINT_ALREADY_CHECKED => true,
                    self::BOOKING_POINT_CHECKED_AT => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)
                ]
            )
        ], $checkIds);

        $updateData = [...$enrichData];
        $expiredPoints = min($sum, $currentUserPoints->getPoints());
        $newCurrentPoints = $currentUserPoints->getPoints() - $expiredPoints;

        if ($expiredPoints > 0) {
            $updateData[] = [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
                'customerId' => $customer->getId(),
                'points' => 0 - $expiredPoints,
                'approved' => true,
                'description' => '',
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'customFields' => [
                    self::BOOKING_POINT_IS_EXPIRY_BOOKING => true,
                    self::BOOKING_POINT_ALREADY_CHECKED => true,
                    self::BOOKING_POINT_CHECKED_AT => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    self::BOOKING_POINT_EXPIRED_IDS => array_map(static fn($row) => $row->getId(), $checkIds)
                ]
            ];
        }

        $this->bookingPointsRepository->upsert($updateData, $salesChannelContext->getContext());

        $this->userPointRepository->update(
            [
                [
                    'id' => $currentUserPoints->getId(),
                    'points' => $newCurrentPoints,
                    'updatedAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'lastCheckedAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            ],
            $salesChannelContext->getContext()
        );
    }

    public function getUpcomingExpiries(CustomerEntity $customer, SalesChannelContext $salesChannelContext): array
    {
        $config = $this->configService->getConfig($salesChannelContext);
        if ($config->getExpiryDays() <= 0) {
            return [];
        }

        $currentUserPoints = $this->getCurrentUserPoint($customer, $salesChannelContext->getContext());
        if (!$currentUserPoints instanceof BonusUserPointEntity) {
            return [];
        }

        $date = (new \DateTime())->modify('-' . $config->getExpiryDays() . ' days')->setTime(0, 0);
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));
        $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::GTE => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT)]));

        $bookingPoints = $this->bookingPointsRepository->search($criteria, $salesChannelContext->getContext())->getEntities();

        $dailyExpire = [];
        /** @var BonusBookingEntity $bookingPoint */
        foreach ($bookingPoints as $bookingPoint) {
            if ($bookingPoint->getPoints() <= 0) {
                continue;
            }
            $dateBooking = $bookingPoint->getCreatedAt();
            assert($dateBooking instanceof \DateTimeImmutable);
            $expiryDate = \DateTime::createFromImmutable($dateBooking);
            $expiryDate->modify('+'.$config->getExpiryDays().' days');
            $daysUntilExpiry = (new \DateTime())->diff($expiryDate)->days;
            $expiresAtString = $expiryDate->format('Y-m-d');

            if (!isset($dailyExpire[$expiresAtString])) {
                $dailyExpire[$expiresAtString] = ['points' => 0, 'bookingIds' => [], 'expiryDays' => $daysUntilExpiry];
            }

            $dailyExpire[$expiresAtString]['points'] += $bookingPoint->getPoints();
            $dailyExpire[$expiresAtString]['bookingIds'][] = $bookingPoint->getId();

            if ($currentUserPoints->getPoints() < $dailyExpire[$expiresAtString]['points']) {
                $dailyExpire[$expiresAtString]['points'] = $currentUserPoints->getPoints();
                break;
            }
        }

        return $dailyExpire;
    }

    public function needsCheck(CustomerEntity $customer, SalesChannelContext $salesChannelContext): bool
    {
        $currentUserPoints = $this->getCurrentUserPoint($customer, $salesChannelContext->getContext());

        $yesterday = (new \DateTime())->modify('-1 day');
        if (!$currentUserPoints instanceof BonusUserPointEntity) {
            return false;
        }
        $lastCheck = $currentUserPoints->getLastCheckedAt();

        return (!$lastCheck || $lastCheck < $yesterday);
    }

    /**
     * @param int $days
     * @param Context $context
     * @param Criteria|null $additionalCriteria
     * @return BonusBookingCollection|null
     */
    public function getPointsExpireInDays(int $days, Context $context, Criteria $additionalCriteria = null): ?BonusBookingCollection
    {
        if ($days <= 0) {
            return null;
        }

        $start = (new \DateTime())->modify('-' . $days . ' days')->setTime(0, 0);
        $end = (new \DateTime())->modify('-' . ($days - 1) . ' days')->setTime(0, 0);

        $criteria = $additionalCriteria ?: new Criteria();
        $criteria->addFilter(new EqualsFilter('approved', true));
        $criteria->addFilter(new RangeFilter('points', [RangeFilter::GT => 0]));
        $criteria->addFilter(new RangeFilter('createdAt', [
            RangeFilter::GTE => $start->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            RangeFilter::LTE => $end->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        ]));


        return $this->bookingPointsRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param int $days
     * @param Context $context
     * @param int $lastCheck
     * @param int $limit
     * @return BonusBookingCollection|null
     */
    public function getPointsExpireInDaysUsingLastCheck(int $days, Context $context, int $lastCheck = 1, int $limit = 1000): ?BonusBookingCollection
    {
        $lastCheck = (-1) * $lastCheck;
        $date = (new \DateTime())->modify($lastCheck . ' days')->setTime(0, 0);

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new EqualsFilter('customer.customFields', null),
                new EqualsFilter('customer.customFields.' . self::BONUS_POINTS_EXPIRE_LAST_CHECK, null),
                new RangeFilter('customer.customFields.' . self::BONUS_POINTS_EXPIRE_LAST_CHECK, [
                    RangeFilter::LT => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT)
                ])
            ]
        ));

        if ($limit) {
            $criteria->setLimit($limit);
        }

        $criteria->addAssociation('customer.salutation');

        return $this->getPointsExpireInDays($days, $context, $criteria);
    }

    public function getCurrentUserPoint(CustomerEntity $customerEntity, Context $context): ?BonusUserPointEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerId', $customerEntity->getId()));
        $criteria->setLimit(1);

        return $this->userPointRepository->search($criteria, $context)->getEntities()->first();
    }
}
