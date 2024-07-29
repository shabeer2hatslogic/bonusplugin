import template from './custom-bonus-system-condition-line-item.html.twig';
import './custom-bonus-system-condition-line-item.scss';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

/**
 * @public
 * @description Condition for the LineItemRule. This component must a be child of sw-condition-tree.
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-line-item :condition="condition" :level="0"></sw-condition-line-item>
 */
Component.register('custom-bonus-system-condition-line-item', {
    template,

    inject: ['repositoryFactory'],

    props: {
        condition: {
            type: Object,
            required: false,
            default: null
        },
    },

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productIds: {
            get() {
                this.ensureValueExist();
                return this.condition.productCondition.identifiers || [];
            },
            set(identifiers) {
                this.ensureValueExist();
                this.condition.productCondition = { ...this.condition.productCondition, identifiers };
            }
        },

        //...mapPropertyErrors('condition', ['value.operator', 'value.identifiers']),

        /**currentError() {
            return this.conditionValueOperatorError || this.conditionValueIdentifiersError;
        },*/

        productCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('options.group');

            return criteria;
        },

        resultCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('options.group');

            return criteria;
        },

        productContext() {
            return { ...Shopware.Context.api, inheritance: true };
        }
    },

    data() {
        return {
            products: null
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        ensureValueExist() {
            if (typeof this.condition.productCondition === 'undefined' || this.condition.productCondition === null) {
                this.condition.productCondition = {};
            }
        },
        createdComponent() {
            this.products = new EntityCollection(
                this.productRepository.route,
                this.productRepository.entityName,
                this.productContext
            );


            if (this.productIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.addAssociation('options.group');
            criteria.setIds(this.productIds);

            return this.productRepository.search(criteria, this.productContext).then((products) => {
                this.products = products;
            });
        },

        setIds(productCollection) {
            this.productIds = productCollection.getIds();
            this.products = productCollection;
        }
    }
});
