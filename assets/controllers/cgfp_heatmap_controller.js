
import { Controller } from '@hotwired/stimulus';

import '../js/plotly.min.js';
import { HeatmapApp } from '../js/heatmap.js';

export default class extends Controller {
    static targets = [
        "plot", "loader", "filterForm",
        "meanCb", "hitsOnlyCb", "clusterFilter", 
        "minThresh", "maxThresh", "bsItem", "catItem",
        "bodySitesContainer",
    ]

    static values = {
        id: String,
        quantifyId: String,
        key: String,
        staticExample: Boolean,
        resType: String,
        width: Number,
        height: Number,
        dataRoute: String,
        jobName: String,
    }

    connect() {
        const paramData = {
            Id: this.idValue,
            QuantifyId: this.quantifyIdValue,
            Key: this.keyValue,
            StaticExample: this.staticExampleValue,
            ResType: this.resTypeValue,
            Width: this.widthValue || 950,
            Height: this.heightValue || 700,
            DataRoute: this.dataRouteValue,
            JobName: this.jobNameValue,
        };

        this.heatmapApp = new HeatmapApp(paramData, this.loaderTarget, true);

        this.applyFilter();
    }

    applyFilter(event) {
        if (event) event.preventDefault();

        const params = new FormData();

        // Read states using Stimulus targets
        const useMean = this.hasMeanCbTarget ? this.meanCbTarget.checked : false;
        const hitsOnly = this.hasHitsOnlyCbTarget ? this.hitsOnlyCbTarget.checked : false;

        if (useMean) params.append("use-mean", 1);
        if (hitsOnly) params.append("hits-only", 1);

        if (this.hasClusterFilterTarget && this.clusterFilterTarget.value) {
            params.append("clusters", this.clusterFilterTarget.value);
        }

        if (this.hasMinThreshTarget && !isNaN(this.minThreshTarget.value)) {
            params.append("lower_thresh", this.minThreshTarget.value);
        }

        if (this.hasMaxThreshTarget && !isNaN(this.maxThreshTarget.value)) {
            params.append("upper-thresh", this.maxThreshTarget.value);
        }

        // Gather array of checked body sites
        const bodysites = this.bsItemTargets
            .filter(item => item.checked)
            .map(item => item.value)
            .join("|");
        if (bodysites) params.append("bodysites", bodysites);

        // Pass UI state to HeatmapApp so it doesn't need to read the DOM itself
        this.heatmapApp.setFilterState({ useMean, hitsOnly });

        // Trigger the backend call
        this.heatmapApp.fetchData(params, (jsonObj) => {
            if (jsonObj && jsonObj.valid) {
                this.updateUIFilters(jsonObj);
            }
        });
    }

    /**
     * Process the raw data provided by the backend.
     */
    updateUIFilters(data) {
        // We only populate if the input is empty so we don't overwrite deliberate user filters
        if (data.extent) {
            if (this.hasMinThreshTarget && this.minThreshTarget.value === "") {
                this.minThreshTarget.value = data.extent.min;
            }
            if (this.hasMaxThreshTarget && this.maxThreshTarget.value === "") {
                this.maxThreshTarget.value = data.extent.max;
            }
        }

        // Populate Body Sites
        if (data.site_info && this.hasBodySitesContainerTarget) {

            // Record currently checked sites so we don't lose the user's selection on reload
            const checkedSites = new Set(
                this.bsItemTargets.filter(item => item.checked).map(item => item.value)
            );

            // Extract unique body sites from the object values
            const uniqueSites = [...new Set(Object.values(data.available_body_sites))].sort();

            this.bodySitesContainerTarget.innerHTML = '';

            uniqueSites.forEach((site, index) => {
                const wrapper = document.createElement('div');
                wrapper.className = 'form-check form-check-inline';

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.value = site; // Safe assigned property
                input.id = `site-cb-${index}`;
                input.className = 'form-check-input filter-bs-item';
                input.setAttribute('data-cgfp-heatmap-target', 'bsItem');
                input.checked = checkedSites.has(site);

                const label = document.createElement('label');
                label.className = 'form-check-label';
                label.htmlFor = `site-cb-${index}`;
                label.textContent = site; // .textContent automatically escapes all HTML/scripts

                wrapper.appendChild(input);
                wrapper.appendChild(label);
                this.bodySitesContainerTarget.appendChild(wrapper);
            });
        }
    }

    resetFilter(event) {
        if (event) event.preventDefault();

        if (this.hasFilterFormTarget) {
            this.filterFormTarget.reset();
        }

        // Clear thresholds so they re-populate from the server extent
        if (this.hasMinThreshTarget) this.minThreshTarget.value = "";
        if (this.hasMaxThreshTarget) this.maxThreshTarget.value = "";

        this.applyFilter();
    }
}
