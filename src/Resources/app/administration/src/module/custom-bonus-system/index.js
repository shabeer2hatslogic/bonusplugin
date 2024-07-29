import './page/custom-bonus-system-booking';
import './page/custom-bonus-system-customer';
import './page/custom-bonus-system-customer-detail';
import './component/custom-bonus-system-customer-detail-info';
import './component/custom-bonus-system-customer-detail-card';
import './page/custom-bonus-system-condition';
import './page/custom-bonus-system-condition-detail';
import './page/custom-bonus-system-bonus-product';
import './page/custom-bonus-system-bonus-product-detail';
import './page/custom-bonus-system-dashboard';

import './component/custom-bonus-system-booking-list';
import './component/custom-bonus-system-user-point-listing';

import './component/condition-type/custom-bonus-system-condition-customer-group';
import './component/condition-type/custom-bonus-system-condition-customer-number';
import './component/condition-type/custom-bonus-system-condition-line-item';
import './component/condition-type/custom-bonus-system-condition-line-item-in-category';
import './component/condition-type/custom-bonus-system-condition-line-item-in-product-stream';
import './component/custom-bonus-system-navigation';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('custom-bonus-system', {
    type: 'plugin',
    name: 'Bonus system',
    title: 'Bonus system module',
    description: 'Module for bonus administration',
    color: '#62ff80',
    icon: 'regular-megaphone',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        index: {
            component: 'custom-bonus-system-dashboard',
            path: 'index'
        },
        booking: {
            component: 'custom-bonus-system-booking',
            path: 'booking'
        },
        customer: {
            component: 'custom-bonus-system-customer',
            path: 'customer'
        },
        customerdetail: {
            component: 'custom-bonus-system-customer-detail',
            path: 'customerdetail/:id',
            props: {
                default: (route) => ({customerId: route.params.id})
            }
        },
        condition: {
            component: 'custom-bonus-system-condition',
            path: 'condition'
        },
        conditioncreate: {
            component: 'custom-bonus-system-condition-detail',
            path: 'conditioncreate',
        },
        conditiondetail: {
            component: 'custom-bonus-system-condition-detail',
            path: 'conditiondetail/:id',
            props: {
                default: (route) => ({conditionId: route.params.id})
            }
        },
        bonusproduct: {
            component: 'custom-bonus-system-bonus-product',
            path: 'bonusproduct'
        },
        bonusproductcreate: {
            component: 'custom-bonus-system-bonus-product-detail',
            path: 'bonusproductcreate',
        },
        bonusproductdetail: {
            component: 'custom-bonus-system-bonus-product-detail',
            path: 'bonusproductdetail/:id',
            props: {
                default: (route) => ({bonusProductId: route.params.id})
            }
        },
    },

    navigation: [{
        path: 'custom.bonus.system.index',
        label: 'custom-bonus-system.general.menuItem',
        color: '#57D9A3',
        parent: 'sw-marketing',
        position: 30
    }]
});
