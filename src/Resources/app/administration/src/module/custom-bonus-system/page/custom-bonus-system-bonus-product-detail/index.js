import template from './custom-bonus-system-bonus-product-detail.html.twig';

const {Component, Mixin} = Shopware;
const {Criteria} = Shopware.Data;

Component.register('custom-bonus-system-bonus-product-detail', {
    template,

    inject: [
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('bonus-product-detail')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    props: {
        bonusProductId: {
            type: String,
            required: false,
            default: null
        },
    },

    data() {
        return {
            bonusProduct: null,
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        bonusProductCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            return criteria;
        },

        productCriteria() {
            const criteria = new Criteria();

            criteria.addSorting(Criteria.sort('name', 'ASC', true));

            criteria.addAssociation('options.group');

            criteria.addFilter(
                Criteria.multi(
                    'OR',
                    [
                        Criteria.equals('product.childCount', 0),
                        Criteria.equals('product.childCount', null),
                    ],
                ),
            );

            criteria.addFilter(
                Criteria.equals('product.active', 1),
            );


            return criteria;
        },

        bonusProductIsLoading() {
            return this.isLoading || this.bonusProduct == null;
        },

        bonusProductRepository() {
            return this.repositoryFactory.create('custom_bonus_system_bonus_product');
        },

        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },

        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        },
    },

    watch: {
        bonusProductId() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.bonusProductId) {
                this.loadEntityData();
                return;
            }

            this.bonusProduct = this.bonusProductRepository.create(Shopware.Context.api);
        },

        loadEntityData() {
            this.isLoading = true;

            this.bonusProductRepository.get(this.bonusProductId, Shopware.Context.api, this.bonusProductCriteria).then((bonusProduct) => {
                this.isLoading = false;
                bonusProduct.type = bonusProduct.type.toString();
                this.bonusProduct = bonusProduct;
            });
        },

        onSave() {
            this.isLoading = true;
            this.bonusProduct.type = parseInt(this.bonusProduct.type, 10);
            this.bonusProductRepository.save(this.bonusProduct, Shopware.Context.api).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                if (this.bonusProductId === null) {
                    this.$router.push({
                        name: 'custom.bonus.system.bonusproductdetail',
                        params: {id: this.bonusProduct.id}
                    });
                    return;
                }

                this.loadEntityData();
            }).catch((exception) => {
                this.isLoading = false;
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessage', 0, {entityName: ''}
                    )
                });
                throw exception;
            });
        },

        onCancel() {
            this.$router.push({name: 'custom.bonus.system.bonusproduct'});
        }
    }
});
