import template from './custom-bonus-system-customer.html.twig'

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('custom-bonus-system-customer', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            sortBy: 'createdAt',
            naturalSorting: true,
            sortDirection: 'DESC',
            customer: null,
            isLoading: true,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('customer');
        },

        columns() {
            return [{
                property: 'customer.firstName',
                dataIndex: 'customer.firstName,customer.lastName',
                label: 'sw-customer.list.columnName',
                allowResize: true
            }, {
                property: 'customerEmail',
                dataIndex: 'customer.email',
                naturalSorting: true,
                label: 'custom-bonus-system.list.userPoint.columnCustomerEmail',
                allowResize: true,
            }, {
                property: 'customerNumber',
                dataIndex: 'customer.customerNumber',
                naturalSorting: true,
                label: 'sw-customer.list.columnCustomerNumber',
                allowResize: true,
                align: 'right'
            }, {
                property: 'points',
                dataIndex: 'extensions.customBonusSystemUserPoint.points',
                label: this.$tc('custom-bonus-system.list.userPoint.columnPoints'),
                allowResize: true,
            }];
        },

        criteria() {
            const criteria = new Criteria(this.page, this.limit);

            this.naturalSorting = this.sortBy === 'createdAt';
            this.sortBy.split(',').forEach(sortBy => {
                criteria.addSorting(Criteria.sort(sortBy, this.sortDirection, this.naturalSorting));
            });

            criteria.setTerm(this.term);
            criteria.addAssociation('customBonusSystemUserPoint');

            return criteria;
        }
    },

    methods: {
        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        getList() {
            this.isLoading = true;

            return this.repository.search(this.criteria, Shopware.Context.api)
                .then((searchResult) => {
                    searchResult.forEach((item) => {
                        if (
                            item.extensions &&
                            item.extensions.customBonusSystemUserPoint &&
                            item.extensions.customBonusSystemUserPoint.points
                        ) {
                            item.points = item.extensions.customBonusSystemUserPoint.points
                        } else {
                            item.points = 0;
                        }
                    });

                    this.customer = searchResult;
                    this.total = searchResult.total;
                    this.isLoading = false;
                });
        },
    },
});
