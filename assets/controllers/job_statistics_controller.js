import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["form", "monthInput"]

    // Triggered when a checkbox changes
    submit() {
        this.formTarget.requestSubmit();
    }

    // Triggered by the Prev/Next month buttons
    changeMonth(event) {
        event.preventDefault();
        this.monthInputTarget.value = event.currentTarget.dataset.month;
        this.submit();
    }
}
