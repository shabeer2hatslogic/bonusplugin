// Plugins
import RangeSlider from "./plugins/range-slider";
import ProductQuantityPoints from "./plugins/product-quantity-points";
import RegisterGuestHint from "./plugins/register-guest-hint";

const PluginManager = window.PluginManager;
PluginManager.register('CustomBonusSystemRangeSlider', RangeSlider, '.bonus-system-redeem-points-container');
PluginManager.register('ProductQuantityBonusPoints', ProductQuantityPoints, '.product-detail-main');
PluginManager.register('RegisterGuestHint', RegisterGuestHint, '[data-custom-register-guest-hint]');
