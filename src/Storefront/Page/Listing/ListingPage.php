<?php declare(strict_types=1);

namespace CustomBonusSystem\Storefront\Page\Listing;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Storefront\Page\Page;

class ListingPage extends Page
{
  /**
   * @var EntitySearchResult
   */
  protected $listing;

  public function getListing(): EntitySearchResult
  {
    return $this->listing;
  }

  public function setListing(EntitySearchResult $listing): void
  {
    $this->listing = $listing;
  }
}
