import template from './custom-bonus-system-customer-detail-info.html.twig';
import './custom-bonus-system-customer-detail-info.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('custom-bonus-system-customer-detail-info', {
    template,

    inject: ['repositoryFactory'],

    props: {
        customer: {
            type: Object,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },
    },

    data() {
        return {
            orderAmount: 0,
            orderCount: 0,
            customerLanguage: null
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        languageId() {
            return this.customer.languageId;
        },

        customerLanguageName() {
            if (this.customerLanguage) {
                return this.customerLanguage.name;
            }
            return '…';
        },

        languageCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('salesChannels.id', this.customer.salesChannelId));

            return criteria;
        }
    },

    watch: {
        languageId: {
            immediate: true,
            handler() {
                this.languageRepository.get(this.languageId, Shopware.Context.api).then((language) => {
                    this.customerLanguage = language;
                });
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
        }
    }
});
