import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        width: { type: Number, default: 800 },
        height: { type: Number, default: 600 }
    }

    open(event) {
        // Prevent the default link behavior if it's an <a> tag
        event.preventDefault();

        const left = (window.screen.width - this.widthValue) / 2;
        const top = (window.screen.height - this.heightValue) / 2;

        const features = `width=${this.widthValue},height=${this.heightValue},left=${left},top=${top},resizable=yes,scrollbars=yes`;

        window.open(this.urlValue, '_blank', features);
    }
}
