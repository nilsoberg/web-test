
export class QuantifyResults {
    constructor(paramData, progLoader, useLogScale = true) {
        this.params = paramData;

        // Accept either a native DOM element (from Stimulus target) or a string selector
        this.progLoaderObj = typeof progLoader === 'string' ? document.querySelector(progLoader) : progLoader;

        this.formAction = paramData.DataRoute;
        this.useLogScale = useLogScale;

        this.mgColors = [
            "RoyalBlue",
            "BurlyWood",
            "Gray",
            "Turquoise",
            "SeaGreen",
            "Orange",
            "Salmon",
            "Purple",
            "LightSteelBlue",
        ];

        this.convertRawValueFn = this.useLogScale 
            ? (rawVal) => {
                const minLog = 4;
                const minLogVal = Math.pow(10, -minLog);
                const val = rawVal >= minLogVal ? Math.log10(rawVal) : -minLog;
                return minLog + val;
            } 
            : (rawVal) => rawVal;
    }

    convertRawValue(rawValue) {
        return this.convertRawValueFn(rawValue);
    }

    async doFormPost(params, responseCallback) {
        if (this.params.StaticExample) {
            params.append("static-example", 1);
        } else {
            params.append("id", this.params.Id);
            params.append("key", this.params.Key);
            if (this.params.QuantifyId)
                params.append("quantify-id", this.params.QuantifyId);
        }

        this.showProgressLoader();
        params.append("res", this.params.ResType);

        try {
            const response = await fetch(this.formAction, {
                method: "POST",
                body: params
            });
            if (response.ok) {
                const jsonObj = await response.json();
                responseCallback(jsonObj);
            } else {
                console.error(`HTTP error! status: ${response.status}`);
            }
        } catch (error) {
            console.error("Error during form post:", error);
        } finally {
            this.hideProgressLoader();
        }
    }

    getColor(color, lastBodysiteIndex, mg) {
        return color ? color : this.mgColors[lastBodysiteIndex % this.mgColors.length];
    }

    getMetagenomeIndexBodySiteMap(metagenomes, siteData) {
        const map = {};
        metagenomes.forEach((mg, i) => {
            map[i] = mg in siteData[mg] ? siteData[mg].site : "Unknown";
        });
        return map;
    }

    getBodySiteInfo(metagenomes, siteData) {
        const order = {};
        const color = {};

        for (const mg of metagenomes) {
            const site = siteData[mg].site;
            if (!(site in order)) {
                order[site] = siteData[mg].order;
                color[site] = siteData[mg].color;
            }
        }

        return { order, color };
    }

    showProgressLoader() {
        if (this.progLoaderObj) {
            this.progLoaderObj.classList.remove("hidden");
        }
    }

    hideProgressLoader() {
        if (this.progLoaderObj) {
            this.progLoaderObj.classList.add("hidden");
        }
    }
}


export class BoxplotApp extends QuantifyResults {
    constructor(paramData) {
        super(paramData, null, false);
        this.containerId = "#master-plot-container";
    }

    getDataForCluster(clusterData, metagenomeMap, siteInfo) {
        const grouped = {};
        const sites = [];

        clusterData.abundance.forEach((abundanceValue, i) => {
            const bodySite = metagenomeMap[i];
            if (!(bodySite in grouped)) {
                grouped[bodySite] = this.createBoxPlotTrace(bodySite, siteInfo.color[bodySite]);
                sites.push(bodySite);
            }
            const logValue = this.convertRawValue(abundanceValue);
            grouped[bodySite].y.push(logValue);
        });

        sites.sort((a, b) => siteInfo.order[a] - siteInfo.order[b]);
        return sites.map(bodySite => grouped[bodySite]);
    }

    createBoxPlotTrace(bodySite, color) {
        const fmtSite = bodySite.replace(" ", "<br>");
        return {
            y: [],
            type: "box",
            name: fmtSite,
            opacity: 1,
            marker: {
                size: 5,
                color: color,
            },
            fillcolor: color,
            jitter: 0.5,
            whiskerwidth: 0.2,
            line: {
                width: 1,
                color: "#555",
            },
        };
    }

