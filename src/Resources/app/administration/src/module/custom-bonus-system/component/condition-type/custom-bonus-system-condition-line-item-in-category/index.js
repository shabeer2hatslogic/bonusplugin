import template from './custom-bonus-system-condition-line-item-in-category.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

Component.register('custom-bonus-system-condition-line-item-in-category', {
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
            categories: null
        };
    },

    computed: {
        categoryRepository() {
            return this.repositoryFactory.create('category');
        },

        categoryIds: {
            get() {
                this.ensureValueExist();
                return this.condition.categoryCondition.categoryIds || [];
            },
            set(categoryIds) {
                this.ensureValueExist();
                this.condition.categoryCondition = { ...this.condition.categoryCondition, categoryIds };
            }
        },

        //...mapPropertyErrors('condition', ['value.operator', 'value.categoryIds']),

        /**currentError() {
            return this.conditionValueOperatorError || this.conditionValueCategoryIdsError;
        }*/
    },

    methods: {
        ensureValueExist() {
            if (typeof this.condition.categoryCondition === 'undefined' || this.condition.categoryCondition === null) {
                this.condition.categoryCondition = {};
            }
        },
        createdComponent() {
            this.categories = new EntityCollection(
                this.categoryRepository.route,
                this.categoryRepository.entityName,
                Context.api
            );

            if (this.categoryIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.categoryIds);

            return this.categoryRepository.search(criteria, Context.api).then((categories) => {
                this.categories = categories;
            });
        },

        setCategoryIds(categories) {
            this.categoryIds = categories.getIds();
            this.categories = categories;
        }
    }
});
