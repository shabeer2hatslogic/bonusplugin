import template from './custom-bonus-system-bonus-product.html.twig'
import './custom-bonus-system-bonus-product.scss'

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('custom-bonus-system-bonus-product', {
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
            bonusProduct: null,
            isLoading: true,
            parentProducts: [],
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
            parentProducts: []
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('custom_bonus_system_bonus_product');
        },

        productRepository() {
            return this.repositoryFactory.create('products');
        },

        productContext() {
            const context = Object.assign({}, Shopware.Context.api);
            context.inheritance = true;

            return context;
        },

        columns() {
            return [{
                property: 'product.name',
                label: this.$tc('custom-bonus-system.list.bonusProduct.columnName'),
                allowResize: true
            }, /**{
              property: 'saleschannel',
              label: this.$tc('custom-bonus-system.list.bonusProduct.columnSalesChannel'),
              allowResize: true
          },*/ {
                property: 'active',
                label: this.$tc('custom-bonus-system.list.bonusProduct.columnActive'),
                allowResize: true
            }, {
                property: 'validFrom',
                label: this.$tc('custom-bonus-system.list.bonusProduct.columnValidFrom'),
                allowResize: true
            }, {
                property: 'validUntil',
                label: this.$tc('custom-bonus-system.list.bonusProduct.columnValidUntil'),
                allowResize: true
            }];
        },

        criteria() {
            const criteria = new Criteria();

            this.naturalSorting = this.sortBy === 'createdAt';
            this.sortBy.split(',').forEach(sortBy => {
                criteria.addSorting(Criteria.sort(sortBy, this.sortDirection, this.naturalSorting));
            });

            criteria.addAssociation('product');
            criteria.addAssociation('product.options.group');

            criteria.setTerm(this.term);

            return criteria;
        }
    },

    methods: {
        createdComponent() {
            let parentProductIds = [];
            this.bonusProduct.forEach((item) => {
                if (item.product && item.product.parentId) {
                    parentProductIds.push(item.product.parentId);
                }
            });

            if (parentProductIds.length === 0) {
                return;
            }

            const criteria = new Criteria();
            criteria.setIds(parentProductIds);
            this.productRepository.search(criteria, this.productContext).then((products) => {
                let parentProducts = {};
                products.forEach((product) => {
                    parentProducts[product.id] = product;
                });

                this.parentProducts = parentProducts;
            });
        },
        onChangeLanguage(languageId) {
            this.getList(languageId);
        },

        getList() {
            this.isLoading = true;

            return this.repository.search(this.criteria, this.productContext)
                .then((searchResult) => {
                    this.bonusProduct = searchResult;
                    this.total = searchResult.total;
                    this.isLoading = false;
                });
        },
        getProductTitle(product) {
            if (
                product.hasOwnProperty('translated') &&
                product.translated.hasOwnProperty('name') &&
                product.translated.name !== null
            ) {
                return product.translated.name;
            }
            if (product.name !== null) {
                return product.name;
            }
            if (product.parentId && this.parentProducts[product.parentId]) {
                const parentProduct = this.parentProducts[product.parentId];
                return parentProduct.translated.hasOwnProperty('name') ? parentProduct.translated.name : parentProduct.name;
            }
            return '';
        },
    },
});