    clearPlotContainer() {
        const container = document.querySelector(this.containerId);
        if (container) {
            container.innerHTML = '';
        }
    }

    fetchData(params, finishFn = () => {}) {
        super.doFormPost(params, (jsonObj) => {
            if (jsonObj.valid) {
                this.processData(jsonObj);
            }
            finishFn(jsonObj);
        });
    }

    processData(rawData) {
        const mgMap = super.getMetagenomeIndexBodySiteMap(rawData.metagenomes, rawData.site_info);
        const siteInfo = super.getBodySiteInfo(rawData.metagenomes, rawData.site_info);

        this.clearPlotContainer();
        const plotContainer = document.querySelector(this.containerId);
        if (!plotContainer) return;

        for (const cluster of rawData.clusters) {
            const groupedData = this.getDataForCluster(cluster, mgMap, siteInfo);
            const plotName = `Cluster ${cluster.number}`;

            const layout = {
                width: 450,
                height: 450,
                title: plotName,
                showlegend: false,
                margin: { l: 70, t: 50, r: 30, b: 50 },
                xaxis: { ticklen: 0, tickfont: { size: 10 } },
                yaxis: { title: { text: "Copies per microbial genome" }, ticksuffix: "  " },
                shapes: [
                    {
                        type: 'line',
                        xref: 'paper',
                        yref: 'paper',
                        x0: 0,
                        y0: 0.05,
                        x1: 0,
                        y1: 1,
                        line: { color: "#000", width: 0.5 },
                    },
                ],
                annotations: [
                    {
                        xref: 'paper',
                        yref: 'paper',
                        x: -0.17,
                        y: 0.5,
                        xanchor: 'left',
                        yanchor: 'center',
                        text: "Copies per microbial genome",
                        showarrow: false,
                        textangle: 270,
                    },
                ],
            };

            const divId = `boxplot-${cluster.number}`;
            plotContainer.insertAdjacentHTML('beforeend', `<div id="${divId}"></div>`);
            Plotly.newPlot(divId, groupedData, layout);
        }
    }

    search(clusterQuery) {
        const params = new FormData();
        params.append("clusters", clusterQuery);

        super.doFormPost(params, (jsonObj) => {
            if (jsonObj.valid) {
                this.processData(jsonObj);
            }
        });
    }
}


export class HeatmapApp extends QuantifyResults {
    constructor(paramData, progLoader, useBoxplots) {
        super(paramData, progLoader, true);

        this.Width = this.params.Width ?? 950;
        this.Height = this.params.Height ?? 700;

        this.colorsBinary = [
            'rgb(255, 255, 255)',
            'rgb(0, 0, 0)',
        ];
        this.colors6 = [
            'rgb(68, 1, 84)',
            'rgb(65, 68, 135)',
            'rgb(42, 120, 142)',
            'rgb(34, 168, 132)',
            'rgb(122, 209, 81)',
            'rgb(253, 231, 37)',
        ];

        this.bodySites = [];
        this.useBoxplots = useBoxplots;
        this.filterState = { useMean: false, hitsOnly: false };
    }

    getBodySites() {
        return this.bodySites;
    }

    getCategories() {
        return [];
    }

    linspace(a, b, n = Math.max(Math.round(b - a) + 1, 1)) {
        if (n < 2) return n === 1 ? [a] : [];
        const ret = Array(n);
        n--;
        for (let i = n; i >= 0; i--) { 
            ret[i] = (i * b + (n - i) * a) / n; 
        }
        return ret;
    }

    setFilterState(state) {
        this.filterState = state;
    }

    fetchData(params, finishFn = () => {}) {
        super.doFormPost(params, (jsonObj) => {
            if (jsonObj.valid) {
                this.processData(jsonObj);
            }
            finishFn(jsonObj);
        });
    }

