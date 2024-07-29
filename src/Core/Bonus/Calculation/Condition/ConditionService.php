<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Condition;

use Doctrine\DBAL\Connection;
use CustomBonusSystem\Core\Bonus\Calculation\Rule\CategoryRule;
use CustomBonusSystem\Core\Bonus\Calculation\Rule\CustomerGroupRule;
use CustomBonusSystem\Core\Bonus\Calculation\Rule\CustomerNumberRule;
use CustomBonusSystem\Core\Bonus\Calculation\Rule\LineItemInCategoryRule;
use CustomBonusSystem\Core\Bonus\Calculation\Rule\LineItemInStreamRule;
use CustomBonusSystem\Core\Bonus\Calculation\Rule\LineItemRule;
use CustomBonusSystem\Core\Bonus\Calculation\Rule\ProductInCategoryRule;
use CustomBonusSystem\Core\Bonus\Calculation\Rule\ProductRule;
use CustomBonusSystem\Core\Entity\Bonus\BonusConditionEntity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class ConditionService
{
    final public const CONDITIONS_CACHE_KEY = 'custom-bonus-system-all-conditions';

    /**
     * @var Connection
     */
    private $connection;

    /** @var ConditionCollection */
    private $conditionCollection;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    public function __construct(
        Connection $connection,
        TagAwareAdapterInterface $cache
    ) {
        $this->connection = $connection;
        $this->cache = $cache;
    }

    public function resetCache(): void
    {
        $this->conditionCollection = null;
        $this->cache->deleteItem(self::CONDITIONS_CACHE_KEY);
    }

    protected function buildConditions()
    {
        $item = $this->cache->getItem(self::CONDITIONS_CACHE_KEY);

        $conditionCollection = $item->get();
        if ($item->isHit() && $conditionCollection) {
            return $this->conditionCollection = $conditionCollection;
        }

        if ($this->conditionCollection && count($this->conditionCollection->getElements()) > 0) {
            return $this->conditionCollection;
        }
        /**if ($this->conditionCollection) {
            return $this->conditionCollection;
        }*/

        // sub/ add one day, because of caching 24 hours
        $todayStart = new \DateTime();
        $todayStart->sub(new \DateInterval('P1D'));
        $todayStart = $todayStart->format('Y-m-d').' 00:00:00:000';
        $todayEnd = new \DateTime();
        $todayEnd->add(new \DateInterval('P1D'));
        $todayEnd = $todayEnd->format('Y-m-d').' 23:59:59:999';

        // Read all active conditions
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->from('custom_bonus_system_condition')
            ->select('custom_bonus_system_condition.*')
            ->where('custom_bonus_system_condition.active = :active')
            ->setParameter('active', 1)
            ->andWhere('(
                (custom_bonus_system_condition.valid_from IS NULL
              OR
                custom_bonus_system_condition.valid_from <= :todayEnd)
              AND
                (custom_bonus_system_condition.valid_until IS NULL
              OR
                custom_bonus_system_condition.valid_until >= :todayStart)
            )')
            ->setParameter('todayStart', $todayStart)
            ->setParameter('todayEnd', $todayEnd);
        $entries = $queryBuilder->execute()->fetchAll();

        $this->conditionCollection = new ConditionCollection();

        if ($entries) {
            foreach ($entries as $entry) {
                $condition = new Condition($entry);
                $this->conditionCollection->add($condition);
            }
        }

        $item->set($this->conditionCollection);
        $item->expiresAfter(\DateInterval::createFromDateString('24 hour'));
        $this->cache->save($item);

        return $this->conditionCollection;
    }

    public function conditionsExcludePointsForProductMatch(SalesChannelProductEntity $product, $subType = BonusConditionEntity::SUB_TYPE_EXCLUDE_FOR_COLLECT): bool
    {
        $conditions = $this->getConditions($subType, BonusConditionEntity::TYPE_EXCLUDE_PRODUCTS);
        $this->conditionCollection = $conditions;

        if ($conditions) {
            /** @var Condition $condition */
            foreach ($conditions->getElements() as $condition) {
                if ($this->isProductOrProductCategoryExcludedCondition($product, $condition)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function conditionsExcludePointsForLineItemMatch($lineItem, $subType = BonusConditionEntity::SUB_TYPE_EXCLUDE_FOR_COLLECT): bool
    {
        $conditions = $this->getConditions($subType, BonusConditionEntity::TYPE_EXCLUDE_PRODUCTS);
        $this->conditionCollection = $conditions;

        if ($conditions !== null) {
            /** @var Condition $condition */
            foreach ($conditions->getElements() as $condition) {
                if ($this->isLineItemOrLineItemCategoryExcludedCondition($lineItem, $condition)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function determineConditionsForConversionFactor(CustomerEntity $customer = null, $product = null, $subType = BonusConditionEntity::SUB_TYPE_CONVERSION_FACTOR_COLLECT) {
        $conditions = $this->getConditions($subType);
        if (!$conditions instanceof ConditionCollection) {
            return null;
        }

        /** @var Condition $condition */
        foreach ($conditions as $key => $condition) {
            switch ($condition->getType()) {
                case BonusConditionEntity::TYPE_EXCLUDE_PRODUCTS:
                    if (!$product) {
                        $conditions->remove($key);
                        break;
                    }

                    if ($product instanceof ProductEntity && $this->isProductOrCategoryExcludedCondition($product, $condition)) {
                        $productExcludedCondition = new ConditionCollection();
                        $productExcludedCondition->add($condition);
                        return $productExcludedCondition;
                    } else {
                        $conditions->remove($key);
                    }
                    break;

                case BonusConditionEntity::TYPE_INDIVIDUAL_BONUS_FACTOR_FOR_CUSTOMER:
                    if (!$customer || !$this->isCustomerConditionMatch($customer, $condition)) {
                        $conditions->remove($key);
                    }
                    break;

                case BonusConditionEntity::TYPE_INDIVIDUAL_BONUS_FACTOR_FOR_PRODUCT_OR_STREAM:
                    if (!$product) {
                        $conditions->remove($key);
                        break;
                    }

                    if ($product instanceof LineItem && !$this->isLineItemConditionMatch($product, $condition)) {
                        $conditions->remove($key);
                    }

                    if ($product instanceof ProductEntity && !$this->isProductConditionMatch($product, $condition)) {
                        $conditions->remove($key);
                    }
                    break;
            }
        }

        return $conditions;
    }

    /**
     * @param CustomerEntity $customer
     */
    public function isCustomerConditionMatch(CustomerEntity $customer, Condition $condition): bool
    {
        $match1 = (new CustomerGroupRule($condition->getCustomerGroupCondition()))->match($customer->getGroupId());
        $match2 = (new CustomerNumberRule($condition->getCustomerNumberCondition()))->match($customer->getCustomerNumber());
        return ($match1 || $match2);
    }

    /**
     * @param ProductEntity $product
     */
    public function isProductConditionMatch(ProductEntity $product, Condition $condition): bool
    {
        $match1 = (new ProductRule($condition->getProductCondition()))->match($product);
        $match2 = (new LineItemInStreamRule($condition->getStreamCondition()))->match($product->getStreamIds() ?: []);
        return ($match1 || $match2);
    }

    /**
     * @param SalesChannelProductEntity $product
     * @param Condition $condition
     * @return bool
     */
    public function isSalesChannelProductConditionMatch(SalesChannelProductEntity $product, Condition $condition): bool
    {
        if ($condition->getProductCondition() == null) {
            $match1 = false;
        } else {
            $match1 = (new ProductRule($condition->getProductCondition()))->match($product);
        }
        $match2 = (new LineItemInStreamRule($condition->getStreamCondition()))->match($product->getStreamIds() ?: []);
        return ($match1 || $match2);
    }

    /**
     * @param LineItem $lineItem
     */
    public function isLineItemConditionMatch(LineItem $lineItem, Condition $condition): bool
    {
        if ($condition->getProductCondition() == null) {
            $match1 = false;
        } else {
            $match1 = (new LineItemRule($condition->getProductCondition()))->match($lineItem);
        }
        $match2 = (new LineItemInStreamRule($condition->getStreamCondition()))->match($lineItem->getPayloadValue('streamIds') ?: []);
        return ($match1 || $match2);
    }

    /**
     * @param SalesChannelProductEntity $product
     * @param Condition $condition
     * @return bool
     */
    public function isProductOrProductCategoryExcludedCondition(SalesChannelProductEntity $product, Condition $condition): bool
    {
        $match1 = (new ProductInCategoryRule($condition->getCategoryCondition()))->match($product);

        if (!$match1) {
            $match2 = $this->isSalesChannelProductConditionMatch($product, $condition);
        }

        return ($match1 || $match2);
    }

    /**
     * @param LineItem $lineItem
     */
    public function isLineItemOrLineItemCategoryExcludedCondition(LineItem $lineItem, Condition $condition): bool
    {
        $match1 = (new LineItemInCategoryRule($condition->getCategoryCondition()))->match($lineItem);

        if (!$match1) {
            $match2 = $this->isLineItemConditionMatch($lineItem, $condition);
        }

        return ($match1 || $match2);
    }

    /**
     * @param ProductEntity $product
     */
    public function isProductOrCategoryExcludedCondition(ProductEntity $product, Condition $condition): bool
    {
        $match1 = (new ProductRule($condition->getProductCondition()))->match($product);
        $match2 = (new CategoryRule($condition->getCategoryCondition()))->match($product->getCategoryIds());
        return ($match1 || $match2);
    }

    private function getConditions(int $subType, int $type = 0): ?ConditionCollection
    {
        $conditions = $this->buildConditions();
        if ($conditions->count() == 0) {
            return null;
        }

        if ($type !== 0) {
            $conditions = $conditions->filterType($type);
            if ($conditions->count() == 0) {
                return null;
            }
        }

        $conditions = $conditions->filterSubType($subType);
        if ($conditions->count() == 0) {
            return null;
        }

        $conditions = $conditions->filterOutdated();
        if ($conditions->count() == 0) {
            return null;
        }

        return $conditions;
    }
}
