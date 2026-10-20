
import { Controller } from '@hotwired/stimulus';

import '../js/plotly.min.js';
import { BoxplotApp } from '../js/heatmap.js';

export default class extends Controller {
    static targets = [
        "plot", "searchForm", "input",
    ]

    static values = {
        id: String,
        quantifyId: String,
        key: String,
        staticExample: Boolean,
        resType: String,
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
            DataRoute: this.dataRouteValue,
            JobName: this.jobNameValue,
        };

        this.boxplotApp = new BoxplotApp(paramData, true);
    }

    addCluster(event) {
        event.preventDefault();

        const clusterNumber = this.inputTarget.value.trim();

        if (!clusterNumber) {
            this.resetForm();
        } else {
            this.fetchDataAndRender(clusterNumber);
        }
    }

    fetchDataAndRender(searchStr) {
        const params = new FormData();

        if (searchStr) {
            params.append("clusters", searchStr);
        }

        this.boxplotApp.fetchData(params, (jsonObj) => { });
    }

    resetForm(event) {
        if (event) event.preventDefault();

        if (this.hasSearchFormTarget) {
            this.searchForm.reset();
        }

        this.fetchDataAndRender();
    }
}
