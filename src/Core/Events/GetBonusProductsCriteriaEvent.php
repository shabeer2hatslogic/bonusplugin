<?php
declare(strict_types = 1);



namespace CustomBonusSystem\Core\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class GetBonusProductsCriteriaEvent extends Event implements ShopwareSalesChannelEvent
{
    protected Criteria $criteria;

    protected SalesChannelContext $context;

    public function __construct(Criteria $criteria, SalesChannelContext $context)
    {
        $this->criteria = $criteria;
        $this->context = $context;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
