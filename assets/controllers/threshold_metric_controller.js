import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['metricDescription', 'metricLabel'];

    connect() {
        this.updateThresholdText(this.element.querySelector('select').value);
    }

    update(event) {
        this.updateThresholdText(event.target.value);
    }

    updateThresholdText(selectedValue) {
        this.metricDescriptionTargets.forEach((element) => {
            const targetValue = element.dataset.enumValue;
            if (targetValue === selectedValue) {
                element.classList.remove('d-none');
            } else {
                element.classList.add('d-none');
            }
        });

        this.metricLabelTargets.forEach((element) => {
            const targetValue = element.dataset.enumValue;
            if (targetValue === selectedValue) {
                element.classList.remove('d-none');
            } else {
                element.classList.add('d-none');
            }
        });
    }
}
