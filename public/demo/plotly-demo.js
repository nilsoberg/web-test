const JOB_NUMBER = 788;
const EXPORT_WIDTH = 2000;
const EXPORT_HEIGHT = 1200;
const EXPORT_SCALE = 2;
const CHART_CONFIG = { displaylogo: false, displayModeBar: false, responsive: true };

const charts = [
    { key: 'length_histogram', title: 'Sequences as a Function of Full Length', description: 'Fixed sequence-length readout with a thin vertical guide.' },
    { key: 'alignment_length', title: 'Alignment Length vs Alignment Score', description: 'Distribution and median trend with no floating statistics or trend markers.' },
    { key: 'percent_identity', title: 'Percent Identity vs Alignment Score', description: 'Fixed percent readout with both horizontal and vertical guides.' },
    { key: 'edge_count', title: 'Edge Count vs Alignment Score', description: 'The full SSN size preview is now a clean line without point markers.' },
    { key: 'edges_histogram', title: 'Edges as a Function of Alignment Score', description: 'The SSN diversity preview uses the same fixed readout and vertical guide.' },
];

const readoutFormatters = {
    length_histogram: (point) => [`Sequence Length: <b>${point.x}</b>`, `Number of Sequences: <b>${Number(point.y).toLocaleString()}</b>`],
    alignment_length: (point) => [`Alignment Score: <b>${point.x}</b>`, `Median Alignment Length: <b>${Number(point.y).toFixed(0)}</b>`],
    percent_identity: (point) => [`Alignment Score: <b>${point.x}</b>`, `Median Percent Identity: <b>${Number(point.y).toFixed(1)}%</b>`],
    edge_count: (point) => [`Alignment Score: <b>${point.x}</b>`, `Edge Count: <b>${Number(point.y).toLocaleString()}</b>`],
    edges_histogram: (point) => [`Alignment Score: <b>${point.x}</b>`, `Number of Edges: <b>${Number(point.y).toLocaleString()}</b>`],
};

const chartGrid = document.getElementById('chart-grid');

for (const [index, chart] of charts.entries()) {
    chartGrid.appendChild(buildChartCard(chart, index));
    renderChart(chart).catch((error) => {
        console.error(`Failed to render ${chart.key}:`, error);
        document.getElementById(`chart-${chart.key}`).textContent = 'Could not load chart data.';
    });
}

function buildChartCard(chart, index) {
    const card = document.createElement('article');
    card.className = 'chart-card';
    card.innerHTML = `
        <div class="chart-heading">
            <div><h2>${chart.title}</h2><p>${chart.description}</p></div>
            <span class="chart-number">${String(index + 1).padStart(2, '0')}</span>
        </div>
        <div id="chart-${chart.key}" class="chart-target"></div>
        <div class="chart-actions">
            <button class="download-button" type="button" data-format="png">Download PNG</button>
            <button class="download-button secondary" type="button" data-format="pdf">Download PDF</button>
        </div>`;
    return card;
}

async function renderChart(chart) {
    const source = window.CHART_DATA?.[chart.key];
    if (!source) throw new Error(`Missing Job 788 data for ${chart.key}`);

    const element = document.getElementById(`chart-${chart.key}`);
    const data = clone(source.data);
    const layout = clone(source.layout);
    delete layout.width;
    delete layout.height;
    layout.margin = layout.margin ?? { t: 72, r: 20, b: 60, l: 72 };
    layout.title = buildChartTitle(layout.title);
    layout.hovermode = 'x';
    layout.annotations = [buildReadoutAnnotation()];

    for (const trace of data) {
        if (trace.type === 'scatter' && String(trace.mode).includes('markers')) trace.mode = 'lines';
        if (trace.name === 'Median trend') {
            trace.line = { ...(trace.line ?? {}), color: '#c23b31', width: 1.3 };
            delete trace.marker;
        }
        trace.hoverinfo = trace.type === 'box' ? 'skip' : 'none';
        delete trace.hovertemplate;
    }

    if (chart.key === 'percent_identity') {
        addCrosshairGuide(layout);
        layout.yaxis = { ...(layout.yaxis ?? {}), tickformat: '', ticksuffix: '' };
    } else {
        addVerticalGuide(layout);
    }

    if (data.length > 1) centerLegend(layout);
    await window.Plotly.newPlot(element, data, layout, CHART_CONFIG);

    element.on('plotly_hover', (event) => {
        const point = event.points.find((candidate) => candidate.data.name === 'Median trend') ?? event.points[0];
        if (!point) return;
        window.Plotly.relayout(element, { 'annotations[0].text': readoutFormatters[chart.key](point).join('<br>') });
    });

    const card = element.closest('.chart-card');
    for (const button of card.querySelectorAll('[data-format]')) {
        button.addEventListener('click', () => exportChart(element, chart.key, button));
    }
}

