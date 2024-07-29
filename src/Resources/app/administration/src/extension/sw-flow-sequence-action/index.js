import { ACTION, GROUP } from '../../constant/custom-bonus-system-plugin.constant';

const { Component } = Shopware;

Component.override('sw-flow-sequence-action', {
    computed: {
        modalName() {
            if (this.selectedAction === ACTION.CHANGE_BONUS_POINTS) {
                return 'custom-bonus-system-flow-builder-change-points-modal';
            }

            return this.$super('modalName');
        },

        actionDescription() {
            const actionDescriptionList = this.$super('actionDescription');

            return {
                ...actionDescriptionList,
                [ACTION.CHANGE_BONUS_POINTS] : (config) => this.getChangePointsDescription(config),
            };
        },
    },

    methods: {
        getChangePointsDescription(config) {
            return this.$tc('custom-bonus-system.flow-builder.change-points.description');
        },

        getActionTitle(actionName) {
            if (actionName === ACTION.CHANGE_BONUS_POINTS) {
                return {
                    value: actionName,
                    icon: 'regular-circle',
                    label: this.$tc('custom-bonus-system.flow-builder.change-points.title-change-points'),
                    group: GROUP,
                }
            }

            return this.$super('getActionTitle', actionName);
        },
    },
});