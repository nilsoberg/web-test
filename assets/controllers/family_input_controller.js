import { Controller } from '@hotwired/stimulus';

/**
 * Handles fetching protein family size information from an API with debouncing
 * to prevent excessive requests.
 */
export default class extends Controller {
    // Define targets, values, and constants
    static targets = [
        'families',
        'forcedUnirefSizeMessage',
        'fraction',
        'sizeContainer',
        'unirefVersion',
    ];
    static values = {
        apiUrl: String,
        csrfToken: String,
        minChars: { type: Number, default: 3 },
        spamDelay: { type: Number, default: 200 },
        disableForcedUniref: Boolean,
        defaultDatabaseId: { type: Number, default: 0 },
    };

    unirefTabActivated = false;
    tabTriggerId = '';

    initialize() {
        // The timer is to avoid spamming the server with requests
        this.spamTimer = null;
        this.tabTriggerId = this.element.getAttribute('data-family-input-tab-trigger-id');
    }

    /**
     * Main action triggered by user input.
     */
    processChange(event) {
        if (event && !this.element.contains(event.target)) {
            return;
        }

        this.handleProcessChange();
    }

    /**
     * Called when form fields external to the family input element are updated.
     */
    processExternalFieldChange(event) {
        const thisFormId = this.element.getAttribute('data-family-input-form-id');
        if (event.detail.formId !== thisFormId) {
            return;
        }

        this.handleProcessChange();
    }

    processUnirefTabChange(event) {
        if (!this.tabTriggerId || event.detail.tabId !== this.tabTriggerId) {
            this.unirefTabActivated = false;
            return;
        }
        this.unirefTabActivated = true;

        this.handleProcessChange();
    }

    handleProcessChange() {
        const families = this.familiesTarget.value.trim();

        // Clear the previous timer on every new event to avoid duplicate API calls.
        clearTimeout(this.spamTimer);

        // Stop here if the families input is too short.
        if (families.length < this.minCharsValue) {
            this.sizeContainerTarget.textContent = `(Type at least ${this.minCharsValue} characters)`;
            return;
        }
        
        // Assemble the query data directly from the targets
        const query = {
            families: families,
        };

        query.unirefVersion = this.getUnirefVersionValue();
        query.fraction = this.getFractionValue();
        query.dbVersion = this.getDatabaseIdValue();

        // Set a new spam timer. The fetch function will only execute if the
        // delay passes without any new events.
        this.spamTimer = setTimeout(() => {
            this._fetchFamilySize(query);
        }, this.spamDelayValue);
    }

    getUnirefVersionValue() {
        if (this.hasUnirefVersionTarget) {
            return this.unirefVersionTarget.value;
        }

        // Check if the Accession input tab is set to UniRef, if it's not we force UniProt
        let checkUniref = true;
        if (this.tabTriggerId && !this.unirefTabActivated) {
            checkUniref = false;
        }

        const formId = this.element.getAttribute('data-family-input-form-id');
        if (checkUniref) {
            const external = document.querySelector(`.uniref-version-field[data-external-family-field-form-id-value="${formId}"]`);
            return external ? external.value : "uniprot";
        } else {
            return "uniprot";
        }
    }

    getFractionValue() {
        if (this.hasFractionTarget) {
            return this.fractionTarget.value;
        }
        const formId = this.element.getAttribute('data-family-input-form-id');
        const external = document.querySelector(`.fraction-field[data-external-family-field-form-id-value="${formId}"]`);
        return external ? external.value : 1;
    }

    getDatabaseIdValue() {
        const formId = this.element.getAttribute('data-family-input-form-id');
        const external = document.querySelector(`.db-version-field[data-external-family-field-form-id-value="${formId}"]`);
        if (external) {
            return external.value;
        } else {
            if (this.hasDefaultDatabaseIdValue) {
                return this.defaultDatabaseIdValue;
            } else {
                return;
            }
        }
    }


