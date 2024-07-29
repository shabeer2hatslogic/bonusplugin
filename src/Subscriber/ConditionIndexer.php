<?php declare(strict_types=1);

namespace CustomBonusSystem\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use CustomBonusSystem\Core\Bonus\Calculation\Condition\ConditionService;
use CustomBonusSystem\Core\Content\Condition\ConditionEvents;
use CustomBonusSystem\Core\Content\Condition\DataAbstractionLayer\ConditionIndexingMessage;
use CustomBonusSystem\Core\Content\Condition\Event\ConditionIndexerEvent;
use CustomBonusSystem\Core\Entity\Bonus\BonusConditionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ConditionIndexer extends EntityIndexer implements EventSubscriberInterface
{
    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        IteratorFactory $iteratorFactory,
        EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $repository,
        private readonly ConditionService $conditionService
    ) {
        $this->iteratorFactory = $iteratorFactory;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConditionEvents::CONDITION_WRITTEN_EVENT => 'onConditionWritten',
        ];
    }

    public function onConditionWritten(EntityWrittenEvent $event): void
    {
        $this->conditionService->resetCache();
    }

    public function getName(): string
    {
        return 'custom_bonus_system_condition.indexer';
    }

    public function iterate($offset): ?EntityIndexingMessage
    {
        $iterator = $this->iteratorFactory->createIterator($this->repository->getDefinition(), $offset);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        return new ConditionIndexingMessage(array_values($ids), $iterator->getOffset());
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        $updates = $event->getPrimaryKeys(BonusConditionDefinition::ENTITY_NAME);

        if (empty($updates)) {
            return null;
        }

        $this->handle(new ConditionIndexingMessage(array_values($updates), null, $event->getContext()));

        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
        $ids = $message->getData();

        $ids = array_unique(array_filter($ids));
        if ($ids === []) {
            return;
        }

        //$this->payloadUpdater->update($ids);

        $this->eventDispatcher->dispatch(new ConditionIndexerEvent($ids, $message->getContext()));
    }

    public function getTotal(): int
    {
        return $this->iteratorFactory->createIterator($this->repository->getDefinition())->fetchCount();
    }

    public function getDecorated(): EntityIndexer
    {
        throw new DecorationPatternException(static::class);
    }
}
