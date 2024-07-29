import template from './custom-bonus-system-condition-customer-number.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

/**
 * @public
 * @description Condition for the CustomerNumberRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-customer-number :condition="condition" :level="0"></sw-condition-customer-number>
 */
Component.register('custom-bonus-system-condition-customer-number', {
    template,

    props: {
        condition: {
            type: Object,
            required: false,
            default: null
        },
    },

    computed: {
        customerNumbers: {
            get() {
                this.ensureValueExist();
                return this.condition.customerNumberCondition.numbers || [];
            },
            set(numbers) {
                this.ensureValueExist();
                this.condition.customerNumberCondition = { ...this.condition.customerNumberCondition, numbers };
            }
        },

        //...mapPropertyErrors('condition', ['value.operator', 'value.numbers']),
    },

    methods: {
        ensureValueExist() {
            if (typeof this.condition.customerNumberCondition === 'undefined' || this.condition.customerNumberCondition === null) {
                this.condition.customerNumberCondition = {};
            }
        },
    }
});
