<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\BonusProduct;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Doctrine\DBAL\Connection;
use CustomBonusSystem\Core\Entity\Bonus\BonusProductEntity;
use CustomBonusSystem\Core\Events\GetBonusProductsCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BonusProductService
{
    /**
     * @var Connection
     */
    private $connection;

    protected $bonusProductSettingsForProductId = [];

    private readonly EventDispatcherInterface $dispatcher;

    private $bonusProducts;

    /**
     * @var SessionInterface
     */
    private SessionInterface $session;

    private bool $onlyBuyableWithPoints = false;

    public function __construct(
        Connection $connection,
        private readonly EntityRepository $bonusProductRepository,
        private readonly ProductListingLoader $productListingLoader,
        private readonly EntityRepository $optionRepository,
        private $sortingRepository,
        EventDispatcherInterface $dispatcher,
        RequestStack $requestStack
    ) {
        $this->connection = $connection;
        $this->dispatcher = $dispatcher;

        $this->session = new Session();
        if ($requestStack->getMainRequest() && $requestStack->getMainRequest()->hasSession()) {
            $this->session = $requestStack->getMainRequest()->getSession();
        }
    }

    /**
     * Get one bonus product by productId
     * @param SalesChannelContext $context
     * @return mixed|null
     */
    public function getBonusProduct(SalesChannelContext $context, string $productId) {
        $this->bonusProducts = $this->getActiveBonusProducts($context->getContext());

        if ($this->bonusProducts && count($this->bonusProductSettingsForProductId) == 0) {
            /** @var BonusProduct $bonusProduct */
            foreach($this->bonusProducts as $bonusProduct) {
                $this->storeBonusProductSetting($bonusProduct);
            }
        }
        if (count($this->bonusProductSettingsForProductId) > 0) {
            return $this->getBonusProductSettingsForProductId($productId);
        }

        return null;
    }

    /**
     * Get Settings for a bonus product
     * @param $productId
     * @return mixed|null
     */
    public function getBonusProductSettingsForProductId($productId)
    {
        if (array_key_exists($productId, $this->bonusProductSettingsForProductId)) {
            return $this->bonusProductSettingsForProductId[$productId];
        } else {
            return null;
        }
    }

    /**
     * Read all active bonus products from bonusProductRepository. It's active if active flag is set and current date
     * is in optional start-/enddate range
     * @param $context
     * @return array
     */
    protected function getActiveBonusProducts($context) {
        $today = new \DateTime();
        $todayDate = $today->format('Y-m-d H:i:s');

        $dateRangeFilter1 = new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new RangeFilter('custom_bonus_system_bonus_product.validFrom', ['lte' => $todayDate]),
                new EqualsFilter('custom_bonus_system_bonus_product.validFrom', null)
            ]
        );

        $dateRangeFilter2 = new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new RangeFilter('custom_bonus_system_bonus_product.validUntil', ['gte' => $todayDate]),
                new EqualsFilter('custom_bonus_system_bonus_product.validUntil', null)
            ]
        );

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('custom_bonus_system_bonus_product.active', true))
            ->addFilter($dateRangeFilter1)
            ->addFilter($dateRangeFilter2);

        return $this->bonusProductRepository->search($criteria, $context)->getEntities()->getElements();
    }

    /**
     * Store bonus product settings for a product id
     * @param $bonusProduct
     */
    protected function storeBonusProductSetting($bonusProduct)
    {
        $this->bonusProductSettingsForProductId[$bonusProduct->getProductId()] = [
            'id' => $bonusProduct->getId(),
            'type' => $bonusProduct->getType(),
            'value' => $bonusProduct->getValue(),
            'maxOrderQuantity' => $bonusProduct->getMaxOrderQuantity(),
            'onlyBuyableWithPoints' => $bonusProduct->isOnlyBuyableWithPoints(),
        ];
    }

    /**
     * Add some bonus specific properties to product
     * @param $product
     * @param $points
     * @param $onlyBuyableWithPoints
     * @param $maxOrderQuantity
     */
    public function enrichProductWithBonusProductData($product, $points, $onlyBuyableWithPoints): void
    {
        $product->customBonusSystemBonusProduct = true;
        // need ceil so that there are no comma bonus points
        $product->customBonusSystemPointCosts = ceil($points);
        $product->customBonusSystemOnlyBuyableWithPoints = $onlyBuyableWithPoints;
    }

    /**
     * @param SalesChannelProductEntity $product
     * @param int|null $maxOrderQuantity
     * @param bool|null $onlyBuyableWithPoints
     * @return void
     */
    public function overrideMaxOrderQuantityForBonusProduct(SalesChannelProductEntity $product, ?int $maxOrderQuantity = 0, ?bool $onlyBuyableWithPoints = false): void
    {
        $sessionKey = BonusProductEntity::BUY_WITH_POINTS_ONLY_SESSION_KEY . '-' . $product->getId();
        if ($this->session->has($sessionKey)) {
            $this->onlyBuyableWithPoints = $this->session->get($sessionKey);
            $this->session->remove($sessionKey);
        }

        $product->customBonusSystemOnlyBuyableWithPointsChecked = $this->onlyBuyableWithPoints;

        // override default max. order quantity by bonus product max. order quantity
        if ($maxOrderQuantity && ($onlyBuyableWithPoints || $this->onlyBuyableWithPoints)) {
            $product->setCalculatedMaxPurchase($maxOrderQuantity);
        }
    }

    /**
     * Get all active bonus products for product listing. It's active if active flag is set and current date is in optional
     * start-/enddate range
     *
     * @param SalesChannelContext $context
     * @param null $limit
     * @param int $page
     * @param false $isFilter
     * @param null $request
     * @return mixed|ProductSearchResultEvent|ProductListingResult|void
     * @throws InconsistentCriteriaIdsException
     */
    public function getBonusProducts(SalesChannelContext $context, $limit = null, $page = 1, $isFilter = false, $request = null)
    {
        $productIds = [];

        $criteria = new Criteria();
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addFilter(new EqualsFilter('product.active', true));

        $this->bonusProducts = $this->getActiveBonusProducts($context->getContext());

        if ($this->bonusProducts) {
            /** @var BonusProduct $bonusProduct */
            foreach($this->bonusProducts as $bonusProduct) {
                $this->storeBonusProductSetting($bonusProduct);
                $productIds[] = $bonusProduct->getProductId();
            }
        } else {
            // Hotfix: Otherwise all products are shown. Return not possible here, because of criteria event at end of method.
            // Maybe there are products by other plugins added bellow.
            $criteria->addFilter(new EqualsFilter('product.name', 'bs-no-product-found'));
        }
        if ($productIds !== []) {
            $criteria->addFilter(new EqualsAnyFilter('product.id', $productIds));
        }

        if ($limit) {
            $criteria = $criteria->setLimit($limit);
        }

        if ($isFilter) {

            $criteriaSorting = new Criteria();

            if ($this->sortingRepository != null) {
                $sortList = $this->sortingRepository->search($criteriaSorting, $context->getContext())->getEntities();
            } else {
                $sortList = null;
            }

            $currentSorting = $this->getCurrentSorting($request, $context);
            $currentSortingKey = (empty($currentSorting)) ? null : $currentSorting->getKey();
            $currentSortingFields = (empty($currentSorting)) ? null : $currentSorting->getFields();

            if (str_contains((string) Kernel::SHOPWARE_FALLBACK_VERSION, '6.3') ||
                str_contains((string) Kernel::SHOPWARE_FALLBACK_VERSION, '6.4')) {
                if (!empty($currentSortingFields)) {
                    $criteria = $criteria->addSorting(new FieldSorting($currentSortingFields[0]['field'],
                            $currentSortingFields[0]['order'],
                            (bool)$currentSortingFields[0]['naturalSorting'])
                    );
                }
            } elseif (!empty($currentSortingFields)) {
                foreach ($currentSortingFields as $field => $order) {
                    $criteria = $criteria->addSorting(new FieldSorting($field, $order));
                }
            }

            $criteria = $criteria->addAggregation(
                new EntityAggregation('manufacturer', 'product.manufacturerId', 'product_manufacturer')
            );

            $criteriaGroup = new Criteria();
            $criteriaGroup = $criteriaGroup->addAssociation('group');

            if (str_contains((string) Kernel::SHOPWARE_FALLBACK_VERSION, '6.3') ||
                str_contains((string) Kernel::SHOPWARE_FALLBACK_VERSION, '6.4')
            ) {
                $criteriaGroup = $criteriaGroup->addFilter(new EqualsFilter('group.filterable', true));
            }

            /** @var PropertyGroupOptionCollection $options */
            $options = $this->optionRepository->search($criteriaGroup, $context->getContext())->getEntities();

            // group options by their property-group
            $grouped = $options->groupByPropertyGroups();
            $grouped->sortByPositions();

            if (str_contains((string) Kernel::SHOPWARE_FALLBACK_VERSION, '6.3') ||
                str_contains((string) Kernel::SHOPWARE_FALLBACK_VERSION, '6.4')
            ) {
                $grouped->sortByConfig();
            }

            $ids = $this->getManufacturerIds($request);
            if ($ids !== []) {
                $criteria->addPostFilter(new EqualsAnyFilter('product.manufacturerId', $ids));
            }

            $ids = $this->getPropertyIds($request);
            if ($ids !== []) {
                $groups = $this->connection->fetchAll(
                    'SELECT LOWER(HEX(property_group_id)) as property_group_id, LOWER(HEX(id)) as id
                         FROM property_group_option
                         WHERE id IN (:ids)',
                    ['ids' => Uuid::fromHexToBytesList($ids)],
                    ['ids' => Connection::PARAM_STR_ARRAY]
                );

                $groups = FetchModeHelper::group($groups);

                $filters = [];
                foreach ($groups as $options) {
                    $options = array_column($options, 'id');

                    $filters[] = new MultiFilter(
                        MultiFilter::CONNECTION_OR,
                        [
                            new EqualsAnyFilter('product.propertyIds', $options),
                        ]
                    );
                }

                $criteria->addPostFilter(new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    $filters
                ));
            }

            $prices = $this->getPriceFilter($request);

            if ($prices !== [] && str_contains((string) Kernel::SHOPWARE_FALLBACK_VERSION, '6.4')) {
                $criteria->addPostFilter(new RangeFilter('product.cheapestPrice', $prices));
            } elseif ($prices !== []) {
                $criteria->addPostFilter(new RangeFilter('product.listingPrices', $prices));
            }

        }

        if ($page > 1) {
            $criteria = $criteria->setOffset(($page - 1) * $limit);
        }
        $this->dispatcher->dispatch(new GetBonusProductsCriteriaEvent($criteria, $context));
        $products = $this->productListingLoader->load($criteria, $context);

        if (!empty($grouped)) {
            $aggregations = $products->getAggregations();
            $aggregations->remove('properties');
            $aggregations->remove('configurators');
            $aggregations->remove('options');
            $propertyAggregation = new EntityResult('properties', $grouped);
            $aggregations->add($propertyAggregation);
        }

        $result = ProductListingResult::createFrom($products);

        if (!empty($currentSortingKey)) {
            $result->setSorting($currentSortingKey);
        }

        if (!empty($sortList)) {
            $result->setAvailableSortings($sortList);
        }

        if ($limit) {
            $result->setLimit($limit);
        }

        return $result;
    }

    /**
     * @param Request $request
     * @param SalesChannelContext $context
     * @param null $default
     * @return mixed|null
     */
    private function getCurrentSorting(Request $request, SalesChannelContext $context, $default = null)
    {
        if (str_contains((string) Kernel::SHOPWARE_FALLBACK_VERSION, '6.3') ||
            str_contains((string) Kernel::SHOPWARE_FALLBACK_VERSION, '6.4')
        ) {
            $key = '';
            if ($request->query->has('order')) {
                $key = (string) $request->query->get('order');
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('key', $key));

            if ($this->sortingRepository != null) {
                $sorting = $this->sortingRepository->search($criteria, $context->getContext())->first();
            } else {
                $sorting = null;
            }

            if ($sorting !== null) {
                return $sorting;
            }
        } else {
            $sort = '';
            if ($request->query->has('order')) {
                $sort = (string) $request->query->get('order');
            }

            if (is_string($sort) && !empty($sort)) {
                $request->request->set('order', $sort);

                $request->query->set('sort', null);
                $request->request->set('sort', null);
            }

            $key = $default;
            if ($request->query->has('order')) {
                $key = (string) $request->query->get('order');
            }

            if (!$key) {
                return null;
            }

            return $default;
        }
    }

    
    private function getPriceFilter(Request $request): array
    {
        $min = 0;
        if ($request->query->has('min-price')) {
            $min = (int) $request->query->get('min-price');
        }

        $max = 0;
        if ($request->query->has('max-price')) {
            $max = (int) $request->query->get('max-price');
        }

        $range = [];
        if ($min > 0) {
            $range[RangeFilter::GTE] = $min;
        }
        if ($max > 0) {
            $range[RangeFilter::LTE] = $max;
        }

        return $range;
    }

    
    private function getManufacturerIds(Request $request): array
    {
        $ids = $request->query->get('manufacturer', '');
        $ids = explode('|', (string) $ids);

        return array_filter($ids);
    }

    
    private function getPropertyIds(Request $request): array
    {
        $ids = $request->query->get('properties', '');
        if ($request->isMethod(Request::METHOD_POST)) {
            $ids = $request->request->get('properties', '');
        }

        if (\is_string($ids)) {
            $ids = explode('|', $ids);
        }

        return array_filter($ids);
    }
}
