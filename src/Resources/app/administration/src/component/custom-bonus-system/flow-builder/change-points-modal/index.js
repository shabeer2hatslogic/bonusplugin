import template from './change-points-modal.html.twig';

const { Component } = Shopware;

Component.register('custom-bonus-system-flow-builder-change-points-modal', {
    template,

    props: {
        sequence: {
            type: Object,
            required: true
        },
    },

    data() {
        return {
            points: 0,
            description: ''
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.points = this.sequence?.config?.points || 0;
            this.description = this.sequence?.config?.description || '';
        },

        onClose() {
            this.$emit('modal-close');
        },

        onAddAction() {
            const sequence = {
                ...this.sequence,
                    config: {
                        ...this.config,
                        points: this.points,
                        description: this.description
                    },
            };

            this.$emit('process-finish', sequence)
        },
    },
});