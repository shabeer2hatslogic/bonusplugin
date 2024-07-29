import template from './custom-bonus-system-customer-detail.html.twig';
import './custom-bonus-system-customer-detail.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

Component.register('custom-bonus-system-customer-detail', {
    template,

    inject: [
        'repositoryFactory',
        'filterFactory',
        'stateStyleDataProviderService',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('customer-detail')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    props: {
        customerId: {
            type: String,
            required: false,
            default: null
        },
    },

    data() {
        return {
            bonus: null,
            customer: null,
            isLoading: false,
            filterCriteria: [],
            defaultFilters: [
                'is-order-filter',
                'approved-filter'
            ],
            storeKey: 'grid.filter.bonus.customer.detail.index',
            sortBy: 'createdAt',
            naturalSorting: true,
            sortDirection: 'DESC',
            activeFilterNumber: 0,
            bookingPoints: null,
            bookingReason: null,
            bookingFormError: 0,
            showModal: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        customerCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('group');
            criteria.addAssociation('salutation');
            criteria.addAssociation('customBonusSystemUserPoint');
            return criteria;
        },

        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        repository() {
            return this.repositoryFactory.create('custom_bonus_system_booking');
        },

        userPointRepository() {
            return this.repositoryFactory.create('custom_bonus_system_user_point');
        },

        listFilters() {
            return this.filterFactory.create('custom_bonus_system_booking', {
                'approved-filter': {
                    property: 'approved',
                    criteria: this.getApprovedCriteria('custom_bonus_system_booking.approved'),
                    label: this.$tc('custom-bonus-system.list.booking.titleSidebarItemFilterApproved'),
                    placeholder: this.$tc('custom-bonus-system.list.booking.titleSidebarItemFilterApproved'),
                    valueProperty: 'value',
                    labelProperty: 'label',
                    options: this.approvedValues
                },
                'is-order-filter': {
                    property: 'orderId',
                    criteria: this.getIsOrderCriteria('custom_bonus_system_booking.orderId'),
                    label: this.$tc('custom-bonus-system.list.booking.titleSidebarItemFilterIsOrder'),
                    placeholder: this.$tc('custom-bonus-system.list.booking.titleSidebarItemFilterIsOrder'),
                    valueProperty: 'value',
                    labelProperty: 'label',
                    optionHasCriteria: this.$tc('global.sw-condition.condition.yes'),
                    optionNoCriteria: this.$tc('global.sw-condition.condition.no'),
                    options: this.isOrderValues
                }
            });
        },

        getVariantFromOrderState(order) {
            if (order.stateMachineState !== undefined) {
                return this.stateStyleDataProviderService.getStyle(
                    'order.state', order.stateMachineState.technicalName
                ).variant;
            }
        },

        columns() {
            return [{
                property: 'order.orderNumber',
                label: this.$tc('custom-bonus-system.list.booking.columnOrder'),
                allowResize: true,
                primary: true
            }, {
                property: 'order.stateMachineState.name',
                label: 'sw-order.list.columnState',
                allowResize: true
            }, {
                property: 'createdAt',
                allowResize: true,
                label: this.$tc('custom-bonus-system.list.booking.columnCreateDate'),
            }, {
                property: 'salesChannel.name',
                label: this.$tc('custom-bonus-system.list.booking.columnSalesChannelName'),
                allowResize: true
            }, /**{
                property: 'customer.firstName',
                dataIndex: 'customer.firstName,customer.lastName',
                label: this.$tc('custom-bonus-system.list.booking.columnCustomerName'),
                allowResize: true
            },*/ {
                property: 'points',
                label: this.$tc('custom-bonus-system.list.booking.columnPoints'),
                allowResize: true,
            }, {
                property: 'description',
                label: this.$tc('custom-bonus-system.list.booking.columnDescription'),
                allowResize: true,
            }, {
                property: 'approved',
                label: this.$tc('custom-bonus-system.list.booking.columnApproved'),
                allowResize: true,
            }];
        },

        criteria() {
            const criteria = new Criteria();
            this.naturalSorting = this.sortBy === 'createdAt';

            this.sortBy.split(',').forEach(sortBy => {
                criteria.addSorting(Criteria.sort(sortBy, this.sortDirection, this.naturalSorting));
            });
            criteria.addFilter(Criteria.equals('customerId', this.customerId));

            criteria.setTerm(this.term);

            this.filterCriteria.forEach(filter => {
                criteria.addFilter(filter);
            });

            criteria.addAssociation('order');
            criteria.addAssociation('order.stateMachineState');
            criteria.addAssociation('salesChannel');

            return criteria;
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
        criteria: {
            handler() {
                this.getList();
            },
            deep: true
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchCustomer();
            this.loadFilterValues();
        },

        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        loadFilterValues() {
            this.filterLoading = true;

            this.approvedValues = [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: false
                }
            ];
            this.isOrderValues =  [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: false
                }
            ];
        },

        async getList() {
            this.isLoading = true;

            const criteria = await Shopware.Service('filterService')
                .mergeWithStoredFilters(this.storeKey, this.criteria);

            this.activeFilterNumber = criteria.filters.length;

            return this.repository.search(criteria, Shopware.Context.api)
                .then((searchResult) => {
                    this.bonus = searchResult;
                    this.total = searchResult.total;
                    this.isLoading = false;
                });
        },
        updateCriteria(criteria) {
            this.page = 1;

            this.filterCriteria = criteria;
        },
        getApprovedCriteria(value) {
            const criteria = new Criteria();

            if (value === true) {
                criteria.addFilter(Criteria.equals('approved', true));
            } else {
                criteria.addFilter(Criteria.equals('approved', false));
            }

            return criteria;
        },
        getIsOrderCriteria(value) {
            const criteria = new Criteria();

            if (value === true) {
                criteria.addFilter(Criteria.not(
                    'and',
                    [Criteria.equals('orderId', null)]
                ));
            } else {
                criteria.addFilter(Criteria.equals('orderId', null));
            }

            return criteria;
        },

        onCancel() {
            this.$router.push({ name: 'custom.bonus.system.customer' });
        },
        updateCustomerPoints() {
            if (!this.bookingReason || !this.bookingPoints || this.bookingPoints === 0) {
                this.bookingFormError = 1;
                return;
            }

            this.bookingFormError = 0;
            this.updateBonusBooking();
            this.updateUserPoints();
        },
        updateBonusBooking() {
            this.isLoading = true;
            this.repository.save(this.createBonusBooking(), Shopware.Context.api).then(() => {
                this.getList();
                this.clearBookingFields();
                this.isLoading = false;
            });
        },
        createBonusBooking() {
            const bonusBooking = this.repository.create(Shopware.Context.api);
            bonusBooking.points = this.bookingPoints;
            bonusBooking.approved = true;
            bonusBooking.customerId = this.customerId;
            bonusBooking.description = this.bookingReason;
            bonusBooking.salesChannelId = this.customer.salesChannelId;

            return bonusBooking;
        },
        updateUserPoints() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('customerId', this.customerId));
            criteria.limit = 1;
            this.isLoading = true;

            this.userPointRepository.search(criteria, Shopware.Context.api)
                .then((userPoints) => {
                    if (userPoints.total > 0) {
                        userPoints.forEach((userPoint) => {
                            userPoint.points += this.bookingPoints;
                            this.userPointRepository.save(userPoint, Shopware.Context.api).then(() => {
                                this.fetchCustomer();
                                this.clearBookingFields();
                                this.isLoading = false;
                            });
                        });
                    } else {
                        this.userPointRepository.save(this.createUserPoint(), Shopware.Context.api).then(() => {
                            this.fetchCustomer();
                            this.clearBookingFields();
                            this.isLoading = false;
                        });
                    }
                });
        },
        createUserPoint() {
            const userPoint = this.userPointRepository.create(Shopware.Context.api);
            userPoint.points = this.bookingPoints;
            userPoint.customerId = this.customerId;

            return userPoint;
        },
        fetchCustomer() {
            this.isLoading = true;
            this.customerRepository.get(this.customerId, Shopware.Context.api, this.customerCriteria).then((customer) => {
                this.customer = customer;
                this.isLoading = false;
            });
        },
        clearBookingFields() {
            this.showModal = false;
            this.bookingPoints = null;
            this.bookingReason = null;
        },
        displayPointBookingModal() {
            this.showModal = true;
        },
        closePointBookingModal() {
            this.clearBookingFields();
        }
    }
});
