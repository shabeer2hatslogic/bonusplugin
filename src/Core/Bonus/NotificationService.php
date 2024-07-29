<?php declare(strict_types=1);

namespace CustomBonusSystem\Core\Bonus;

use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\ParameterBag;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use CustomBonusSystem\CustomBonusSystem;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class NotificationService
{
    /**
     * @var AbstractMailService
     */
    protected AbstractMailService $mailService;

    /**
     * @var EntityRepository
     */
    protected EntityRepository $mailTemplateRepository;

    /**
     * @param AbstractMailService $mailService
     * @param EntityRepository $mailTemplateRepository
     */
    public function __construct(AbstractMailService $mailService, EntityRepository $mailTemplateRepository)
    {
        $this->mailService = $mailService;
        $this->mailTemplateRepository = $mailTemplateRepository;
    }

    /**
     * @param CustomerEntity $customer
     * @param int $currentPoints
     * @param int $pointsExpire
     * @param int $days
     * @param Context $context
     * @return void
     */
    public function pointsExpireNotification(CustomerEntity $customer, int $currentPoints, int $pointsExpire, int $days, Context $context): void
    {
        $data = new ParameterBag();

        $data->set('salesChannelId', $customer->getSalesChannelId());
        $data->set('customerId', $customer->getId());
        $data->set('recipients', [ $customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName() ]);

        $templateData = [
            'days'                  => $days,
            'customer'              => $customer,
            'salesChannel'          => $customer->getSalesChannel(),
            'numberPoints'          => $currentPoints,
            'numberPointsExpire'    => $pointsExpire
        ];

        $this->sendMail(CustomBonusSystem::POINTS_EXPIRATION_NOTIFICATION_TECHNICAL_NAME, $data, $context, $templateData);
    }

    /**
     * @param string $technicalName
     * @param ParameterBag $data
     * @param Context $context
     * @param array $templateData
     * @param string $senderName
     * @return void
     */
    public function sendMail(string $technicalName, ParameterBag $data, Context $context, array $templateData = [], string $senderName = ''): void
    {
        $mailTemplate = $this->getMailTemplate($context, $technicalName);
        $data->set('senderName', $mailTemplate->getTranslation('senderName'));
        $data->set('contentHtml', $mailTemplate->getTranslation('contentHtml'));
        $data->set('contentPlain', $mailTemplate->getTranslation('contentPlain'));
        $data->set('subject', $mailTemplate->getSubject());
        $data->set('technicalTemplateTypeName', $mailTemplate->getMailTemplateType()->getTechnicalName());

        if ($senderName) {
            $data->set('senderName', $senderName);
        }

        $this->mailService->send($data->all(), $context, $templateData);
    }

    /**
     * @param Context $context
     * @param string $technicalName
     * @return MailTemplateEntity|null
     */
    private function getMailTemplate(Context $context, string $technicalName): ?MailTemplateEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', $technicalName));
        $criteria->addAssociation('mailTemplateType');
        $criteria->setLimit(1);

        /** @var MailTemplateEntity|null $mailTemplate */
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();

        return $mailTemplate;
    }
}