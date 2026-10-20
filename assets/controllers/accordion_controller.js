import '../js/jquery/jquery-ui.min.css';
import '../styles/tabs.css';

import { Controller } from '@hotwired/stimulus';

import '../js/jquery/jquery-3.7.1.min.js';
import '../js/jquery/jquery-ui.min.js'

export default class extends Controller {
    static values = {
        // A boolean to determine if the accordion should be open on initialization.
        initiallyOpen: { type: Boolean, default: false }
    }

    connect() {
        $(this.element).accordion({
            heightStyle: "content",
            collapsible: true,
            // Use the value to determine if it should be open (index 0) or closed.
            active: this.initiallyOpenValue ? 0 : false
        });
    }

    disconnect() {
        // Destroy the accordion when the element is removed from the DOM.
        if ($(this.element).is(".ui-accordion")) {
            $(this.element).accordion('destroy');
        }
    }
}

