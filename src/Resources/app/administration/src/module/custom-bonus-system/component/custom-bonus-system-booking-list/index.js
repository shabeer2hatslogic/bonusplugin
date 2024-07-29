import template from './custom-bonus-system-booking-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.extend('custom-bonus-system-booking-list', 'sw-entity-listing', {
    template,

    inject: ['repositoryFactory'],

    methods: {
        approveItems() {
            let me = this;
            let userPointRepository = this.repositoryFactory.create('custom_bonus_system_user_point');
            let userPointsToUpdate = {};

            Object.values(this.selection).forEach((selectedProxy) => {
                if (selectedProxy.approved === false) {
                    selectedProxy.approved = true;

                    this.repository.save(selectedProxy, Shopware.Context.api);

                    if (userPointsToUpdate[selectedProxy.customerId] === undefined) {
                        userPointsToUpdate[selectedProxy.customerId] = 0;
                    }
                    userPointsToUpdate[selectedProxy.customerId] += selectedProxy.points;
                }
            });

                Object.keys(userPointsToUpdate).forEach(function(key) {
                    let criteria = new Criteria();
                    criteria.addFilter(Criteria.equals('customerId', key));
                    criteria.limit = 1;
                    userPointRepository.search(criteria, Shopware.Context.api)
                        .then((userPoints) => {
                            if (userPoints.total > 0) {
                                userPoints.forEach((userPoint) => {
                                    userPoint.points += userPointsToUpdate[key];
                                    userPointRepository.save(userPoint, Shopware.Context.api);
                                });
                            } else {
                                let userPoint = userPointRepository.create(Shopware.Context.api);
                                userPoint.points = userPointsToUpdate[key];
                                userPoint.customerId = key;
                                userPointRepository.save(userPoint, Shopware.Context.api);
                            }
                            me.doSearch();
                        });
                });

            return this.doSearch();
        },
    }
});
