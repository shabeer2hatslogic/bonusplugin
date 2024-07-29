
import template from './custom-bonus-system-has-points.html.twig';

Shopware.Component.extend('custom-bonus-system-has-points', 'sw-condition-base', {
    template,

    computed: {
        selectValues() {
            return [
                {
                    label: this.$tc('global.sw-condition.condition.yes'),
                    value: true
                },
                {
                    label: this.$tc('global.sw-condition.condition.no'),
                    value: false
                }
            ];
        },

        hasPoints: {
            get() {
                this.ensureValueExist();

                if (this.condition.value.hasPoints == null) {
                    this.condition.value.hasPoints = false;
                }

                return this.condition.value.hasPoints;
            },
            set(hasPoints) {
                this.ensureValueExist();
                this.condition.value = { ...this.condition.value, hasPoints };
            }
        }
    }
});