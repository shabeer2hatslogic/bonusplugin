<?php

declare(strict_types=1);

namespace CustomBonusSystem\Controller\Api;

use Doctrine\DBAL\Driver\Exception;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Doctrine\DBAL\Connection;
use CustomBonusSystem\Core\Bonus\BonusService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class BonusSystemApiController extends AbstractController
{
    public function __construct(private readonly EntityRepository $customerRepository, private readonly BonusService $bonusService, private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory, private readonly Connection $connection, private readonly EntityRepository $bonusSystemUserPointRepository, private readonly EntityRepository $salesChannelRepository)
    {
    }

    #[Route(path: '/api/v{version}/custom-bonus-system/get-bonus-points/{customerNumber}', name: 'api.action.custom-bonus-system.get-bonus-points', methods: ['GET'])]
    public function getBonusPoints(Request $request): JsonResponse
    {
        $customerNumber = '';
        if ($request->attributes->has('customerNumber')) {
            $customerNumber = (string) $request->attributes->get('customerNumber');
        }

        $customer = $this->getCustomerByNumber($customerNumber);
        if (!$customer instanceof CustomerEntity) {
            throw new InvalidRequestParameterException('customerNumber');
        }

        return $this->getPointsJsonReponse($customer);
    }


    #[Route(path: '/api/v{version}/custom-bonus-system/get-bonus-bookings/{customerNumber}', name: 'api.action.custom-bonus-system.get-bonus-bookings', methods: ['GET'])]
    public function getBonusBookings(Request $request): JsonResponse
    {
        $customerNumber = '';
        if ($request->attributes->has('customerNumber')) {
            $customerNumber = (string) $request->attributes->get('customerNumber');
        }

        $customer = $this->getCustomerByNumber($customerNumber);
        if (!$customer instanceof CustomerEntity) {
            throw new InvalidRequestParameterException('customerNumber');
        }

        $from = $request->get('startDate');
        $to = $request->get('endDate');

        $salesChannelContext = $this->getSalesChannelContextForCustomer($customer);
        $bookingEntities = $this->bonusService->getBonusForUser($salesChannelContext, new Criteria(), $from, $to)->getEntities();

        $bookings = [];
        foreach ($bookingEntities as $booking) {
            $booking = json_decode(json_encode($booking), true);
            unset($booking['customer'], $booking['extensions'], $booking['salesChannel']);
            $bookings[] = $booking;
        }
        return new JsonResponse(
            [
                'customerNumber' => $salesChannelContext->getCustomer()->getCustomerNumber(),
                'bookings' => $bookings
            ]
        );
    }


    #[Route(path: '/api/v{version}/custom-bonus-system/create-bonus-booking', name: 'api.action.custom-bonus-system.create-bonus-booking', methods: ['POST'])]
    public function createBonusBooking(Request $request): JsonResponse
    {
        $customerNumber = '';
        if ($request->request->has('customerNumber')) {
            $customerNumber = (string) $request->request->get('customerNumber');
        }

        $customer = $this->getCustomerByNumber($customerNumber);
        if (!$customer instanceof CustomerEntity) {
            throw new InvalidRequestParameterException('customerNumber');
        }

        $description = '';
        if ($request->request->has('description')) {
            $description = (string) $request->request->get('description');
        }

        if ($description === '' || $description === '0') {
            throw new MissingRequestParameterException('description');
        }

        $points = 0;
        if ($request->request->has('points')) {
            $points = (int) $request->request->get('points');
        }

        if ($points === 0) {
            throw new MissingRequestParameterException('points');
        }

        $salesChannelId = '';
        if ($request->request->has('salesChannelId')) {
            $salesChannelId = (string) $request->request->get('salesChannelId');
        }

        if (!$salesChannelId) {
            $salesChannelId = $customer->getSalesChannelId();
        }
        $this->bonusService->addApprovedBookingToCustomerAccount($points, $customer->getId(), $description, $salesChannelId, Context::createDefaultContext());

        return $this->getPointsJsonReponse($customer);
    }

    /**
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    /**
     *
     * @param RequestDataBag $requestDataBag
     * @param Context $context
     * @return JsonResponse
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    #[Route(path: '/api/_action/custom/get-top-earned-points-customers', name: 'api.CustomBonusSystem.getTopEarnedPointsCustomers', methods: ['POST'])]
    public function getTopEarnedPointsCustomers(RequestDataBag $requestDataBag, Context $context): JsonResponse
    {
        $limit = ($requestDataBag->has('limit')) ? $requestDataBag->get('limit') : 5;

        return new JsonResponse([
            'items' => $this->getTopEarnedCustomers($context, $limit)
        ]);
    }

    #[Route(path: '/api/_action/custom/get-top-credit-points-customers', name: 'api.CustomBonusSystem.getTopCreditPointsCustomers', methods: ['POST'])]
    public function getTopCreditPointsCustomers(RequestDataBag $requestDataBag, Context $context): JsonResponse
    {
        $limit = ($requestDataBag->has('limit')) ? $requestDataBag->get('limit') : 5;

        return new JsonResponse([
            'items' => $this->getTopCreditCustomers($context, $limit)
        ]);
    }

    /**
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    /**
     *
     * @param RequestDataBag $requestDataBag
     * @param Context $context
     * @return JsonResponse
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    #[Route(path: '/api/_action/custom/get-sum-points-sales-channels', name: 'api.CustomBonusSystem.getSumPointsSalesChannels', methods: ['POST'])]
    public function getSumPointsSalesChannels(RequestDataBag $requestDataBag, Context $context): JsonResponse
    {
        $limit = ($requestDataBag->has('limit')) ? $requestDataBag->get('limit') : 5;
        $filter = ($requestDataBag->has('filter')) ? $requestDataBag->get('filter') : 'all';

        return new JsonResponse([
            'items' => $this->getSumSalesChannels($context, $filter, $limit)
        ]);
    }

    /**
     * @return void
     */
    #[Route(path: '/api/custom-bonus-system/reset-bonus-bookings', name: 'api.action.custom-bonus-system.reset-bonus-bookings', methods: ['POST'])]
    public function resetBonusBookings(Request $request, Context $context): JsonResponse
    {
        $customer = null;
        $customerNumber = $request->get('customerNumber');

        if ($customerNumber) {
            $customer = $this->getCustomerByNumber($customerNumber);
            if (!$customer instanceof CustomerEntity) {
                throw new InvalidRequestParameterException('customerNumber');
            }
        }

        $customerId  = $customer ? $customer->getId() : null;
        $description = $request->get('description', '');

        $this->bonusService->resetBonusBookings($context, $description, $customerId);

        return new JsonResponse(
            [
                'status' => Response::HTTP_OK,
                'success' => true
            ],
            Response::HTTP_OK
        );
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getSumSalesChannels(Context $context, string $filter = 'all', int $limit = 5): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->addSelect('HEX(b.sales_channel_id) AS salesChannelId')
            ->addSelect('SUM(CASE WHEN b.points > 0 AND b.approved = 1 THEN b.points END) AS earned')
            ->addSelect('SUM(CASE WHEN b.points < 0 THEN b.points END) AS spent')
            ->addSelect('SUM(CASE WHEN b.points > 0 AND b.approved = 0 THEN b.points END) AS notApproved')
            ->addSelect('SUM(CASE WHEN b.approved = 1 THEN b.points END) AS credit')
            ->from('custom_bonus_system_booking', 'b')
            ->groupBy('b.sales_channel_id')
            ->orderBy('earned', 'DESC')
            ->setMaxResults($limit);

        if ($filter && ($filter !== 'all' && $filter !== 'yesterday')) {
            $qb->andWhere('b.created_at >= DATE_SUB(CURRENT_TIMESTAMP(), INTERVAL '. $filter .' DAY)');
        }

        if ($filter === 'yesterday') {
            $qb->andWhere('b.created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY)');
        }

        $results = $qb->execute()->fetchAllAssociative();
        return array_map(fn($item) => ['salesChannel' => $this->getSalesChannelById($item['salesChannelId'], $context), ...$item], $results);
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getTopEarnedCustomers(Context $context, int $limit = 5): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->addSelect('HEX(b.customer_id) AS customerId')
            ->addSelect('SUM(CASE WHEN b.points > 0 AND b.approved = 1 THEN b.points END) AS earned')
            ->addSelect('SUM(CASE WHEN b.points < 0 THEN b.points END) AS spent')
            ->from('custom_bonus_system_booking', 'b')
            ->groupBy('b.customer_id')
            ->orderBy('earned', 'DESC')
            ->setMaxResults($limit);

        $results = $qb->execute()->fetchAllAssociative();
        return array_map(fn($item) => ['customer' => $this->getCustomerById($item['customerId'], $context), ...$item], $results);
    }

    public function getTopCreditCustomers(Context $context, int $limit = 5): EntityCollection
    {
        $criteria = new Criteria();
        $criteria
            ->addAssociation('customer')
            ->setLimit($limit)
            ->addSorting(new FieldSorting('points', FieldSorting::DESCENDING));
        return $this->bonusSystemUserPointRepository->search($criteria, $context)->getEntities();
    }

    protected function getCustomerById(string $customerId, Context $context): ?CustomerEntity
    {
        $criteria = new Criteria([strtolower($customerId)]);
        return $this->customerRepository->search($criteria, $context)->getEntities()->first();
    }

    protected function getSalesChannelById(string $salesChannelId, Context $context): ?SalesChannelEntity
    {
        $criteria = new Criteria([strtolower($salesChannelId)]);
        $criteria->addAssociation('type');
        return $this->salesChannelRepository->search($criteria, $context)->getEntities()->first();
    }

    protected function getPointsJsonReponse($customer): JsonResponse
    {
        $salesChannelContext = $this->getSalesChannelContextForCustomer($customer);
        $points = $this->bonusService->getBonusSumForUser($salesChannelContext);

        return new JsonResponse(
            [
                'customerNumber' => $salesChannelContext->getCustomer()->getCustomerNumber(),
                'points' => $points
            ]
        );
    }

    private function getCustomerByNumber(string $customerNumber): ?CustomerEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customerNumber', $customerNumber));
        return $this->customerRepository->search($criteria, Context::createDefaultContext())->first();
    }

    private function getSalesChannelContextForCustomer(CustomerEntity $customer): SalesChannelContext
    {
        return $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $customer->getSalesChannelId(),
            [
                SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
            ]
        );
    }
}
