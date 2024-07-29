<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Entity\Bonus;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class BonusUserPointCollection extends EntityCollection
{
  protected function getExpectedClass(): string
  {
    return BonusUserPointEntity::class;
  }
}
