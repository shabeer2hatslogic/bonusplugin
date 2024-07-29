import './init/api-service.init';
// import './module/sw-order-line-items-grid'
import './module/custom-bonus-system';
import './decorator/rule-condition-service-decoration'
import './extension/sw-flow-sequence-action'
import './component/custom-bonus-system/flow-builder/change-points-modal'

import deDeSnippets from './snippet/de-DE.json';
import enGBSnippets from './snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDeSnippets);
Shopware.Locale.extend('en-GB', enGBSnippets);


/**Application.addInitializerDecorator('locale', (localeFactory) => {
    localeFactory.extend('de-DE', deDeSnippets);
    localeFactory.extend('en-GB', enGBSnippets);

    return localeFactory;
});*/

document.addEventListener('DOMContentLoaded', function() {
    var slider = document.getElementById('pointsToRedeem');
    var output = document.getElementById('pointsToRedeemValue');
    console.log("value");
    slider.oninput = function() {
        output.innerHTML = this.value;
    }
});