

import '../styles/sunburst.css';

import { Controller } from '@hotwired/stimulus';

import '../js/sunburst/sunburst-chart.min.js';
import '../js/sunburst/transfer.js';
import '../js/sunburst/sunburst-helpers.js';
import { AppSunburst } from '../js/sunburst/app.js';

export default class extends Controller {
    // Define the data attributes the controller will look for
    static values = {
        apiId: String,
        apiKey: String,
        unirefVersion: String,
        primaryIdType: String,
        dataUrl: String,
        fastaUrl: String,
        estTransferUrl: String,
        gntTransferUrl: String,
        baseFilename: String,
    }

    async connect() {
		// Abort controller is to make this code work with Turbo
        this.abortController = new AbortController();
        this.initializeSunburst(this.abortController.signal);
	}

    disconnect() {
        // When Turbo navigates away, abort the in-progress fetch request
        if (this.abortController) {
            this.abortController.abort();
        }
    }

    async initializeSunburst(signal) {
        const container = this.element;
        const hasUniref = this.unirefVersionValue !== '0';

        const apiParams = {
            apiId: this.apiIdValue,
            apiKey: this.apiKeyValue,
            sunburstUnirefVersion: this.unirefVersionValue, // The sequences that the sunburst supports
            primaryIdType: this.primaryIdTypeValue,
            scriptAppUrl: this.dataUrlValue,
            fastaAppUrl: this.fastaUrlValue,
            estTransferUrl: this.estTransferUrlValue,
            gntTransferUrl: this.gntTransferUrlValue,
            hasUniRef: hasUniref,
            // Pass the signal to the legacy code's constructor so we can abort if necessary
            signal: signal,
            baseFilename: this.baseFilenameValue,
        };

        const sunburstApp = new AppSunburst(apiParams);
        const transferHelper = new SunburstAppTransfer(sunburstApp, this.estTransferUrlValue, this.gntTransferUrlValue, this.apiIdValue, this.apiKeyValue);
        const onSunburstLoaded = transferHelper.makeOnSunburstCompleteFn();

        try {
            await sunburstApp.addSunburstFeatureAsync(onSunburstLoaded);
        } catch (error) {
            // Gracefully ignore the error thrown when a fetch is aborted
            if (error.name === 'AbortError') {
                console.log('Sunburst initialization aborted.');
                return;
            }
            console.error('Failed to initialize Sunburst feature:', error);
            container.innerHTML = `<p style="color: red;">Error loading visualization: ${error.message}</p>`;
        }
    }
}
