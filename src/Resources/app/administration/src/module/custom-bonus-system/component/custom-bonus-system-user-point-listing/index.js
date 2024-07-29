import template from './custom-bonus-system-user-point-listing.html.twig';
import './custom-bonus-system-user-points-listing.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.extend('custom-bonus-system-user-point-listing', 'sw-entity-listing', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            deleteId: null,
            showBulkDeleteModal: false,
            isBulkLoading: false,
            page: 1,
            limit: this.criteriaLimit,
            total: 10,
            lastSortedColumn: null,
            showPointBookingModal: false,
            pointBookingId: null,
            pointBookingSalesChannelId: null,
            pointBookingPoints: null,
            pointBookingReason: null,
            bookingFormError: 0
        };
    },

    methods: {
        applyResult(result) {
            result.forEach((item) => {
                if (
                    item.extensions &&
                    item.extensions.customBonusSystemUserPoint &&
                    item.extensions.customBonusSystemUserPoint.points
                ) {
                    item.points = item.extensions.customBonusSystemUserPoint.points
                } else {
                    item.points = 0;
                }
            });

            this.records = result;
            this.total = result.total;
            this.page = result.criteria.page;
            this.limit = result.criteria.limit;
            this.loading = false;

            this.$emit('update-records', result);
        },

        bookPoints() {
            if (!this.pointBookingReason || !this.pointBookingPoints || this.pointBookingPoints === 0) {
                this.bookingFormError = 1;
                return;
            }
            this.bookingFormError = 0;

            this.customerId = this.pointBookingId;
            this.pointBookingId = null;

            this.bonusBookingRepository = this.repositoryFactory.create('custom_bonus_system_booking');
            let bonusBooking = this.bonusBookingRepository.create(Shopware.Context.api);
            bonusBooking.customerId = this.customerId;
            bonusBooking.description = this.pointBookingReason;
            bonusBooking.points = this.pointBookingPoints;
            bonusBooking.salesChannelId = this.pointBookingSalesChannelId;
            bonusBooking.approved = true;
            this.bonusBookingRepository.save(bonusBooking, Shopware.Context.api);

            this.userPointRepository = this.repositoryFactory.create('custom_bonus_system_user_point');
            this.criteria = new Criteria();
            this.criteria.addFilter(Criteria.equals('customerId', this.customerId));
            this.criteria.limit = 1;
            this.userPointRepository.search(this.criteria, Shopware.Context.api)
                .then((userPoints) => {
                    if (userPoints.total > 0) {
                        userPoints.forEach((userPoint) => {
                            userPoint.points += this.pointBookingPoints;
                            this.userPointRepository.save(userPoint, Shopware.Context.api).then(() => {
                                return this.doSearch();
                            });
                        });
                    } else {
                        let userPoint = this.userPointRepository.create(Shopware.Context.api);
                        userPoint.points = this.pointBookingPoints;
                        userPoint.customerId = this.customerId;
                        this.userPointRepository.save(userPoint, Shopware.Context.api).then(() => {
                            return this.doSearch();
                        });
                    }

                });
        },

        displayPointBookingModal(id, salesChannelId) {
            this.pointBookingId = id;
            this.pointBookingSalesChannelId = salesChannelId;
        },

        closePointBookingModal() {
            this.pointBookingId = null;
            this.pointBookingSalesChannelId = null
            this.pointBookingPoints = null;
            this.pointBookingReason = null;
        }
    }
});