function addVerticalGuide(layout) {
    layout.xaxis = {
        ...(layout.xaxis ?? {}),
        showspikes: true,
        spikecolor: '#000000',
        spikethickness: 1,
        spikemode: 'across',
        spikedash: 'solid',
        spikesnap: 'hovered data',
    };
    layout.yaxis = { ...(layout.yaxis ?? {}), showspikes: false };
}

function addCrosshairGuide(layout) {
    addVerticalGuide(layout);
    layout.yaxis = {
        ...(layout.yaxis ?? {}),
        showspikes: true,
        spikecolor: '#000000',
        spikethickness: 1,
        spikemode: 'across',
        spikedash: 'solid',
        spikesnap: 'hovered data',
    };
}

function centerLegend(layout) {
    layout.showlegend = true;
    layout.legend = { orientation: 'h', x: 0.5, xanchor: 'center', y: 1, yanchor: 'bottom' };
    layout.margin = { t: 80, r: 20, b: 60, l: 72, ...layout.margin };
    if (layout.margin.t < 80) layout.margin.t = 80;
}

function buildReadoutAnnotation() {
    return {
        xref: 'paper', yref: 'paper', x: 0.99, y: 0.02,
        xanchor: 'right', yanchor: 'bottom', align: 'left', showarrow: false,
        bordercolor: '#9aa4b2', borderwidth: 1, borderpad: 6,
        bgcolor: 'rgba(255, 255, 255, 0.92)', font: { size: 12 }, text: 'Hover the plot',
    };
}

function getTitleText(title) {
    return typeof title === 'string' ? title : title?.text ?? '';
}

function buildChartTitle(title) {
    const titleText = getTitleText(title);
    if (!window.matchMedia('(max-width: 720px)').matches) {
        return { text: `${titleText} - Job ${JOB_NUMBER}` };
    }

    const wrappedTitle = titleText
        .replace(' vs ', '<br>vs ')
        .replace(' at ', '<br>at ');

    return {
        text: `${wrappedTitle}<br>Job ${JOB_NUMBER}`,
        font: { size: 15 },
        x: 0.5,
        xanchor: 'center',
    };
}

