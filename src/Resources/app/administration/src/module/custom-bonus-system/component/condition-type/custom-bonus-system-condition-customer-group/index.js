import template from './custom-bonus-system-condition-customer-group.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the CustomerGroupRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-customer-group :condition="condition" :level="0"></sw-condition-customer-group>
 */
Component.register('custom-bonus-system-condition-customer-group', {
    template,
    inheritAttrs: false,

    inject: ['repositoryFactory'],

    props: {
        condition: {
            type: Object,
            required: false,
            default: null
        },
    },

    created() {
        this.createdComponent();
    },

    data() {
        return {
            customerGroups: null
        };
    },

    computed: {
        customerGroupRepository() {
            return this.repositoryFactory.create('customer_group');
        },

        customerGroupIds: {
            get() {
                this.ensureValueExist();
                return this.condition.customerGroupCondition.customerGroupIds || [];
            },
            set(customerGroupIds) {
                this.ensureValueExist();
                this.condition.customerGroupCondition = { ...this.condition.value, customerGroupIds };
            }
        },
    },

    methods: {
        ensureValueExist() {
            if (typeof this.condition.customerGroupCondition === 'undefined' || this.condition.customerGroupCondition === null) {
                this.condition.customerGroupCondition = {};
            }
        },

        createdComponent() {
            this.customerGroups = new EntityCollection(
                this.customerGroupRepository.route,
                this.customerGroupRepository.entityName,
                Context.api
            );

            if (this.customerGroupIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.customerGroupIds);

            return this.customerGroupRepository.search(criteria, Context.api).then((customerGroups) => {
                this.customerGroups = customerGroups;
            });
        },

        setCustomerGroupIds(customerGroups) {
            this.customerGroupIds = customerGroups.getIds();
            this.customerGroups = customerGroups;
        }
    }
});
