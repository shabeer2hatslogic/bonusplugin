import template from './custom-bonus-system-condition-line-item-in-product-stream.html.twig';

const { Component, Context } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();
const { EntityCollection, Criteria } = Shopware.Data;

Component.register('custom-bonus-system-condition-line-item-in-product-stream', {
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
            streams: null
        };
    },

    computed: {
        streamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        streamIds: {
            get() {
                this.ensureValueExist();
                return this.condition.streamCondition.streamIds || [];
            },
            set(streamIds) {
                this.ensureValueExist();
                this.condition.streamCondition = { ...this.condition.streamCondition, streamIds };
            }
        },
    },

    methods: {
        ensureValueExist() {
            if (typeof this.condition.streamCondition === 'undefined' || this.condition.streamCondition === null) {
                this.condition.streamCondition = {};
            }
        },
        createdComponent() {
            this.streams = new EntityCollection(
                this.streamRepository.route,
                this.streamRepository.entityName,
                Context.api
            );

            if (this.streamIds.length <= 0) {
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.setIds(this.streamIds);

            return this.streamRepository.search(criteria, Context.api).then((streams) => {
                this.streams = streams;
            });
        },

        setStreamIds(streams) {
            this.streamIds = streams.getIds();
            this.streams = streams;
        }
    }
});
