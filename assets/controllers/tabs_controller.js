import '../js/jquery/jquery-ui.min.css';
import '../styles/tabs.css';

import { Controller } from '@hotwired/stimulus';

import '../js/jquery/jquery-3.7.1.min.js';
import '../js/jquery/jquery-ui.min.js'

export default class extends Controller {
    static values = {
        active: { type: Number, default: 0 },
        nestedActive: { type: Number, default: -1 },
        nestedTarget: { type: String, default: '' }
    }

    connect() {
        $(this.element).tabs({
            active: this.activeValue,
            activate: (event, ui) => {
                this.broadcastChange({
                    tabId: ui.newPanel.attr('id'),
                    tabIndex: ui.newTab.index()
                });
            }
        });

        if (this.nestedTargetValue && this.nestedActiveValue > -1) {
            const nestedTabs = $(this.element).find(this.nestedTargetValue);
            // We need to wait a tick for the nested controller to potentially initialize.
            // A more robust solution might use custom events if timing is an issue.
            setTimeout(() => {
                if (nestedTabs.length && nestedTabs.is('.ui-tabs')) {
                    nestedTabs.tabs('option', 'active', this.nestedActiveValue);
                }
            }, 1);
        }
    }

    broadcastChange(detail) {
        const tabEvent = new CustomEvent("global-tab-change", {
            bubbles: true,
            detail: detail,
        });
        window.dispatchEvent(tabEvent);
    }

    disconnect() {
        if ($(this.element).is(".ui-tabs")) {
            $(this.element).tabs('destroy');
        }
    }
}

