export const PLOTLY_CONFIG = {
    displaylogo: false,
    displayModeBar: false,
    responsive: true,
};

const EXPORT_WIDTH = 2000;
const EXPORT_HEIGHT = 1200;
const EXPORT_SCALE = 2;

const readoutFormatters = {
    length_histogram: (point) => [
        `Sequence Length: <b>${point.x}</b>`,
        `Number of Sequences: <b>${Number(point.y).toLocaleString()}</b>`,
    ],
    alignment_length: (point) => [
        `Alignment Score: <b>${point.x}</b>`,
        `Median Alignment Length: <b>${Number(point.y).toFixed(0)}</b>`,
    ],
    percent_identity: (point) => [
        `Alignment Score: <b>${point.x}</b>`,
        `Median Percent Identity: <b>${Number(point.y).toFixed(1)}%</b>`,
    ],
    edge_count: (point) => [
        `Alignment Score: <b>${point.x}</b>`,
        `Edge Count: <b>${Number(point.y).toLocaleString()}</b>`,
    ],
    edges_histogram: (point) => [
        `Alignment Score: <b>${point.x}</b>`,
        `Number of Edges: <b>${Number(point.y).toLocaleString()}</b>`,
    ],
};

export function prepareInteractiveChart(data, layout, chartKey, jobNumber) {
    for (const trace of data) {
        if (trace.type === 'scatter' && String(trace.mode).includes('markers')) {
            trace.mode = 'lines';
        }

        if (trace.name === 'Median trend') {
            trace.line = {
                ...(trace.line ?? {}),
                color: '#c23b31',
                width: 1.3,
            };
            delete trace.marker;
        }

        trace.hoverinfo = trace.type === 'box' ? 'skip' : 'none';
        delete trace.hovertemplate;
    }

    layout.title = buildChartTitle(layout.title, jobNumber);
    layout.hovermode = 'x';
    layout.annotations = [buildReadoutAnnotation()];

    if (chartKey === 'percent_identity') {
        addCrosshairGuide(layout);
        layout.yaxis = {
            ...(layout.yaxis ?? {}),
            dtick: layout.yaxis?.dtick ?? 5,
            tickformat: '',
            ticksuffix: '',
        };
    } else {
        addVerticalGuide(layout);
    }

    if (data.length > 1) {
        centerLegend(layout);
    }
}

export function bindFixedReadout(element, chartKey) {
    element.on('plotly_hover', (event) => {
        const point = event.points.find((candidate) => candidate.data.name === 'Median trend') ?? event.points[0];
        if (!point) {
            return;
        }

        const formatter = readoutFormatters[chartKey] ?? ((value) => [
            `X: <b>${value.x}</b>`,
            `Y: <b>${Number(value.y).toLocaleString()}</b>`,
        ]);

        window.Plotly.relayout(element, {
            'annotations[0].text': formatter(point).join('<br>'),
        });
    });
}

export async function exportCleanPng(element, filename) {
    downloadUrl(await renderCleanImage(element, 'png'), `${filename}.png`);
}

export async function exportCleanPdf(element, filename) {
    const imageUrl = await renderCleanImage(element, 'jpeg');
    const image = new Image();
    await new Promise((resolve, reject) => {
        image.onload = resolve;
        image.onerror = reject;
        image.src = imageUrl;
    });

    const binary = atob(imageUrl.split(',')[1]);
    const jpeg = new Uint8Array(binary.length);
    for (let index = 0; index < binary.length; index += 1) {
        jpeg[index] = binary.charCodeAt(index);
    }

    const pdf = buildJpegPdf(jpeg, image.naturalWidth, image.naturalHeight);
    const url = URL.createObjectURL(new Blob([pdf], { type: 'application/pdf' }));
    downloadUrl(url, `${filename}.pdf`);
    window.setTimeout(() => URL.revokeObjectURL(url), 4000);
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
    layout.yaxis = {
        ...(layout.yaxis ?? {}),
        showspikes: false,
    };
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
    layout.legend = {
        orientation: 'h',
        x: 0.5,
        xanchor: 'center',
        y: 1,
        yanchor: 'bottom',
    };
    layout.margin = {
        t: 80,
        r: 20,
        b: 60,
        l: 72,
        ...(layout.margin ?? {}),
    };

    if (layout.margin.t < 80) {
        layout.margin.t = 80;
    }
}

function buildChartTitle(title, jobNumber) {
    const titleText = typeof title === 'string' ? title : title?.text ?? '';
    if (!jobNumber) {
        return { text: titleText };
    }

    return { text: `${titleText} - Job ${jobNumber}` };
}

function buildReadoutAnnotation() {
    return {
        xref: 'paper',
        yref: 'paper',
        x: 0.99,
        y: 0.02,
        xanchor: 'right',
        yanchor: 'bottom',
        align: 'left',
        showarrow: false,
        bordercolor: '#9aa4b2',
        borderwidth: 1,
        borderpad: 6,
        bgcolor: 'rgba(255, 255, 255, 0.92)',
        font: { size: 12 },
        text: 'Hover the plot',
    };
}

function cleanExportFigure(sourceElement) {
    const data = clone(sourceElement.data).map((trace) => {
        trace.hoverinfo = 'skip';

        if (trace.type === 'scatter') {
            trace.line = { ...(trace.line ?? {}), width: 1.3 };
            if (trace.marker) {
                trace.marker.size = Math.min(trace.marker.size ?? 4, 3);
            }
        }

        if (trace.type === 'box') {
            trace.line = { ...(trace.line ?? {}), width: 0.5 };
        }

        if (trace.type === 'bar') {
            trace.marker = { ...(trace.marker ?? {}), line: { width: 0 } };
        }

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

    if (layout.title) {
        layout.title = {
            text: typeof layout.title === 'string' ? layout.title : layout.title.text,
            font: { size: 28 },
        };
    }

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
        return await window.Plotly.toImage(exportElement, {
            format,
            width: EXPORT_WIDTH,
            height: EXPORT_HEIGHT,
            scale: EXPORT_SCALE,
        });
    } finally {
        window.Plotly.purge(exportElement);
        exportElement.remove();
    }
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
    for (let index = 1; index <= 5; index += 1) {
        xref += `${String(offsets[index]).padStart(10, '0')} 00000 n \n`;
    }

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
