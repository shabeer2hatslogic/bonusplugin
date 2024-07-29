import HttpClient from 'src/service/http-client.service';
import Plugin from 'src/plugin-system/plugin.class';
import PageLoadingIndicatorUtil from 'src/utility/loading-indicator/page-loading-indicator.util';

/**
 * Class ProductQuantityPoints
 */
export default class ProductQuantityPoints extends Plugin {

    static options = {
        selectSelector:                     '.product-detail-quantity-input',
        containerSelector:                  '#bonus-system-product-detail-points-ajax-container',
        containerIsLoadingClass:            'bonus-system-is-loading',

        buyWidgetContainerSelector:         '#bonus-system-product-detail-buy',
        buyWithPointsOnlyCheckboxSelector:  '#buy-with-points-only-checkbox',
    };

    /**
     * Initialize plugin
     */
    init() {
        const {
            selectSelector,
            containerSelector,
            buyWidgetContainerSelector,
            buyWithPointsOnlyCheckboxSelector
        } = this.options;

        this._client = new HttpClient();

        this.select     = document.querySelector(selectSelector);
        this.container  = document.querySelector(containerSelector);

        this.buyWidgetContainer        = document.querySelector(buyWidgetContainerSelector);
        this.buyWithPointsOnlyCheckbox = document.querySelector(buyWithPointsOnlyCheckboxSelector);

        this._registerEvents();
    }

    /**
     * Register events
     *
     * @private
     */
    _registerEvents() {
        this.select.onchange = this._fetch.bind(this);

        if (this.buyWithPointsOnlyCheckbox) {
            this.buyWithPointsOnlyCheckbox.onchange = this._fetchBuyWithPoints.bind(this);
        }
    }

    /**
     * Fetch data
     *
     * @private
     */
    _fetch() {
        if (this.container) {
            const url = new URL(this.container.dataset.updatePointsUrl);
            url.searchParams.append('quantity', this.select.value);

            this.container.classList.add(this.options.containerIsLoadingClass);
            this._client.get(url.toString(), this._setContent.bind(this), 'application/json', true);
        }
    }

    /**
     * Sets the checkbox state and reload the page to recalculate max order quantity
     *
     * @private
     */
    _fetchBuyWithPoints() {
        if (this.buyWidgetContainer) {
            PageLoadingIndicatorUtil.create();

            const url = new URL(this.buyWidgetContainer.dataset.buyWithPointsUrl);
            url.searchParams.append('state', this.buyWithPointsOnlyCheckbox.checked);

            this._client.get(`${url}`, (response) => {
                window.location.reload();
            });
        }
    }

    /**
     * Sets the content
     *
     * @param data
     * @private
     */
    _setContent(data) {
        this.container.classList.remove(this.options.containerIsLoadingClass);
        this.container.innerHTML = JSON.parse(data).template;
    }
}