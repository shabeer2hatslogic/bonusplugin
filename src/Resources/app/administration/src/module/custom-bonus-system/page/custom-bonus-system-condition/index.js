import template from './custom-bonus-system-condition.html.twig'
import './custom-bonus-system-condition.scss'

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('custom-bonus-system-condition', {
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
            condition: null,
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
            return this.repositoryFactory.create('custom_bonus_system_condition');
        },

        columns() {
            return [{
                property: 'name',
                label: this.$tc('custom-bonus-system.list.condition.columnName'),
                allowResize: true
            }, {
                property: 'active',
                label: this.$tc('custom-bonus-system.list.condition.columnActive'),
                allowResize: true
            }, {
                property: 'validFrom',
                label: this.$tc('custom-bonus-system.list.condition.columnValidFrom'),
                allowResize: true
            }, {
                property: 'validUntil',
                label: this.$tc('custom-bonus-system.list.condition.columnValidUntil'),
                allowResize: true
            }];
        },

        criteria() {
            const criteria = new Criteria();
            this.naturalSorting = this.sortBy === 'createdAt';

            this.sortBy.split(',').forEach(sortBy => {
                criteria.addSorting(Criteria.sort(sortBy, this.sortDirection, this.naturalSorting));
            });

            criteria.setTerm(this.term);

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
                    this.condition = searchResult;
                    this.total = searchResult.total;
                    this.isLoading = false;
                });
        },
    },
});