    processData(data) {
        const hitsOnly = this.filterState?.hitsOnly || false;
        const useMean = this.filterState?.useMean || false; 
        const logScale = !hitsOnly;

        const convertRawValue = (rawVal) => {
            if (!logScale) return rawVal;
            const minLog = 4;
            const minLogVal = Math.pow(10, -minLog);
            const val = rawVal >= minLogVal ? Math.log10(rawVal) : -minLog;
            return minLog + val;
        };

        const numMetagenomes = data.metagenomes.length;
        const numClusters = data.clusters.length;

        const useManualExtent = true;
        const min = convertRawValue(0);
        const max = convertRawValue(1);

        const x = [];
        const y = this.linspace(1, numClusters);
        const z = [];
        const bodysiteMgLabels = [];
        const secondary = [];
        const label = [];
        const bodySiteMap = {};
        const regions = [];

        let lastBodysite = "";
        let lastBodysiteIndex = 0;
        let lastColor = "";

        data.metagenomes.forEach((mg, i) => {
            const bodysite = data.site_info[mg].site;
            const color = data.site_info[mg].color;
            const regionColor = this.getColor(color, regions.length, mg);
            x.push(mg);
            bodysiteMgLabels.push(bodysite);
            secondary.push('');

            if (i === 0) lastBodysite = bodysite;
            if (bodysite !== lastBodysite) {
                regions.push([lastBodysiteIndex, i - 1, lastBodysite, lastColor]);
                lastBodysite = bodysite;
                lastBodysiteIndex = i;
            }
            lastColor = regionColor;
            bodySiteMap[mg] = bodysite;
        });

        regions.push([lastBodysiteIndex, numMetagenomes - 1, bodysiteMgLabels[numMetagenomes - 1], lastColor]);

        this.bodySites = [];
        bodysiteMgLabels.forEach(bsLabel => {
            if (!(bsLabel in this.bodySites)) {
                this.bodySites.push(bsLabel);
                this.bodySites[bsLabel] = 1;
            }
        });

        data.clusters.forEach((cluster, i) => {
            z.push([]);
            label.push([]);
            y[i] = cluster.number;

            for (let j = 0; j < numMetagenomes; j++) {
                const rawVal = cluster.abundance[j];
                const posVal = convertRawValue(rawVal);
                z[i].push(posVal);
                label[i].push(`${x[j]}, ${y[i]} = ${rawVal}<br>${bodysiteMgLabels[j]}, ${secondary[j]}`);
            }
        });

        let colorScale = "Jet";
        if (hitsOnly) {
            colorScale = [
                [0, this.colorsBinary[0]],
                [1, this.colorsBinary[1]]
            ];
        }

        const traces = [
            {
                x,
                y,
                z,
                type: "heatmap",
                colorscale: colorScale,
                text: label,
                hoverinfo: "text",
                legend: { orientation: "h" },
            },
        ];

        if (useManualExtent) {
            traces[0].zmin = min;
            traces[0].zmax = max;
            traces[0].zauto = false;
        }

        if (logScale) {
            traces[0].colorbar = {
                title: "gene copies per microbial genome",
                titleside: "right",
                tick0: 0,
                tickmode: "array",
                tickvals: [0, 1, 2, 3, 3.90],
                ticktext: ["<1:10K", "1:1K", "1:100", "1:10", "1:1"],
            };
        } else if (hitsOnly) {
            traces[0].showscale = false;
        }

        const shapes = [];
        const labels = [];
        if (regions.length > 0) {
            regions.forEach((region) => {
                const x0 = region[0] - 0.5;
                const x1 = region[1] + 0.5;
                shapes.push({
                    type: 'rect',
                    xref: 'x',
                    yref: 'paper',
                    x0, y0: 0,
                    x1, y1: -0.02,
                    fillcolor: region[3],
                    line: { width: 0 },
                });

                labels.push({
                    x: (x1 - x0) / 2 + x0,
                    y: -0.12,
                    xref: 'x',
                    yref: 'paper',
                    text: region[2].replace(" ", "<br>"),
                    showarrow: false,
                    valign: "top",
                    height: 50,
                    font: { color: 'black' },
                });
            });
        }

        let title = "Abundances";
        if (!this.params.StaticExample) {
            title += ` for Identify ${this.params.Id}, Quantify ${this.params.QuantifyId}`;
        }

        if (this.params.JobName || this.params.SearchType || this.params.RefDb || this.params.CdHitSid || this.params.DiamondSens) {
            title += "<br>";
        }

        let hasNewLine = false;
        let titleFileName = this.params.JobName;

        if (titleFileName) {
            titleFileName = titleFileName.replace(/\.xgmml/i, "")
                .replace(/\.zip/i, "")
                .replace(/_coloredssn/i, "")
                .replace(/_full_ssn/i, "")
                .replace(/_repnode-(1?\.\d\d)_ssn/i, " $1% ")
                .replace(/_/g, " ")
                .replace(/^\d+ (\d+) /, "");
            title += titleFileName;
            hasNewLine = true;
        }

        if (this.params.RefDb && this.params.RefDb !== "UNIREF90") {
            if (hasNewLine) title += "; ";
            hasNewLine = true;
            title += this.params.RefDb;
        }
        if (this.params.SearchType && this.params.SearchType !== "DIAMOND") {
            if (hasNewLine) title += "; ";
            hasNewLine = true;
            title += this.params.SearchType;
        }
        if (this.params.DiamondSens && this.params.DiamondSens !== "normal") {
            if (hasNewLine) title += "; ";
            hasNewLine = true;
            title += this.params.DiamondSens;
        }
        if (this.params.CdHitSid && this.params.CdHitSid !== "85") {
            if (hasNewLine) title += "; ";
            hasNewLine = true;
            title += `CD-HIT ${this.params.CdHitSid}%`;
        }

        if (hasNewLine) title += "; ";
        title += useMean ? "Mean Method" : "Median Method";

        let exportFileName = `abundance_I${this.params.Id}_Q${this.params.QuantifyId}`;
        if (titleFileName) exportFileName += `_${titleFileName.replace(/[^A-Za-z0-9_\-]+/g, "_")}`;

        const layout = {
            title,
            width: this.Width,
            height: this.Height,
            xaxis: { tickangle: -45, showticklabels: false },
            yaxis: { title: "Cluster Number", autorange: "reversed", type: "category" },
            shapes,
            annotations: labels,
        };

        if (hitsOnly) layout.margin = { r: 135 };

        Plotly.newPlot('plot', traces, layout, { toImageButtonOptions: { filename: exportFileName, width: 1425, height: 1050 } });

        if (this.useBoxplots) {
            const plotDiv = document.getElementById('plot');
            plotDiv.on("plotly_click", (data) => {
                if (!data?.points?.length) return;

                const point = data.points[0];
                const mgIdx = point.pointIndex[1];
                const clusterIdx = point.y - 1;
                const clusterId = point.data.y[clusterIdx];
                const mgType = bodysiteMgLabels[mgIdx];

                if (!(clusterIdx in point.data.z)) {
                    console.log(`Not found: ${clusterId}/${clusterIdx} ${point.x} ${mgType}`);
                    return;
                }

                const clusterData = point.data.z[clusterIdx];
                const groupedData = [];
                const groupMap = {};
                let groupCount = 0;

                clusterData.forEach((val, i) => {
                    const site = bodysiteMgLabels[i];
                    if (!(site in groupMap)) {
                        groupMap[site] = groupCount++;
                        groupedData.push({ y: [], type: "box", name: site });
                    }
                    groupedData[groupMap[site]].y.push(val);
                });

                Plotly.newPlot("boxplot-plot", groupedData, { width: 550, height: 550 });

                // Show modal utilizing native HTML5 dialog element
                const boxplotModal = document.getElementById("boxplot");
                if (boxplotModal && typeof boxplotModal.showModal === "function") {
                    boxplotModal.showModal();
                }
            });
        }
    }
}
