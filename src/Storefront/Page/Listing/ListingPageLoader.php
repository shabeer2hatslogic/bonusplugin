<?php

declare(strict_types=1);


namespace CustomBonusSystem\Storefront\Page\Listing;

use CustomBonusSystem\Core\Bonus\BonusService;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ListingPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var GenericPageLoader
     */
    private $genericLoader;

    public function __construct(
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        private readonly BonusService $bonusService
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
    }

    /**
     * @param Request $request
     * @param SalesChannelContext $context
     *
     * @return ListingPage
     *
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $context, $withVariantFix = false): ListingPage
    {
        $page = $this->genericLoader->load($request, $context);
        /** @var ListingPage $page */
        $page = ListingPage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,nofollow');
        }

        $listing = $this->loadBonus($request, $context);

        $page->setListing($listing);

        $this->eventDispatcher->dispatch(
            new ListingPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function loadBonus(Request $request, SalesChannelContext $context): EntitySearchResult
    {
        $criteria = $this->createCriteria($request, $context);

        return $this->bonusService->getBonusForUser($context, $criteria);
    }

    private function createCriteria(Request $request, SalesChannelContext $context): Criteria
    {
        $limit = 10;
        if ($request->request->has('limit')) {
            $limit = (int) $request->request->get('limit');
        }

        $page = 1;
        if ($request->request->has('p')) {
            $page = (int) $request->request->get('p');
        }

        $customerId = $context->getCustomer() ? $context->getCustomer()->getId() : Uuid::randomHex();

        return (new Criteria())
            ->setLimit($limit)
            ->addFilter(new EqualsFilter('custom_bonus_system_booking.customerId', $customerId))
            ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING))
            ->setOffset(($page - 1) * $limit)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }
}
