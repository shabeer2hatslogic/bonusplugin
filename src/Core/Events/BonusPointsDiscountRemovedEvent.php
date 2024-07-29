<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;

class BonusPointsDiscountRemovedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var SalesChannelContext
     */
    protected SalesChannelContext $salesChannelContext;

    /**
     * @param SalesChannelContext $salesChannelContext
     */
    public function __construct(SalesChannelContext $salesChannelContext)
    {
        $this->salesChannelContext = $salesChannelContext;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    /**
     * @return SalesChannelContext
     */
    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}