async function exportChart(sourceElement, key, button) {
    const format = button.dataset.format;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Preparing...';

    try {
        if (format === 'pdf') {
            await exportCleanPdf(sourceElement, `${key}_job${JOB_NUMBER}`);
        } else {
            downloadUrl(await renderCleanImage(sourceElement, 'png'), `${key}_job${JOB_NUMBER}.png`);
        }
    } catch (error) {
        console.error(`Could not export ${key} as ${format}:`, error);
        window.alert(`Could not prepare the ${format.toUpperCase()} export.`);
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
}

function cleanExportFigure(sourceElement) {
    const data = clone(sourceElement.data).map((trace) => {
        trace.hoverinfo = 'skip';
        if (trace.type === 'scatter') {
            trace.line = { ...(trace.line ?? {}), width: 1.3 };
            if (trace.marker) trace.marker.size = Math.min(trace.marker.size ?? 4, 3);
        }
        if (trace.type === 'box') trace.line = { ...(trace.line ?? {}), width: 0.5 };
        if (trace.type === 'bar') trace.marker = { ...(trace.marker ?? {}), line: { width: 0 } };
        return trace;
    });
    const layout = clone(sourceElement.layout);
    layout.annotations = [];
    layout.font = { ...(layout.font ?? {}), size: 20 };

    for (const axisName of ['xaxis', 'yaxis']) {
        const axis = layout[axisName] ?? {};
        axis.showspikes = false;
        axis.title = { ...(axis.title ?? {}), font: { size: 22 } };
        axis.tickfont = { ...(axis.tickfont ?? {}), size: 18 };
        layout[axisName] = axis;
    }

    if (layout.title) layout.title = { text: getTitleText(layout.title), font: { size: 28 } };
    layout.width = EXPORT_WIDTH;
    layout.height = EXPORT_HEIGHT;
    return { data, layout };
}

async function renderCleanImage(sourceElement, format) {
    const { data, layout } = cleanExportFigure(sourceElement);
    const exportElement = document.createElement('div');
    exportElement.style.cssText = `position:fixed;left:-10000px;top:0;width:${EXPORT_WIDTH}px;height:${EXPORT_HEIGHT}px`;
    document.body.appendChild(exportElement);

    try {
        await window.Plotly.newPlot(exportElement, data, layout, { staticPlot: true });
        return await window.Plotly.toImage(exportElement, { format, width: EXPORT_WIDTH, height: EXPORT_HEIGHT, scale: EXPORT_SCALE });
    } finally {
        window.Plotly.purge(exportElement);
        exportElement.remove();
    }
}

async function exportCleanPdf(sourceElement, filename) {
    const imageUrl = await renderCleanImage(sourceElement, 'jpeg');
    const image = new Image();
    await new Promise((resolve, reject) => {
        image.onload = resolve;
        image.onerror = reject;
        image.src = imageUrl;
    });

    const binary = atob(imageUrl.split(',')[1]);
    const jpeg = new Uint8Array(binary.length);
    for (let index = 0; index < binary.length; index += 1) jpeg[index] = binary.charCodeAt(index);

    const url = URL.createObjectURL(new Blob([buildJpegPdf(jpeg, image.naturalWidth, image.naturalHeight)], { type: 'application/pdf' }));
    downloadUrl(url, `${filename}.pdf`);
    window.setTimeout(() => URL.revokeObjectURL(url), 4000);
}

function buildJpegPdf(jpeg, imageWidth, imageHeight) {
    const pageWidth = 1000;
    const pageHeight = 600;
    const parts = [];
    const offsets = {};
    let length = 0;
    const appendText = (value) => {
        const bytes = new TextEncoder().encode(value);
        parts.push(bytes);
        length += bytes.length;
    };
    const appendBytes = (bytes) => {
        parts.push(bytes);
        length += bytes.length;
    };

    appendText('%PDF-1.4\n');
    offsets[1] = length;
    appendText('1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n');
    offsets[2] = length;
    appendText('2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n');
    offsets[3] = length;
    appendText(`3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pageWidth} ${pageHeight}] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>\nendobj\n`);
    offsets[4] = length;
    appendText(`4 0 obj\n<< /Type /XObject /Subtype /Image /Width ${imageWidth} /Height ${imageHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${jpeg.length} >>\nstream\n`);
    appendBytes(jpeg);
    appendText('\nendstream\nendobj\n');

    const content = new TextEncoder().encode(`q\n${pageWidth} 0 0 ${pageHeight} 0 0 cm\n/Im0 Do\nQ\n`);
    offsets[5] = length;
    appendText(`5 0 obj\n<< /Length ${content.length} >>\nstream\n`);
    appendBytes(content);
    appendText('\nendstream\nendobj\n');

    const xrefStart = length;
    let xref = 'xref\n0 6\n0000000000 65535 f \n';
    for (let index = 1; index <= 5; index += 1) xref += `${String(offsets[index]).padStart(10, '0')} 00000 n \n`;
    appendText(xref);
    appendText(`trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF\n`);

    const output = new Uint8Array(length);
    let position = 0;
    for (const part of parts) {
        output.set(part, position);
        position += part.length;
    }
    return output;
}

function downloadUrl(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
}

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}
