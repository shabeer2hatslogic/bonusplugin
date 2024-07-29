<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Content\Condition\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ConditionIndexerEvent extends NestedEvent
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(private readonly array $ids, Context $context)
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
