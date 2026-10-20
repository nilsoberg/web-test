import { Controller } from '@hotwired/stimulus';
import { Tooltip } from 'bootstrap';

/**
 * A simple controller to initialize Bootstrap tooltips.
 */
export default class extends Controller {
    connect() {
        // Initialize a new Bootstrap Tooltip on this controller's element.
        this.tooltip = new Tooltip(this.element);
    }

    disconnect() {
        // Verify that the tooltip wasn't already disposed by the job status controller
        // when a user removed a row
        if (this.tooltip) {
            try {
                // When the element is removed from the DOM, properly destroy the tooltip
                // to prevent memory leaks.
                this.tooltip.dispose();
            } catch (e) {
            } finally {
                this.tooltip = null;
            }
        }
    }
}
