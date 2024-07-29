import template from './custom-bonus-system-dashboard.html.twig'
import './custom-bonus-system-dashboard.scss';

const { Component } = Shopware;

Component.register('custom-bonus-system-dashboard', {
    template,

    inject: [
        'customBonusSystemApiService'
    ],

    data() {
        return {
            isLoading: false,
            salesChannelsIsLoading: false,
            filterValue: 'all',
            sumPointsSalesChannels: null,
            topEarnedPointsCustomers: null,
            topCreditPointsCustomers: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    created() {
        this.initSumPointsSalesChannels();
        this.initTopEarnedPointsCustomers();
        this.initTopCreditPointsCustomers();
    },

    computed: {
        filterValues() {
            return [
                {
                    value: 'all',
                    label: this.$tc('custom-bonus-system.list.dashboard.filterAllLabel'),
                },
                {
                    value: '30',
                    label: this.$tc('custom-bonus-system.list.dashboard.filterlast30DaysLabel'),
                },
                {
                    value: '14',
                    label: this.$tc('custom-bonus-system.list.dashboard.filterlast14DaysLabel'),
                },
                {
                    value: '7',
                    label: this.$tc('custom-bonus-system.list.dashboard.filterlast7DaysLabel'),
                },
                {
                    value: '1',
                    label: this.$tc('custom-bonus-system.list.dashboard.filterlast24HoursLabel'),
                },
                {
                    value: 'yesterday',
                    label: this.$tc('custom-bonus-system.list.dashboard.filterSinceYesterdayLabel'),
                },
            ];
        },
        salesChannelColumns() {
            return [
                {
                    property: 'salesChannel.name',
                    label: this.$tc('custom-bonus-system.list.dashboard.salesChannelLabel'),
                    primary: true
                },
                {
                    property: 'earned',
                    label: this.$tc('custom-bonus-system.list.dashboard.pointsEarnedLabel'),
                },
                {
                    property: 'spent',
                    label: this.$tc('custom-bonus-system.list.dashboard.pointsSpentLabel'),
                },
                {
                    property: 'notApproved',
                    label: this.$tc('custom-bonus-system.list.dashboard.pointsNotApprovedLabel'),
                },
                {
                    property: 'credit',
                    label: this.$tc('custom-bonus-system.list.dashboard.pointsCreditLabel'),
                }
            ];
        },
        topEarnedPointsCustomersColumns() {
            return [
                {
                    property: 'customer.firstName',
                    label: this.$tc('custom-bonus-system.list.dashboard.customerLabel'),
                    primary: true
                },
                {
                    property: 'earned',
                    label: this.$tc('custom-bonus-system.list.dashboard.pointsEarnedLabel'),
                },
                {
                    property: 'spent',
                    label: this.$tc('custom-bonus-system.list.dashboard.pointsSpentLabel'),
                }
            ];
        },
        topCreditPointsCustomersColumns() {
            return [
                {
                    property: 'customer.firstName',
                    label: this.$tc('custom-bonus-system.list.dashboard.customerLabel'),
                    primary: true
                },
                {
                    property: 'points',
                    label: this.$tc('custom-bonus-system.list.dashboard.pointsCreditLabel'),
                }
            ];
        },
    },

    methods: {
        initSumPointsSalesChannels() {
            this.salesChannelsIsLoading = true;
            this.customBonusSystemApiService.getSumPointsSalesChannels(this.filterValue).then(results => {
                this.sumPointsSalesChannels = results.items;
                this.salesChannelsIsLoading = false;
            });
        },
        initTopEarnedPointsCustomers() {
            this.isLoading = true;
            this.customBonusSystemApiService.getTopEarnedPointsCustomers().then(results => {
                this.topEarnedPointsCustomers = results.items.filter((item) => item.customer);
                this.isLoading = false;
            });
        },
        initTopCreditPointsCustomers() {
            this.isLoading = true;
            this.customBonusSystemApiService.getTopCreditPointsCustomers().then(results => {
                this.topCreditPointsCustomers = results.items.filter((item) => item.customer);
                this.isLoading = false;
            });
        },
        getPositiveValue(value) {
            if (value < 0) {
                return (value * (-1));
            }

            if (!value) {
                return 0;
            }

            return value
        }
    },
});
