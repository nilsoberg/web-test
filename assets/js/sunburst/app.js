

export class AppSunburst {
    constructor(apiParams) {
        this.apiParams = apiParams;
        this.colors = new SunburstColor();
        this.abortSignal = apiParams.signal || null;
        this.pngSize = apiParams.exportPngSize || 2000;
    }

    /**
     * Appends the basic HTML structure for the sunburst chart to a given container.
     * @param {string} mainIdSelector - CSS selector for the container element.
     */
    attachToContainer(mainIdSelector) {
        const mainElement = document.getElementById(mainIdSelector);
        if (!mainElement) {
            console.error(`Container element "{mainIdSelector}" not found.`);
        }
    }

    /**
     * Asynchronously fetches sunburst data from the backend and then renders the feature.
     */
    async addSunburstFeatureAsync(onSunburstLoaded) {

        try {
            const response = await fetch(this.apiParams.scriptAppUrl, { signal: this.abortSignal });

            if (!response.ok) {
                let errorMsg = `Request error; Status: ${response.status}`;
                try {
                    // Attempt to parse a JSON error response from the server
                    const errorData = await response.json();
                    errorMsg = errorData.message || errorMsg;
                } catch (e) {
                    // If response is not JSON or another error occurs, stick with the HTTP status
                }
                throw new Error(errorMsg);
            }

            const jsonData = await response.json();

            // Check for application-specific error flags
            if (jsonData.valid !== undefined && jsonData.valid.toString() === 'false') {
                alert(jsonData.message); // Maintain original alert for this specific case
                throw new Error(jsonData.message || 'Server validation failed.');
            }

            // Actually initialize and create the sunburst diagram here
            if (jsonData.data && jsonData.data.data) {
                this.addSunburstFeature(jsonData.data.data);
                this.enableDownloadLinks();
                //TODO: implement transfer option
                //onSunburstLoaded();
            } else {
                throw new Error('Received data is not in the expected format.');
            }
        } catch (error) {
            console.error('Error fetching or processing sunburst data from url ' + this.apiParams.scriptAppUrl + ':', error);
            throw error; // Re-throw to be caught by the caller
        }
    }

    /**
     * Renders the sunburst feature using the provided tree data and sets up event listeners.
     * @param {object} treeData - The data structure for the sunburst chart.
     */
    addSunburstFeature(treeData) {
        const that = this;

        let idTypeStr = this.apiParams.sunburstUnirefVersion ? "UniRef"+this.apiParams.sunburstUnirefVersion : this.appPrimaryIdTypeText;
        const colorsFn = (d, g) => { return this.colors.getColorForNode(d, g ? g.depth : 0); };

        this.addParentRef(treeData, null);

        this.maxDepth = 9;
        this.depthMap = {
            0 : [ "Root",   "Superkingdom", "Kingdom",  "Phylum",   "Class",    "Order",    "Family",   "Genus",    "Species" ],
            1 : [           "Superkingdom", "Kingdom",  "Phylum",   "Class",    "Order",    "Family",   "Genus",    "Species" ], 
            2 : [                           "Kingdom",  "Phylum",   "Class",    "Order",    "Family",   "Genus",    "Species" ], 
            3 : [                                       "Phylum",   "Class",    "Order",    "Family",   "Genus",    "Species" ], 
            4 : [                                                   "Class",    "Order",    "Family",   "Genus",    "Species" ], 
            5 : [                                                               "Order",    "Family",   "Genus",    "Species" ], 
            6 : [                                                                           "Family",   "Genus",    "Species" ], 
            7 : [                                                                                       "Genus",    "Species" ], 
            8 : [                                                                                                   "Species" ], 
        };

        this.sbCurrentData = treeData;
        this.addCurrentViewNumberOfSequences();
        this.showTaxLevels(treeData);

        this.sb = Sunburst()
            .width(600)
            .height(600)
            .data(treeData)
            .label("node")
            .size("ns") // ns = numSpecies
            .color(colorsFn)
            .excludeRoot(true)
            (document.getElementById("sunburst-chart"));
        this.sb.onClick(function(data) {
            if (!data)
                return;
            that.sbCurrentData = data;
            that.addCurrentViewNumberOfSequences();
            that.sb.focusOnNode(data);
            that.showTaxLevels(data);
        });
    }

