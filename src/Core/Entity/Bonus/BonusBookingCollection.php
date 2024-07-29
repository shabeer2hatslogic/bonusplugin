<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Entity\Bonus;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class BonusBookingCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BonusBookingEntity::class;
    }

    public function getCustomers(): array
    {
        return $this->fmap(function (BonusBookingEntity $bonusBooking) {
            return $bonusBooking->getCustomer();
        });
    }

    public function getCustomerPoints(): array
    {
        $data = [];
        foreach ($this->getElements() as $booking) {
            $points = $booking->getPoints();
            if (isset($data[$booking->getCustomerId()])) {
                $points += $data[$booking->getCustomerId()]['points'];
            }

            $data[$booking->getCustomerId()] = [
                'customer' => $booking->getCustomer(),
                'points' => $points,
            ];
        }

        return $data;
    }
}
