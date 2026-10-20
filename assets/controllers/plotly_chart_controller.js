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
        chartKey: { type: String, default: 'chart' },
        fallbackAlt: { type: String, default: 'Chart fallback image' },
        fallbackImageUrl: String,
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

            const rawConfig = await response.json();
            const { data, extraTraces, layout } = this.normalizePlotConfig(rawConfig);
            const allTraces = [...data, ...extraTraces];
            prepareInteractiveChart(allTraces, layout, this.chartKeyValue, this.jobNumberValue);

            this.plotTarget.replaceChildren();
            await window.Plotly.newPlot(this.plotTarget, data, layout, PLOTLY_CONFIG);

            if (extraTraces.length > 0) {
                await window.Plotly.addTraces(this.plotTarget, extraTraces);
            }

            bindFixedReadout(this.plotTarget, this.chartKeyValue);
            this.showActions();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error('Error fetching or plotting chart JSON:', error);
            this.renderFallback();
        }
    }

    renderFallback() {
        if (!this.hasFallbackImageUrlValue) {
            this.plotTarget.textContent = 'Could not load chart data.';
            this.hideActions();
            return;
        }

        const image = document.createElement('img');
        image.src = this.fallbackImageUrlValue;
        image.alt = this.fallbackAltValue;
        image.width = 800;

        this.plotTarget.replaceChildren(image);
        this.hideActions();
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
            console.error(`Could not export chart as ${format}:`, error);
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

    normalizePlotConfig(rawConfig) {
        if (rawConfig && Array.isArray(rawConfig.data)) {
            return this.normalizePlotlyConfig(rawConfig);
        }

        if (rawConfig && typeof rawConfig.type === 'string') {
            return this.normalizeStatsConfig(rawConfig);
        }

        throw new Error('Chart JSON does not match a supported Plotly schema');
    }

    normalizePlotlyConfig(rawConfig) {
        const data = rawConfig.data.map((trace) => this.normalizeTrace(trace, rawConfig));
        const layout = this.buildLayout(rawConfig.layout ?? {}, data, rawConfig);
        const hasMedianTrend = data.some((trace) => trace.name === 'Median trend');
        const extraTraces = hasMedianTrend
            ? []
            : data
                .filter((trace) => trace.type === 'box')
                .map((trace) => this.buildMedianTrendTrace(trace, layout))
                .filter(Boolean);

        return { data, extraTraces, layout };
    }

    normalizeStatsConfig(rawConfig) {
        const trace = this.normalizeTrace(rawConfig, rawConfig);
        const data = [trace];
        const layout = this.buildLayout({
            title: rawConfig.title,
            xaxis: { title: rawConfig.x_axis_title },
            yaxis: { title: rawConfig.y_axis_title },
        }, data, rawConfig);
        const extraTraces = trace.type === 'box'
            ? [this.buildMedianTrendTrace(trace, layout)].filter(Boolean)
            : [];

        return { data, extraTraces, layout };
    }

    normalizeTrace(trace, rawConfig) {
        if (trace.type === 'box') {
            return this.normalizeBoxTrace(trace, rawConfig);
        }

        if (trace.type === 'bar') {
            return this.normalizeBarTrace(trace, rawConfig);
        }

        return trace;
    }

    normalizeBoxTrace(trace, rawConfig) {
        return {
            boxpoints: false,
            fillcolor: 'rgba(72, 127, 255, 0.18)',
            line: {
                color: 'rgba(53, 92, 125, 0.5)',
                width: 1.5,
            },
            marker: {
                color: '#355c7d',
            },
            name: trace.name ?? 'Distribution',
            ...trace,
        };
    }

    normalizeBarTrace(trace, rawConfig) {
        return {
            hovertemplate: this.buildBarHoverTemplate(rawConfig),
            marker: {
                color: '#4c78a8',
            },
            name: trace.name ?? 'Values',
            ...trace,
        };
    }

    buildLayout(layout, data, rawConfig) {
        const hasBoxTrace = data.some((trace) => trace.type === 'box');
        const identityPlot = this.isIdentityPlot(layout, rawConfig);
        const xValues = data.flatMap((trace) => Array.isArray(trace.x) ? trace.x : []);

        const xaxis = {
            gridcolor: '#e5e7eb',
            linecolor: '#475569',
            linewidth: 1,
            showline: true,
            showspikes: hasBoxTrace,
            spikedash: 'solid',
            spikesnap: 'hovered data',
            spikemode: 'across+marker',
            tickcolor: '#475569',
            ticks: 'outside',
            zeroline: true,
            zerolinecolor: '#94a3b8',
            ...(layout.xaxis ?? {}),
        };

        const yaxis = {
            gridcolor: '#e5e7eb',
            linecolor: '#475569',
            linewidth: 1,
            showline: true,
            showspikes: hasBoxTrace,
            spikedash: 'solid',
            spikesnap: 'hovered data',
            spikemode: 'across+marker',
            tickcolor: '#475569',
            ticks: 'outside',
            zeroline: true,
            zerolinecolor: '#94a3b8',
            ...(layout.yaxis ?? {}),
        };

        if (identityPlot) {
            yaxis.range = yaxis.range ?? [0, 100];
            yaxis.ticksuffix = yaxis.ticksuffix ?? '%';
            yaxis.dtick = yaxis.dtick ?? 5;
        }

        if (hasBoxTrace && xValues.length > 0) {
            const maxX = Math.max(...xValues.map((value) => Number(value)));
            xaxis.range = xaxis.range ?? [0, maxX];
        }

        return {
            autosize: true,
            hovermode: hasBoxTrace ? 'x unified' : 'closest',
            legend: {
                orientation: 'h',
                x: 0,
                xanchor: 'left',
                y: 1.1,
            },
            margin: {
                b: 70,
                l: 75,
                r: 30,
                t: 80,
            },
            paper_bgcolor: '#ffffff',
            plot_bgcolor: '#ffffff',
            ...layout,
            xaxis,
            yaxis,
        };
    }

    buildMedianTrendTrace(trace) {
        if (!Array.isArray(trace.median) || !Array.isArray(trace.x) || trace.median.length !== trace.x.length) {
            return null;
        }

        return {
            type: 'scatter',
            mode: 'lines',
            name: 'Median trend',
            x: trace.x,
            y: trace.median,
            line: {
                color: '#c23b31',
                width: 1.3,
            },
            hoverinfo: 'none',
        };
    }

    buildBarHoverTemplate(rawConfig) {
        const xTitle = rawConfig.x_axis_title ?? rawConfig.layout?.xaxis?.title?.text ?? rawConfig.layout?.xaxis?.title ?? 'X';
        const yTitle = rawConfig.y_axis_title ?? rawConfig.layout?.yaxis?.title?.text ?? rawConfig.layout?.yaxis?.title ?? 'Y';

        return `${xTitle}: %{x}<br>${yTitle}: %{y:,}<extra></extra>`;
    }

    isIdentityPlot(layout, rawConfig) {
        const title = String(rawConfig.title ?? layout.title?.text ?? layout.title ?? '').toLowerCase();
        const yTitle = String(rawConfig.y_axis_title ?? layout.yaxis?.title?.text ?? layout.yaxis?.title ?? '').toLowerCase();

        return title.includes('identity') || yTitle.includes('identity');
    }

}