    addCurrentViewNumberOfSequences() {
        if (!this.sbCurrentData)
            return;

        let numUniprot = 0;
        let idStr = this.sbCurrentData.nq > 1 ? "IDs" : "ID";
        let numUniRef90;
        let numUniRef50;
        let theText;

        if (this.apiParams.sunburstUnirefVersion) {
            const numIds = this.computeVisibleIds(this.sbCurrentData);
            numUniprot = this.commify(numIds.uniprot);
            numUniRef90 = this.commify(numIds.uniref90);
            numUniRef50 = this.commify(numIds.uniref50);
            theText = numUniprot + " UniProt, " + numUniRef90 + " UniRef90, " + numUniRef50 + " UniRef50 IDs";
        } else {
            numUniprot = this.commify(this.sbCurrentData.nq); // nq = numSequences
            theText = numUniprot + " " + idTypeStr + " " + idStr + "";
        }

        const numberContainer = document.getElementById("sunburst-id-nums");
        if (numberContainer) {
	        numberContainer.textContent = theText;
        }

        let rankText = "";
        if (this.sbCurrentData.d > 0)
            rankText = this.depthMap[this.sbCurrentData.d][0] + ": " + this.sbCurrentData.node;

        const rankContainer = document.getElementById("sunburst-rank");
        if (rankContainer) {
            rankContainer.textContent = rankText;
        }
    }

    showTaxLevels(data) {
        const levelsContainer = document.getElementById("sunburst-chart-levels");
        if (!levelsContainer) return;

        let htmlContent = '';
        for (let i = data.d + 1; i < this.maxDepth; i++) {
            let theColor = "white";
            if (data.d > 0) {
                theColor = this.colors.getColorForNode(data, i - 1);
            }

            const theLevel = this.depthMap[i][0];
            htmlContent += `<div style="background-color: ${theColor}" class="sunburst-level">${theLevel}</div>`;
        }

        // Set the innerHTML only once after the loop is complete
        levelsContainer.innerHTML = htmlContent;
    }

    addParentRef(treeData, theParent) {
        treeData.theParent = theParent;
        if (typeof treeData.children !== "undefined") {
            for (let i = 0; i < treeData.children.length; i++) {
                this.addParentRef(treeData.children[i], treeData);
            }
        }
    }

    computeVisibleIds(data) {
        const rawIds = this.getIdsFromTree(data);
        const ids = {uniprot: 0, uniref90: 0, uniref50: 0};
        ids.uniref50 = Array.from(new Set(rawIds.uniref50)).length;
        ids.uniref90 = Array.from(new Set(rawIds.uniref90)).length;
        ids.uniprot = Array.from(new Set(rawIds.uniprot)).length;
        return ids;
    }

    getIdsFromTree(data, idType = "") {
        const nextLevel = function(level) {
            const ids = {uniprot: [], uniref90: [], uniref50: []};
            // Bottom level
            // When we fix the legacy problem, only look for level.seq, not level.s
            if (typeof level.s !== "undefined")
                level.seq = level.s;
            if (typeof level.seq !== "undefined") {
                for (let i = 0; i < level.seq.length; i++) {
                    ids.uniref50.push(level.seq[i].sa50);
                    ids.uniref90.push(level.seq[i].sa90);
                    ids.uniprot.push(level.seq[i].sa); //sa = seqAcc
                }
            } else {
                for (let i = 0; i < level.children.length; i++) {
                    const nextIds = nextLevel(level.children[i]);
                    for (let j = 0; j < nextIds.uniprot.length; j++) {
                        ids.uniprot.push(nextIds.uniprot[j]);
                        ids.uniref50.push(nextIds.uniref50[j]);
                        ids.uniref90.push(nextIds.uniref90[j]);
                    }
                }
            }
            return ids;
        };

        let ids = nextLevel(data);
        if (idType) {
            if (idType === "uniref50")
                ids = ids.uniref50;
            else if (idType === "uniref90")
                ids = ids.uniref90;
            else
                ids = ids.uniprot;
            ids = Array.from(new Set(ids)); // Make unique
        }
        return ids;
    }




    ////////////////////////////////////////////////////////////////////////////////////////////////
    // Display utility functions
    //
    commify(num) {
        return parseInt(num).toLocaleString();
    }




    ////////////////////////////////////////////////////////////////////////////////////////////////
    // Download functions
    //
    //
    //

