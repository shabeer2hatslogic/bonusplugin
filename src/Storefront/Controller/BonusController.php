<?php
declare(strict_types = 1);


namespace CustomBonusSystem\Storefront\Controller;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use CustomBonusSystem\Core\Bonus\ConfigData;
use CustomBonusSystem\Core\Bonus\BonusProduct\BonusProductService;
use CustomBonusSystem\Core\Bonus\BonusService;
use CustomBonusSystem\Core\Bonus\Calculation\CalculationService;
use CustomBonusSystem\Core\Bonus\ConfigService;
use CustomBonusSystem\Core\Checkout\Bonus\BonusProcessor;
use CustomBonusSystem\Core\Entity\Bonus\BonusProductEntity;
use CustomBonusSystem\Core\Events\BonusPointsDiscountRemovedEvent;
use CustomBonusSystem\Storefront\Page\Listing\ListingPageLoader;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class BonusController extends StorefrontController
{
    public $token;
    final const ADD_TO_CART_IDENTIFIER = 'CustomBonusSystemBonusProduct';
    private readonly ConfigService $configService;
    protected ?Struct $bonusSettings = null;

    public function __construct(
        private readonly ListingPageLoader $listingPageLoader,
        private readonly GenericPageLoader $genericPageLoader,
        private readonly BonusService $bonusService,
        protected BonusProductService $bonusProductService,
        private readonly BonusProcessor $bonusProcessor,
        private readonly CalculationService $calculationService,
        ConfigService $configService,
        private readonly CartService $cartService,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->configService = $configService;
    }

    /**
     * Get plugin settings
     *
     * @return ConfigData|Struct
     * @throws \Exception
     */
    protected function getBonusSettings(SalesChannelContext $context)
    {
        if ($this->bonusSettings !== null) {
            return $this->bonusSettings;
        }
        $this->bonusSettings = $this->configService->getConfig($context);

        if (!$this->bonusSettings) {
            throw new \Exception('No bonus system settings found');
        }

        return $this->bonusSettings;
    }

    #[Route(path: '/checkout/bonus-system-product/add', name: 'frontend.checkout.custom-bonus-system.product.add', methods: ['POST'], defaults: ['XmlHttpRequest' => true])]
    public function addBonusProduct(
        Cart $cart,
        RequestDataBag $requestDataBag,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): Response {
        $lineItems = $requestDataBag->get('lineItems');
        if (!$lineItems instanceof RequestDataBag) {
            throw new MissingRequestParameterException('lineItems');
        }

        $count = 0;

        try {
            $items = [];
            /** @var RequestDataBag $lineItemData */
            foreach ($lineItems as $lineItemData) {
                $id = $lineItemData->getAlnum('referencedId');
                $payload = $lineItemData->has('payload') ? $lineItemData->get('payload')->all() : [];
                $stackable = $lineItemData->getBoolean('stackable', true);

                if (isset($payload['buyWithPoints'])) {
                    $id = $this->bonusProductService->getBonusProduct(
                        $salesChannelContext,
                        $id
                    )['id'];
                }

                $lineItem = new LineItem(
                    $id,
                    $lineItemData->getAlnum('type'),
                    $lineItemData->get('referencedId'),
                    $lineItemData->getInt('quantity', 1)
                );

                $lineItem->setStackable($stackable);
                $lineItem->setRemovable($lineItemData->getBoolean('removable', true));
                $lineItem->setPayload($payload);

                $count += $lineItem->getQuantity();

                $items[] = $lineItem;
            }

            $cart = $this->cartService->add($cart, $items, $salesChannelContext);

            if (!$this->traceErrors($cart)) {
                $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', ['%count%' => $count]));
            }
        } catch (ProductNotFoundException) {
            $this->addFlash(self::DANGER, $this->trans('error.addToCartError'));
        }

        return $this->createActionResponse($request);
    }

    private function traceErrors(Cart $cart): bool
    {
        if ($cart->getErrors()->count() <= 0) {
            return false;
        }

        $this->addCartErrors(
            $cart,
            fn(Error $error) => $error->isPersistent()
        );

        return true;
    }

    /**
     * Shows bonus account page of a customer. If not logged in then redirected to login page
     */
    #[Route(path: '/account/bonus', name: 'frontend.CustomBonusSystem.index', options: ['seo' => false], methods: ['GET', 'POST'], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true])]
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $bonusSettings = $this->getBonusSettings($context);
        $settingVars = $bonusSettings->getVars();

        if (!$settingVars['useBonusSystem']) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        $page = $this->listingPageLoader->load($request, $context);

        return $this->renderStorefront(
            '@CustomBonusSystem/storefront/page/account/bonus/index.html.twig',
            [
                'customBonusSystem' => $bonusSettings,
                'page' => $page,
            ]
        );
    }

    /**
     * Show all bonus products if config option is set for customers to purchase defined bonus point products
     */
    #[Route(path: '/bonus-products', name: 'frontend.CustomBonusSystem.listing', options: ['seo' => false], methods: ['GET'], defaults: ['XmlHttpRequest' => true, '_loginRequired' => true])]
    public function listing(SalesChannelContext $context, Request $request): Response
    {
        $bonusSettings = $this->getBonusSettings($context);
        $settingVars = $bonusSettings->getVars();

        if (!$settingVars['useBonusSystem']) {
            throw CartException::customerNotLoggedIn();
        }

        $page = $this->genericPageLoader->load($request, $context);
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,nofollow');
        }

        $perPageLimit = 24;
        $currentPage = (int)$request->query->get('p', 1);

        $bonusProducts = $this->bonusProductService->getBonusProducts($context, $perPageLimit, $currentPage, true, $request);

        return $this->renderStorefront(
            '@CustomBonusSystem/storefront/page/bonus-products/listing.html.twig',
            [
                'page' => $page,
                'searchResult' => $bonusProducts,
                'dataUrl' => '/bonus-products',
                'filterUrl' => '/bonus-products',
                'sidebar' => true
            ]
        );
    }

    /**
     * Shows bonus account page of a customer. If not logged in then redirected to login page
     */
    #[Route(path: '/bonus-system/redeem-points', name: 'frontend.CustomBonusSystem.redeemPoints', options: ['seo' => false], methods: ['POST'], defaults: ['_loginRequired' => true, 'XmlHttpRequest' => true])]
    public function redeemPoints(Cart $cart, SalesChannelContext $context, Request $request): Response
    {
        $settingVars = $this->getBonusSettings($context)->getVars();

        $redirectTo = '';
        if ($request->request->has('redirectTo')) {
            $redirectTo = $request->request->get('redirectTo');
        }

        if (!$settingVars['useBonusSystem']) {
            return $this->redirectToRoute($redirectTo);
        }

        if ($settingVars['disallowRedeemPoints']) {
            return $this->redirectToRoute($redirectTo);
        }
        if (!$settingVars['bonusSystemConversionFactorRedeem']) {
            return $this->redirectToRoute($redirectTo);
        }

        $bonusSystemConversionFactor = $settingVars['bonusSystemConversionFactorRedeem'];
        if ($bonusSystemConversionFactor) {
            $bonusSystemConversionFactor = $this->calculationService->getConversionFactorRedeem($context->getCustomer(), $bonusSystemConversionFactor);
        }

        $basketAmountRedeemRestriction = $settingVars['basketAmountRedeemRestriction'];
        $basketAmountRedeemRestrictionValue = $settingVars['basketAmountRedeemRestrictionValue'];

        $bonusPoints = 0;
        if ($request->request->has('bonuspoints')) {
            $bonusPoints = $request->request->get('bonuspoints');
        }


        $bonusSystemConversionFactorRedeem = 0;
        if ($settingVars['bonusSystemConversionFactorRedeem'] && !$settingVars['disallowRedeemPoints']) {
            $bonusSystemConversionFactorRedeem = $this->calculationService->getConversionFactorRedeem($context->getCustomer(), $settingVars['bonusSystemConversionFactorRedeem']);
        }
        $hasPoints = 0;
        if ($context->getCustomer()) {
            $hasPoints = $this->bonusService->getBonusSumForUser($context);
        }
        if ($context->getCurrentCustomerGroup()->getDisplayGross()) {
            // gross price calculation
            $totalPrice = $cart->getPrice()->getTotalPrice();
        } else {
            // net price calculation
            $totalPrice = $cart->getPrice()->getNetPrice();
        }
        $pointsPossibleAmount = 0;
        if ($bonusSystemConversionFactorRedeem) {
            $pointsPossibleAmount = $this->calculationService->calculateDiscountForBonusPoints(
                $hasPoints,
                $bonusSystemConversionFactorRedeem,
                $context,
                true
            );
        }
        $basketAmountForRedeemPoints = $this->calculationService->getAvailableBasketAmountForRedeemPoints(
            $totalPrice,
            $basketAmountRedeemRestriction,
            $basketAmountRedeemRestrictionValue,
            $pointsPossibleAmount,
            $cart,
            $context,
            false
        );


        if ($this->bonusProcessor->isPointRedeemOk(
            $bonusPoints,
            $cart,
            $bonusSystemConversionFactor,
            $basketAmountRedeemRestriction,
            $basketAmountRedeemRestrictionValue,
            $this->bonusService->getBonusSumForUser($context),
            $context
        )) {
            // Store with $availableBasketAmountForRedeemPoints to prevent negative baskets because of points only for the last cents
            $this->bonusProcessor->storePointRedeem($bonusPoints, BonusProcessor::POINT_REDEEM_BASKET_DISCOUNT, $basketAmountForRedeemPoints);
        }

        return $this->redirectToRoute($redirectTo);
    }

    /**
     * Shows bonus account page of a customer. If not logged in then redirected to login page
     *
     * @param Cart $cart
     */
    #[Route(path: '/bonus-system/cancel-redeem-points', name: 'frontend.CustomBonusSystem.cancelRedeemPoints', options: ['seo' => false], methods: ['POST'])]
    public function cancelRedeemPoints(SalesChannelContext $context, Request $request): Response
    {
        $redirectTo = '';
        if ($request->request->has('redirectTo')) {
            $redirectTo = $request->request->get('redirectTo');
        }

        $this->bonusProcessor->removePointRedeemByType();
        $this->eventDispatcher->dispatch(new BonusPointsDiscountRemovedEvent($context));

        return $this->redirectToRoute($redirectTo);
    }

    /**
     * Show points get on product detailpage for ordering product after changing quantity
     *
     * @throws \Exception
     */
    #[Route(path: '/bonus-system/update-points-by-product-quantity', name: 'custom-bonus-system.update-points-by-product-quantity', defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function updatePointsByProductQuantity(SalesChannelContext $context, Request $request): JsonResponse
    {
        $productId = '';
        if ($request->query->has('product')) {
            $productId = (string) $request->query->get('product');
        }

        $quantity = 1;
        if ($request->query->has('quantity')) {
            $quantity = (int) $request->query->get('quantity');
        }

        $return = new JsonResponse([
            'template' => ''
        ]);

        $bonusSettings = $this->getBonusSettings($context);
        $settingVars = $bonusSettings->getVars();
        if (!$settingVars['useBonusSystem']) {
            return $return;
        }

        $bonusSystemConversionFactor = $settingVars['bonusSystemConversionFactorCollect'];
        if (!$bonusSystemConversionFactor) {
            return $return;
        }

        $product = $this->getSalesChannelProductById($productId, $context);
        if (!$product) {
            return $return;
        }

        $points = 0;

        $bonusSystemConversionFactor = $this->calculationService->getConversionFactorCollect($context->getCustomer(), $bonusSystemConversionFactor, $product);

        $calculatedQuantityPrice = $this->calculationService->calculateProductPriceByQuantity($product, (int)$quantity, $context);
        $points = $this->calculationService->calculateBonusPointsForAmount(
            $calculatedQuantityPrice->getTotalPrice(),
            $bonusSystemConversionFactor,
            $context,
            true,
            $settingVars['collectPointsRound']
        );
        return new JsonResponse([
           'template' => $this->renderStorefront(
                '@Storefront/storefront/component/bonus/product-detail/get-points.html.twig',
                [
                    'customBonusSystemPoints' => $points
                ]
            )->getContent()
        ]);
    }

    /**
     * Sets the checkbox state
     *
     *
     * @throws \Exception
     */
    #[Route(path: '/bonus-system/buy-with-points-checkbox', name: 'custom-bonus-system.buy-with-points-checkbox', defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function buyWithPointsCheckbox(Request $request): JsonResponse
    {
        $state = filter_var($request->query->get('state', false), FILTER_VALIDATE_BOOLEAN);
        $productId = (string) $request->query->get('product', '');

        $session = $request->getSession();
        $sessionKey = BonusProductEntity::BUY_WITH_POINTS_ONLY_SESSION_KEY . '-' . $productId;

        if ($session->has($sessionKey) && !$state) {
            $session->remove($sessionKey);
        }

        if ($state) {
            $session->set($sessionKey, $state);
        }

        return new JsonResponse([
            'state' => $state
        ]);
    }

    #[Route(path: '/account/points-expiration-notification', name: 'frontend.account.pointsExpirationNotification', defaults: ['XmlHttpRequest' => true, '_loginRequired' => true], methods: ['POST'])]
    public function pointsExpirationNotification(Request $request, SalesChannelContext $context, CustomerEntity $customer): Response
    {
        $status = (bool) $request->get('option', false);

        $customBonusSystemUserPoint = $data = [
            'id' => Uuid::randomHex(),
            'customerId' => $customer->getId(),
            'canSendPointsExpirationNotification' => $status
        ];

        if ($customer->hasExtension('customBonusSystemUserPoint')) {
            $customBonusSystemUserPoint = $customer->getExtension('customBonusSystemUserPoint')->getVars();

            $customBonusSystemUserPoint['canSendPointsExpirationNotification'] = $status;
            $data['id'] = $customBonusSystemUserPoint['id'];
        }

        $customer->addExtension('customBonusSystemUserPoint', new ArrayStruct($customBonusSystemUserPoint));
        $this->bonusService->updateUserPointsEntity([$data], $context->getContext());

        return $this->renderStorefront('@Storefront/storefront/page/account/points-expiration-notification-subscribe.html.twig', [
                'customer' => $customer,
                'pointsExpirationNotification' => [
                    'messages' => [
                        'success' => [
                            'type' => 'success',
                            'text' => $this->trans('custom-bonus-system.account.pointsAccount.pointsExpiration.notification.message')
                        ]
                    ]
                ]
            ]
        );
    }

    /**
     * Returns the sales channel product entity by ID
     */
    private function getSalesChannelProductById(string $productId, SalesChannelContext $context): SalesChannelProductEntity
    {
        $criteria = new Criteria([$productId]);
        return $this->salesChannelProductRepository->search($criteria, $context)->getEntities()->first();
    }
}
