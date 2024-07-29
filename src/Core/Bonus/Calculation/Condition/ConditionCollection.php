<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus\Calculation\Condition;

use Shopware\Core\Framework\Struct\Collection;

class ConditionCollection extends Collection
{
    public function filterType(int $type): ConditionCollection
    {
        return $this->filter(
            fn(Condition $condition) => $condition->getType() === $type
        );
    }

    public function filterWithoutTypes(array $types): ConditionCollection
    {
        return $this->filter(
            fn(Condition $condition) => !in_array($condition->getType(), $types)
        );
    }

    public function filterOutdated(): ConditionCollection
    {
        $today = new \DateTime();

        return $this->filter(
            function (Condition $condition) use ($today): bool {
                $validFrom = $condition->getValidFrom();
                $validUntil = $condition->getValidUntil();

                if ($validFrom && $validUntil && ($validFrom > $today || $validUntil < $today)) {
                    return false;
                } elseif ($validFrom && $validFrom > $today) {
                    return false;
                } elseif ($validUntil && $validUntil < $today) {
                    return false;
                }
                /**echo "true: ".$condition->getName()." <br />";
                echo 'Today: '.$today->format('d.m.Y H:i:s').'<br />';
                if ($validFrom) {
                    echo 'validFrom: ' . $validFrom->format('d.m.Y H:i:s') . '<br />';
                }
                if ($validUntil) {
                    echo 'validUntil: ' . $validUntil->format('d.m.Y H:i:s') . '<br />';
                }*/

                return true;
            }
        );
    }

    public function filterSubType(int $subType): ConditionCollection
    {
        return $this->filter(
            fn(Condition $condition) => $condition->getSubType() === $subType
        );
    }

    public function sortByFactor($expression = 'asc') {
        $conditionsByFactor = [];

        foreach($this->elements as $element) {
            $conditionsByFactor[$element->getFactor()] = $element;
        }

        if ($expression == 'asc') {
            ksort($conditionsByFactor);
        } else {
            krsort($conditionsByFactor);
        }

        return $conditionsByFactor;
    }
}