    /**
     * Fetches the data from the API.
     * @param {object} query The search data to send to the API.
     * @private
     */
    async _fetchFamilySize(query) {
        const formData = this._getFormData(query);

        try {
            const response = await fetch(this.apiUrlValue, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                this.sizeContainerTarget.textContent = 'Error calculating size.';
                console.error('API Error:', response.status, await response.text());
                return;
            }

            const data = await response.json();
            this._updateSizeDisplay(data);

        } catch (error) {
            this.sizeContainerTarget.textContent = 'Network error during calculation.';
            console.error('Fetch Error:', error);
        }
    }
    
    /**
     * Updates the UI based on the API response.
     * @param {object} data The JSON data from the API.
     * @private
     */
    _updateSizeDisplay(data) {
        if (data.totalFamilyCount > 0) {
            this._setForcedUnirefVersion(data);
            this._createFamilyTable(data);
        } else {
            this.sizeContainerTarget.textContent = 'No families found or families are empty.';
        }
    }

    _setForcedUnirefVersion(response) {
        if (response.forcedUnirefVersion) {
            this.forcedUnirefSizeMessageTarget.textContent = response.forcedUnirefVersionText + ' will be used because the total family size is too large.';
            this.forcedUnirefSizeMessageTarget.classList.remove('d-none');
            if (this.hasUnirefVersionTarget) {
                this.unirefVersionTarget.value = response.forcedUnirefVersion;
            }
        } else {
            this.forcedUnirefSizeMessageTarget.classList.add('d-none');
        }
    }

    /**
     * Builds and displays the results table.
     * @param {object} response The JSON data from the API.
     * @private
     */
    _createFamilyTable(response) {
        // Clear previous results
        this.sizeContainerTarget.innerHTML = '';

        const table = document.createElement('table');
        table.classList.add('family-data');
    
        const thead = table.createTHead();
        const headerRow = thead.insertRow();
        const headers = ['Family', 'Family Name', 'Full Size', 'UniRef90 Size', 'UniRef50 Size'];
        headers.forEach(headerText => {
            const th = document.createElement('th');
            th.textContent = headerText;
            headerRow.appendChild(th);
        });
    
        const tbody = table.createTBody();
        response.familyData.forEach(family => {
            const row = tbody.insertRow();
            row.insertCell().textContent = family.id;
            row.insertCell().textContent = family.name;
            row.insertCell().textContent = family.count.uniprot;
            row.insertCell().textContent = family.count.uniref90;
            row.insertCell().textContent = family.count.uniref50;
        });
    
        const tfoot = table.createTFoot();
        
        // Total sequence row
        const totalSequenceRow = tfoot.insertRow();
        totalSequenceRow.classList.add('total-row');
        totalSequenceRow.insertCell();
        totalSequenceRow.insertCell().textContent = 'Total:';
        totalSequenceRow.insertCell().textContent = response.totalSequenceCount.uniprot;
        totalSequenceRow.insertCell().textContent = response.totalSequenceCount.uniref90;
        totalSequenceRow.insertCell().textContent = response.totalSequenceCount.uniref50;

        // Total computed row
        const totalComputedRow = tfoot.insertRow();
        totalComputedRow.classList.add('total-row');
        totalComputedRow.insertCell();
        totalComputedRow.insertCell().textContent = 'Total Computed:';
        totalComputedRow.insertCell().textContent = response.totalComputed;
        totalComputedRow.insertCell(); // Empty cells for alignment
        totalComputedRow.insertCell();

        this.sizeContainerTarget.appendChild(table);
    }

    /**
     * Creates a FormData object for the API request.
     * @param {object} query The data to include in the form.
     * @returns {FormData}
     * @private
     */
    _getFormData(query) {
        const formData = new FormData();
        formData.append('families', query.families);
        if (typeof query.unirefVersion !== "undefined") {
            formData.append('unirefVersion', query.unirefVersion);
        }
        if (typeof query.fraction !== "undefined") {
            formData.append('fraction', query.fraction);
        }
        if (typeof query.dbVersion !== "undefined") {
            formData.append('dbVersion', query.dbVersion);
        }
        if (this.hasCsrfTokenValue) {
            formData.append('_token', this.csrfTokenValue);
        }
        if (this.hasDisableForcedUnirefValue && this.disableForcedUnirefValue) {
            formData.append('fscheck', 0);
        }
        return formData;
    }
}
