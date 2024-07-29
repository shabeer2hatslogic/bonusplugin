import template from './custom-bonus-system-booking.html.twig'
import './custom-bonus-system-booking.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('custom-bonus-system-booking', {
  template,

  inject: [
      'repositoryFactory',
      'filterFactory'
  ],

  mixins: [
      Mixin.getByName('listing')
  ],

  data() {
    return {
        bonus: null,
        isLoading: true,
        filterCriteria: [],
        defaultFilters: [
            'is-order-filter',
            'approved-filter'
        ],
        storeKey: 'grid.filter.booking.index',
        sortBy: 'createdAt',
        naturalSorting: true,
        sortDirection: 'DESC',
        activeFilterNumber: 0
    };
  },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

  computed: {
      repository() {
          return this.repositoryFactory.create('custom_bonus_system_booking');
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

      columns() {
          return [{
              property: 'order.orderNumber',
              label: this.$tc('custom-bonus-system.list.booking.columnOrder'),
              allowResize: true,
              primary: true
          }, {
              property: 'createdAt',
              allowResize: true,
              label: this.$tc('custom-bonus-system.list.booking.columnCreateDate'),
          }, {
              property: 'salesChannel.name',
              label: this.$tc('custom-bonus-system.list.booking.columnSalesChannelName'),
              allowResize: true
          }, {
              property: 'customer.firstName',
              dataIndex: 'customer.firstName,customer.lastName',
              label: this.$tc('custom-bonus-system.list.booking.columnCustomerName'),
              allowResize: true
          }, {
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
          const criteria = new Criteria(this.page, this.limit);
          this.naturalSorting = this.sortBy === 'createdAt';

          this.sortBy.split(',').forEach(sortBy => {
              criteria.addSorting(Criteria.sort(sortBy, this.sortDirection, this.naturalSorting));
          });

          criteria.setTerm(this.term);

          this.filterCriteria.forEach(filter => {
              criteria.addFilter(filter);
          });

          criteria.addAssociation('order');
          criteria.addAssociation('salesChannel');

          return criteria;
      }
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
                  this.bonus = searchResult.filter((item) => item.customer);
                  this.total = searchResult.total;
                  this.isLoading = false;
              }).catch(error => {
                  console.log(error);
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
      /**getCustomerCriteria(value) {
          const criteria = new Criteria();

          if (value !== false) {
              criteria.addFilter(Criteria.equals('customerId', value));
          }

          return criteria;
      }*/
  },
});
