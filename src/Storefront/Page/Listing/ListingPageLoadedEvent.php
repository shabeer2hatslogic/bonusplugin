<?php declare(strict_types=1);

namespace CustomBonusSystem\Storefront\Page\Listing;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ListingPageLoadedEvent extends NestedEvent
{
  /**
   * @var ListingPage
   */
  protected $page;

  /**
   * @var SalesChannelContext
   */
  protected $context;

  /**
   * @var Request
   */
  protected $request;

  public function __construct(ListingPage $page, SalesChannelContext $context, Request $request)
  {
    $this->page    = $page;
    $this->context = $context;
    $this->request = $request;
  }

  public function getContext(): Context
  {
    return $this->context->getContext();
  }

  public function getSalesChannelContext(): SalesChannelContext
  {
    return $this->context;
  }

  public function getPage(): ListingPage
  {
    return $this->page;
  }

  public function getRequest(): Request
  {
    return $this->request;
  }
}
