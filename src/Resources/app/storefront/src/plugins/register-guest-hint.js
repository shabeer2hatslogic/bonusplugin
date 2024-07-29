import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
import Iterator from 'src/helper/iterator.helper';

export default class RegisterGuestHint extends Plugin {
    static options = {

        /**
         * the attribute for the target selector
         */
        targetDataAttribute: 'data-form-register-guest-hint-toggle-target',

        /**
         * the attribute for the trigger selector
         */
        triggerDataAttribute: 'data-custom-register-guest-hint-trigger-selector',

        /**
         * show element class
         */
        showClass: 'd-block',

        /**
         * hide element class
         */
        hideClass: 'd-none'
    }

    /**
     * Initialize plugin
     */
    init() {
        this._getTriggers();
        this._getTargets();
        this._registerEvents();
    }

    /**
     * Initialize targets
     *
     * @private
     */
    _getTargets() {
        const selector = DomAccess.getDataAttribute(this.el, this.options.targetDataAttribute);
        this._targets = DomAccess.querySelectorAll(this.el, selector);
    }

    /**
     * Initialize triggers
     *
     * @private
     */
    _getTriggers() {
        const selector = DomAccess.getDataAttribute(this.el, this.options.triggerDataAttribute);
        this._triggers = DomAccess.querySelectorAll(this.el, selector);
    }

    /**
     * Register event listeners
     *
     * @private
     */
    _registerEvents() {
        Iterator.iterate(this._triggers, element => {
            element.addEventListener('change', this._onChange.bind(this))
        });
    }

    /**
     * Show or hide target elements
     *
     * @param event
     * @private
     */
    _onChange(event) {
        const hideElement = event.target.checked;

        Iterator.iterate(this._targets, element => {
            (hideElement) ? this._hideElement(element) : this._showElement(element);
        });
    }

    /**
     * Show the element
     *
     * @param element
     * @private
     */
    _showElement(element) {
        this._replaceElementClass(element, this.options.hideClass, this.options.showClass)
    }

    /**
     * Hide the element
     *
     * @param element
     * @private
     */
    _hideElement(element) {
        this._replaceElementClass(element, this.options.showClass, this.options.hideClass)

        if (!element.classList.contains(this.options.showClass) && !element.classList.contains(this.options.hideClass)) {
            element.classList.add(this.options.hideClass);
        }
    }

    /**
     * Replace the class in the element
     *
     * @param element
     * @param classFrom
     * @param classTo
     * @private
     */
    _replaceElementClass(element, classFrom, classTo) {
        if (element.classList.contains(classFrom)) {
            element.classList.replace(classFrom, classTo);
        }
    }
}