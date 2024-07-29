import Plugin from 'src/plugin-system/plugin.class';

export default class RangeSlider extends Plugin {
    static options = {
        pointInput: '#bonus-slider-points',
        pointDisplayOutput: '#point-display-output',
        btnRedeemPoints: '.btn-redeem-points',
        sliderStart: 0,
        sliderStep: 1,
    };

    init() {
        const slider = document.getElementById('bonus-slider');
        if (slider) {
            this.applySlider(slider);
            window.PluginManager.getPluginInstances('FormAjaxSubmit').forEach(plugin => {
                plugin.$emitter.subscribe('onAfterAjaxSubmit', this.applySlider.bind(this));
            });
        }
    }

    applySlider(slider) {
        const {pointInput, pointDisplayOutput, btnRedeemPoints, sliderStart, sliderStep} = this.options;
        this.pointInput = document.querySelector(pointInput);
        this.pointDisplayOutput = document.querySelector(pointDisplayOutput);
        this.btnRedeemPoints = document.querySelector(btnRedeemPoints);

        const sliderRangeMin = parseInt(slider.dataset.rangeMin);
        const sliderRangeMax = parseInt(slider.dataset.rangeMax);
        const bonusSystemConversionFactorRedeem = parseFloat(document.getElementById('bonus--conversion-factor').value);

        noUiSlider.create(slider, {
            start: sliderRangeMax,
            connect: true,
            tooltips: {
                to: function(value) {
                    return parseInt(value) + ' P / ' + (parseInt(value) * (1 / bonusSystemConversionFactorRedeem)).toFixed(2) + ' ' + slider.dataset.currency;
                } 
            },
            step: sliderStep,
            range: {
                'min': ~~(sliderRangeMin),
                'max': ~~(sliderRangeMax),
            },
        });

        slider.noUiSlider.on('update', (values, handle) => {
            const points = parseInt(values[handle], 10);
            let eurForPoints = (points * (1 / bonusSystemConversionFactorRedeem)).toFixed(2);
            if (points > 0) {
                this.btnRedeemPoints.classList.remove('invisible');
            } else {
                this.btnRedeemPoints.classList.add('invisible');
            }

            this.pointDisplayOutput.innerHTML = points;
            this.pointInput.value = points;
        });
    }
}
