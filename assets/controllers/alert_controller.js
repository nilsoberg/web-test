import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        type: String,
        timeout: { type: Number, default: 10000 }
    }

    connect() {
        // Only auto-dismiss if the type is 'success'
        if (this.typeValue === 'success') {
            this.dismissTimer = setTimeout(() => {
                this.close();
            }, this.timeoutValue);
        }
    }

    close() {
        // Add a fade-out class if using Bootstrap/Tailwind, then remove
        this.element.style.transition = 'opacity 0.5s ease';
        this.element.style.opacity = '0';
        
        setTimeout(() => {
            this.element.remove();
        }, 500);
    }

    disconnect() {
        if (this.dismissTimer) {
            clearTimeout(this.dismissTimer);
        }
    }
}