    // Get filename from Content-Disposition header if provided by the server
    getResponseFilename(response) {
        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = 'sequences.fasta'; // Default filename
        if (contentDisposition) {
            // Simple regex to extract filename; can be made more robust
            // Handles filename="name.ext" and filename*=UTF-8''name.ext
            const filenameMatch = contentDisposition.match(/filename\*?=['"]?(?:UTF-\d['"]*)?([^;\r\n"']+)['"]?/i);
            if (filenameMatch && filenameMatch[1]) {
                filename = decodeURIComponent(filenameMatch[1]); // Ensure URI-encoded chars are decoded
            }
        }
        return filename;
    }

    // Attempt to parse a JSON error response from the server
    async getResponseErrorMessage(response) {
        let errorData;
        let errorMessage = `Server error: ${response.status} ${response.statusText}`;
        try {
            errorData = await response.json();
            if (errorData && errorData.message) {
                errorMessage = errorData.message;
            }
        } catch (e) {
            // Response was not JSON, or other parsing error; stick with the HTTP status
        }
        return errorMessage;
    }

	enableDownloadLinks() {
        const pngDownloadButton = document.getElementById("sunburst-download-png");
        const svgDownloadButton = document.getElementById("sunburst-download-svg");
        const idDownloadButton = document.getElementById("sunburst-download-ids");
        const fastaDownloadButton = document.getElementById("sunburst-download-fasta");
        const transferEstButton = document.getElementById("sunburst-transfer-est");
        const transferGndButton = document.getElementById("sunburst-transfer-gnd");

        if (pngDownloadButton) {
            const fname = this.makeFilename() + "_sunburst.png";
            pngDownloadButton.onclick = () => this.downloadPNG(
                (w, h) => this.createPNG(w, h), 
                fname,
            );
        }

        if (svgDownloadButton) {
            const fname = this.makeFilename() + "_sunburst.svg";
            svgDownloadButton.onclick = () => this.downloadSVG(
                () => this.createSVG(), 
                fname,
            );
        }

        if (idDownloadButton) {
            idDownloadButton.onclick = () => this.downloadIdList();
        }

        if (fastaDownloadButton) {
            fastaDownloadButton.onclick = () => this.downloadFasta();
        }

        if (transferEstButton) {
            transferEstButton.onclick = () => this.transferTo(this.apiParams.estTransferUrl);
        }

        if (transferGndButton) {
            transferGndButton.onclick = () => this.transferTo(this.apiParams.gntTransferUrl);
        }
    }

    transferTo(baseUrl) {
        if (!this.sbCurrentData) return;

        const idType = this._getCheckedIdType();
        const idList = this.getIdsFromTree(this.sbCurrentData, idType)?.join(',');

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = baseUrl;
        form.style.display = 'none';

        const payload = {
            'parent_id': this.apiParams.apiId,
            'parent_key': this.apiParams.apiKey,
            'parent_subtype': this.sbCurrentData.id,
            'parent_subtype_name': this.sbCurrentData.node,
            'parent_seq_type': idType,
            'idList': idList,
        };

        for (const [key, value] of Object.entries(payload)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }

    /**
     * Downloads a list of IDs as a .txt file based on the current view
     */
    downloadIdList() {
        if (!this.sbCurrentData) return;

        const ids = this.getIdsFromTree(this.sbCurrentData, this._getCheckedIdType());

        const fname = this.makeFilename() + ".txt";

        // Create the file content
        const textContent = ids.join("\n");
        const blob = new Blob([textContent], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);

        this._triggerDownload(url, fname);

        window.URL.revokeObjectURL(url);
    }

    makeFilename() {
        const idType = this._getCheckedIdType();

        const nodeName = this.sbCurrentData.node.replace(/[^a-z0-9]/gi, '_');
        let fname = this.apiParams.baseFilename ? this.apiParams.baseFilename : `${this.apiParams.apiId}_`;
        if (fname.endsWith('.')) {
            fname = fname.substring(0, fname.length - 1) + '_';
        }

        if (Array.isArray(this.apiParams.apiExtra)) {
            this.apiParams.apiExtra.forEach(extra => {
                if (extra.name) fname += `${extra.name}_`;
            });
        }

        fname += nodeName;

        if (idType === 'uniref50') {
            fname += '_UniRef50';
        } else if (idType === 'uniref90') {
            fname += '_UniRef90';
        } else {
            fname += '_UniProt';
        }

        return fname;
    }

    /**
     * Helper to get the value of the selected ID type radio button
     */
    _getCheckedIdType() {
        const selected = document.querySelector('input[name="sunburstIdType"]:checked');
        return selected ? selected.value : 'uniprot';
    }

    /**
     * Handles the FASTA file download with user confirmation and progress UI
     */
    async downloadFasta() {
        const idType = this._getCheckedIdType();

        let message = 'The FASTA download may take a long time depending on the number of sequences.';
        if (this.apiParams.primaryIdType === 'UniRef50' || this.apiParams.primaryIdType === 'UniRef90') {
            const displayIdType = idType.replace('uniprot', 'UniProt').replace('uniref', 'UniRef');
            if (displayIdType !== this.apiParams.primaryIdType) {
                message += ` Additionally, ${displayIdType} was selected. Only ${this.apiParams.primaryIdType} sequences will be downloaded.`;
            }
        }

        //message += " Do you wish to continue?";
        const confirmResult = await Swal.fire({
            title: "Do you wish to continue?",
            text: message,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "gray",
            confirmButtonText: "Download"
        });

        if (!confirmResult.isConfirmed) {
            return; // Exit if the user cancels
        }

        const nodeName = this.sbCurrentData.node;
        const jsIds = this.getIdsFromTree(this.sbCurrentData, idType);

        const payload = {
            id: this.apiParams.apiId,
            key: this.apiParams.apiKey,
            o: nodeName,
            ids: jsIds,
            it: idType
        };

        this.startProgress();

        try {
            const response = await fetch(this.apiParams.fastaAppUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/octet-stream, text/fasta, */*'
                },
                body: JSON.stringify(payload),
                signal: this.abortSignal
            });

            if (!response.ok) {
                const errorMessage = await this.getResponseErrorMessage(response);
                throw new Error(errorMessage);
            }

            const filename = this.getResponseFilename(response);
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);

            this._triggerDownload(url, filename);

        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('FASTA Download aborted.');
            } else {
                console.error('FASTA Download Error:', error);
                alert(`Failed to download FASTA file: ${error.message}`);
            }
        } finally {
            this.stopProgress();
        }
    }

    /**
     * Creates and shows the Bootstrap spinner overlay
     */
    startProgress() {
        if (document.getElementById('sunburst-loading-overlay')) return;

        const overlay = document.createElement('div');
        overlay.id = 'sunburst-loading-overlay';
        overlay.className = 'download-overlay';

        // Bootstrap spinner HTML
        overlay.innerHTML = `
            <div class="text-center text-white">
                <div class="spinner-border" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Preparing your download, please wait...</p>
            </div>
        `;

        document.body.appendChild(overlay);
    }

    /**
     * Removes the loading overlay
     */
    stopProgress() {
        const overlay = document.getElementById('sunburst-loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    }

    /**
     * Triggers a browser download for PNG
     */
    async downloadPNG(renderPromise, filename) {
        try {
            const dataURL = await renderPromise(this.pngSize, this.pngSize);
            this._triggerDownload(dataURL, filename);
        } catch (error) {
            console.error("PNG Download failed:", error);
        }
    }

    /**
     * Triggers a browser download for SVG
     */
    downloadSVG(getData, filename) {
        const dataURL = getData();
        this._triggerDownload(dataURL, filename);
    }

    /**
     * Generic helper to create an invisible link and click it
     */
    _triggerDownload(href, filename) {
        const downloadLink = document.createElement('a');
        downloadLink.href = href;
        downloadLink.download = filename;
        document.body.appendChild(downloadLink); // Better compatibility
        downloadLink.click();
        downloadLink.remove();
    }

    /**
     * Adjusts metadata & styling on a copy of the SVG
     */
    fixSVG(width, height) {
        const svgDoc = document.querySelector("#sunburst-chart svg");
        if (!svgDoc) return null;

        const svg = svgDoc.cloneNode(true);
        svg.removeAttribute("style");
        svg.setAttribute("width", `${width}px`);
        svg.setAttribute("height", `${height}px`);
        svg.setAttribute("xmlns:xlink", "http://www.w3.org/1999/xlink");

        const svgStyle = document.createElement("style");
        svgStyle.textContent = `
            text { font-family: sans-serif; font-size: 12px;dominant-baseline: middle; text-anchor: middle; pointer-events: none; fill: #222; }
            .text-contour { fill: none; stroke: white; stroke-width: 5; stroke-linejoin: round; }
            .main-arc { stroke-width: 1px; }
            .hidden-arc { fill: none; }
        `;
        svg.prepend(svgStyle);
        return svg;
    }

    /**
     * Convert SVG element into XML string
     */
    serializeSVG(width, height) {
        const svg = this.fixSVG(width, height);
        if (!svg) return "";
        return new XMLSerializer().serializeToString(svg);
    }

    /**
     * Convert serialized SVG element from XML to data URL
     */
    createSVG(width = 600, height = 600) {
        const svgData = this.serializeSVG(width, height);
        const dataHeader = 'data:image/svg+xml;base64,';
        return dataHeader + btoa(unescape(encodeURIComponent(svgData)));
    }

    /**
     * Render a PNG using a hidden HTML canvas element
     */
    createPNG(width, height) {
        const imgBase64 = this.createSVG(width, height);
        const tempImg = new Image();

        return new Promise((resolve, reject) => {
            tempImg.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');

                ctx.drawImage(tempImg, 0, 0);
                const dataURL = canvas.toDataURL("image/png", 1.0);

                // Cleanup
                tempImg.remove();
                canvas.remove();
                resolve(dataURL);
            };

            tempImg.onerror = (err) => reject(err);
            tempImg.src = imgBase64;
        });
    }
}
