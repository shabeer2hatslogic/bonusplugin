<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Entity\Bonus;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BonusUserPointEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var int
     */
    protected $points;

    /**
     * @var bool
     */
    protected $canSendPointsExpirationNotification = true;

    /**
     * @var string
     */
    protected $customerId;

    /**
     * @var CustomerEntity
     */
    protected $customer;

    /**
     * @var ?\DateTimeInterface
     */
    protected $lastCheckedAt;

    public function hasPoints(): bool
    {
        return $this->points > 0;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): void
    {
        $this->points = $points;
    }

    /**
     * @return bool
     */
    public function isCanSendPointsExpirationNotification(): bool
    {
        return $this->canSendPointsExpirationNotification;
    }

    /**
     * @param bool $canSendPointsExpirationNotification
     * @return void
     */
    public function setCanSendPointsExpirationNotification(bool $canSendPointsExpirationNotification): void
    {
        $this->canSendPointsExpirationNotification = $canSendPointsExpirationNotification;
    }

    /**
     * @return string
     */
    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return CustomerEntity
     */
    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    /**
     * @param CustomerEntity $customer
     */
    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getLastCheckedAt(): ?\DateTimeInterface
    {
        return $this->lastCheckedAt;
    }

    public function setLastCheckedAt(?\DateTimeInterface $lastCheckedAt): void
    {
        $this->lastCheckedAt = $lastCheckedAt;
    }
}
