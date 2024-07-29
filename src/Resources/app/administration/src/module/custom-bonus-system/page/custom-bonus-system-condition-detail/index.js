import template from './custom-bonus-system-condition-detail.html.twig';
import errorConfig from './error-config.json';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

Component.register('custom-bonus-system-condition-detail', {
    template,

    inject: [
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('condition-detail')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    props: {
        conditionId: {
            type: String,
            required: false,
            default: null
        },
    },

    data() {
        return {
            condition: null,
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
        identifier() {
            return this.placeholder(this.condition, 'name');
        },

        conditionCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            return criteria;
        },

        conditionIsLoading() {
            return this.isLoading || this.condition == null;
        },

        conditionRepository() {
            return this.repositoryFactory.create('custom_bonus_system_condition');
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

        ...mapPageErrors('condition', errorConfig['custom.bonus.system.condition.detail'].condition)
    },

    watch: {
        conditionId() {
            this.createdComponent();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (this.conditionId) {
                this.loadEntityData();
                return;
            }

            this.condition = this.conditionRepository.create(Shopware.Context.api);

            if (this.condition.subType === undefined) {
                this.condition.subType = 1;
            }
        },

        loadEntityData() {
            this.isLoading = true;

            this.conditionRepository.get(this.conditionId, Shopware.Context.api, this.conditionCriteria).then((condition) => {
                this.isLoading = false;
                this.condition = condition;

                if (this.condition.subType === undefined) {
                    this.condition.subType = 1;
                }
            });
        },

        onSave() {
            this.isLoading = true;
            this.condition.type = parseInt(this.condition.type, 10);
            this.condition.subType = parseInt(this.condition.subType, 10);
            this.conditionRepository.save(this.condition, Shopware.Context.api).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                if (this.conditionId === null) {
                    this.$router.push({ name: 'custom.bonus.system.conditiondetail', params: { id: this.condition.id } });
                    return;
                }

                this.loadEntityData();
            }).catch((exception) => {
                this.isLoading = false;
                const conditionName = this.condition.name;
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: this.$tc(
                        'global.notification.notificationSaveErrorMessage', 0, { entityName: conditionName }
                    )
                });
                throw exception;
            });
        },

        onCancel() {
            this.$router.push({ name: 'custom.bonus.system.condition' });
        }
    }
});
