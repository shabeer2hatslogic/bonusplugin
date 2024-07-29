<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Rule;

use Shopware\Core\Checkout\Cart\Exception\PayloadKeyNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;

class ProductInCategoryRule {
    /**
     * @var array
     */
    protected $categoryIds;

    /**
     * @param array|null $categoryIds
     */
    public function __construct(?array $categoryIds = [])
    {
        $this->categoryIds = $categoryIds;
    }

    /**
     * @param SalesChannelProductEntity $product
     * @return bool
     */
    public function match(SalesChannelProductEntity $product): bool
    {
        return $this->matchesOneOfCategory($product);
    }

    /**
     * @throws UnsupportedOperatorException
     * @throws PayloadKeyNotFoundException
     */
    private function matchesOneOfCategory(SalesChannelProductEntity $product): bool
    {
        if (!is_array($this->categoryIds)) {
            return false;
        }

        $categoryIds = (array) $product->getCategoryIds();
        return RuleComparison::uuids($categoryIds, $this->categoryIds, Rule::OPERATOR_EQ);
    }
}
