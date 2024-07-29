<?php declare(strict_types=1);


namespace CustomBonusSystem\Core\Content\Flow\Dispatching\Action;

use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Framework\Event\ChangePointsAware;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Routing\Exception\InvalidRequestParameterException;

class ChangePointsAction extends FlowAction implements DelayableAction
{
    public function __construct(private readonly BonusService $bonusService)
    {
    }

    public static function getName(): string
    {
        return 'action.change.bonus.points';
    }

    /**
     * @return string[]
     */
    public function requirements(): array
    {
        return [ChangePointsAware::class, CustomerAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        // config is the "Configuration data" you get after you create a flow sequence
        $config = $flow->getConfig();

        // make sure your "points" data exists
        if (!\array_key_exists('points', $config)) {
            return;
        }

        $points = (int) $config['points'];
        $description = (string) $config['description'];

        // just a step to make sure you are dispatching the correct action
        if (!$flow->hasStore(CustomerAware::CUSTOMER_ID) || empty($points) || empty($description)) {
            return;
        }

        $customer = $flow->getData(CustomerAware::CUSTOMER);
        if (!$customer instanceof CustomerEntity) {
            throw new InvalidRequestParameterException('customerNumber');
        }

        $points = $this->bonusService->handleAmountOfAssignedPoints($customer->getId(), $points, $flow->getContext());
        if ($points === 0) {
            return;
        }

        $this->bonusService->addApprovedBookingToCustomerAccount(
            $points,
            $customer->getId(),
            $description,
            $customer->getSalesChannelId(),
            $flow->getContext()
        );
    }
}