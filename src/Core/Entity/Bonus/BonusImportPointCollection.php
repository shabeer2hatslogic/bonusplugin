<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Entity\Bonus;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class BonusImportPointCollection extends EntityCollection
{
  protected function getExpectedClass(): string
  {
    return BonusImportPointEntity::class;
  }
}
