import template from './custom-bonus-system-customer-detail-card.html.twig';
import './custom-bonus-system-customer-detail-card.scss';

const { Component, Mixin } = Shopware;
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();
const format = Shopware.Utils.format;

Component.register('custom-bonus-system-customer-detail-card', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('salutation')
    ],

    props: {
        customer: {
            type: Object,
            required: true
        },
        title: {
            type: String,
            required: true
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false
        },
    },

    computed: {
        fullName() {
            const name = {
                name: this.salutation(this.customer),
                company: this.customer.company
            };

            return Object.values(name).filter(item => item !== null).join(' - ').trim();
        },
        points() {
            console.log()
            if (
                this.customer.extensions &&
                this.customer.extensions.customBonusSystemUserPoint &&
                this.customer.extensions.customBonusSystemUserPoint.points
            ) {
                return this.customer.extensions.customBonusSystemUserPoint.points;
            } else {
                return 0;
            }
        },
        ...mapPropertyErrors('customer', ['firstName', 'lastName'])
    },

    methods: {
        getMailTo(mail) {
            return `mailto:${mail}`;
        },
        displayPointBookingModal() {
            this.$emit('open-book-points-modal-event');
        },
    }
});
