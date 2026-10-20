import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static values = { 
        formId: String,
        fieldName: String, // e.g. 'fraction', 'unirefVersion'
    }

    broadcast(event) {
        this.dispatch("changed", {
            detail: { 
                formId: this.formIdValue,
                fieldName: this.fieldNameValue,
                value: event.target.value,
            }
        });
    }
}
