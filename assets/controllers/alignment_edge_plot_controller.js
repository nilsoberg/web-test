import { Controller } from '@hotwired/stimulus';

import '../js/plotly.min.js';
import {
    bindFixedReadout,
    exportCleanPdf,
    exportCleanPng,
    PLOTLY_CONFIG,
    prepareInteractiveChart,
} from '../js/plotly_chart_helpers.js';

export default class extends Controller {
    static targets = ['actions', 'plot'];

    static values = {
        chartKey: { type: String, default: 'edge_count' },
        jobNumber: Number,
        url: String,
    }

    connect() {
        this.abortController = new AbortController();
        this.initializePlot(this.abortController.signal);
    }

    disconnect() {
        if (this.abortController) {
            this.abortController.abort();
        }

        if (window.Plotly && this.hasPlotTarget) {
            window.Plotly.purge(this.plotTarget);
        }
    }

    async initializePlot(signal) {
        try {
            const response = await fetch(this.urlValue, { signal });
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const text = await response.text();
            const { x, y } = this.parseEdgeData(text);
            const data = [{
                x,
                y,
                type: 'scatter',
                mode: 'lines',
                line: { color: 'blue' },
                name: 'Edges',
            }];
            const layout = {
                title: 'Edge Count vs Alignment Score',
                xaxis: { title: 'Alignment Score' },
                yaxis: { title: 'Edge Count' },
            };

            prepareInteractiveChart(data, layout, this.chartKeyValue, this.jobNumberValue);
            this.plotTarget.replaceChildren();
            await window.Plotly.newPlot(this.plotTarget, data, layout, PLOTLY_CONFIG);
            bindFixedReadout(this.plotTarget, this.chartKeyValue);
            this.showActions();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error('Error fetching or plotting edge data:', error);
            this.plotTarget.textContent = 'Could not load chart data.';
            this.hideActions();
        }
    }

    parseEdgeData(text) {
        const lines = text.trim().split('\n');
        const x = [];
        const y = [];

        for (let index = 1; index < lines.length; index += 1) {
            const parts = lines[index].split('\t');
            const score = Number.parseFloat(parts[0]);
            const edgeCount = Number.parseInt(parts[2], 10);

            if (!Number.isNaN(score) && !Number.isNaN(edgeCount)) {
                x.push(score);
                y.push(edgeCount);
            }
        }

        return { x, y };
    }

    async downloadPng(event) {
        await this.exportChart(event.currentTarget, 'png');
    }

    async downloadPdf(event) {
        await this.exportChart(event.currentTarget, 'pdf');
    }

    async exportChart(button, format) {
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Preparing...';

        try {
            const filename = `${this.chartKeyValue}_job${this.jobNumberValue}`;
            if (format === 'pdf') {
                await exportCleanPdf(this.plotTarget, filename);
            } else {
                await exportCleanPng(this.plotTarget, filename);
            }
        } catch (error) {
            console.error(`Could not export edge chart as ${format}:`, error);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    hideActions() {
        if (this.hasActionsTarget) {
            this.actionsTarget.hidden = true;
        }
    }

    showActions() {
        if (this.hasActionsTarget) {
            this.actionsTarget.hidden = false;
        }
    }
}
