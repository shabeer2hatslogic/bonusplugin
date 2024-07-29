import template from './custom-bonus-system-customer-points.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Shopware.Component.extend('custom-bonus-system-customer-points', 'sw-condition-base', {
    template,

    computed: {
        operators() {
            return this.conditionDataProviderService.getOperatorSet('number');
        },

        count: {
            get() {
                this.ensureValueExist();
                return this.condition.value.count;
            },
            set(count) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, count };
            },
        },

        ...mapPropertyErrors('condition', ['value.operator', 'value.count']),

        currentError() {
            return this.conditionTypeError || this.conditionValueOperatorError || this.conditionValueCountError;
        },
    },
});