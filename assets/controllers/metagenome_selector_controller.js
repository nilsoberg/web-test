import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        "databaseSelect", 
        "container", 
        "availableSelect", 
        "selectedSelect", 
        "availableSearch", 
        "selectedSearch",
        "selectedMetagenomes",
    ];
    static values = { fetchUrl: String }

    // Store master arrays to allow instant client-side filtering via search
    availableItems = [];
    selectedItems = [];

    async connect() {
        const validOptions = Array.from(this.databaseSelectTarget.options).filter(
            opt => opt.value !== ''
        );

        // Initial load
        if (validOptions.length === 1) {
            this.databaseSelectTarget.value = validOptions[0].value;
            await this.loadDatabase({ target: this.databaseSelectTarget });
        }
        // Form reload
        else if (this.databaseSelectTarget.value) {
            await this.loadDatabase({ target: this.databaseSelectTarget });
        }

        this.restoreExistingSelection();
    }

    // Triggered when database select element changes value
    async loadDatabase(event) {
        const dbId = event.target.value;

        if (!dbId) {
            this.containerTarget.classList.add('d-none');
            return;
        }

        // Fetch metagenome array from Symfony backend
        const response = await fetch(`${this.fetchUrlValue}?db=${dbId}`);
        const data = await response.json();

        // Expecting data in format: [{sample_id: 'SRS011263', body_site: 'anterior nares', gender: 'Female'}, ...]
        this.availableItems = data;
        this.selectedItems = [];

        this.containerTarget.classList.remove('d-none');
        this.render();
    }

    // Move single selected item right
    addSelected() {
        this.moveItems(this.availableSelectTarget, this.availableItems, this.selectedItems);
    }

    // Move all currently filtered/visible items right
    addAll() {
        const query = this.availableSearchTarget.value.toLowerCase().trim();

        if (!query) {
            // If there's no search query, move everything
            this.selectedItems.push(...this.availableItems);
            this.availableItems = [];
        } else {
            // Partition availableItems based on the active search filter
            const remainingItems = [];

            this.availableItems.forEach(item => {
                const label = this.getItemLabel(item);

                if (label.includes(query)) {
                    // Item matches filter -> move to selected
                    this.selectedItems.push(item);
                } else {
                    // Item does NOT match filter -> keep in available
                    remainingItems.push(item);
                }
            });

            this.availableItems = remainingItems;
        }

        this.render();
    }

    // Move single selected item left
    removeSelected() {
        this.moveItems(this.selectedSelectTarget, this.selectedItems, this.availableItems);
    }

    // Move all currently filtered/visible items left
    removeAll() {
        const query = this.selectedSearchTarget.value.toLowerCase().trim();

        if (!query) {
            this.availableItems.push(...this.selectedItems);
            this.selectedItems = [];
        } else {
            const remainingItems = [];

            this.selectedItems.forEach(item => {
                const label = this.getItemLabel(item);

                if (label.includes(query)) {
                    this.availableItems.push(item);
                } else {
                    remainingItems.push(item);
                }
            });

            this.selectedItems = remainingItems;
        }

        this.render();
    }

    // Filter available items using the search input
    filterAvailable() {
        this.renderSelect(this.availableSelectTarget, this.availableItems, this.availableSearchTarget.value);
    }

    // Filter target quantification items using search input
    filterSelected() {
        this.renderSelect(this.selectedSelectTarget, this.selectedItems, this.selectedSearchTarget.value);
    }

    // Helper to transfer options between lists
    moveItems(sourceSelect, sourceArray, targetArray) {
        const selectedValues = Array.from(sourceSelect.selectedOptions).map(opt => opt.value);

        for (let i = sourceArray.length - 1; i >= 0; i--) {
            if (selectedValues.includes(String(sourceArray[i].sample_id))) {
                targetArray.push(sourceArray[i]);
                sourceArray.splice(i, 1);
            }
        }
        this.render();
    }

    // Render options and synch hidden inputs for form submission
    render() {
        this.renderSelect(this.availableSelectTarget, this.availableItems, this.availableSearchTarget.value);
        this.renderSelect(this.selectedSelectTarget, this.selectedItems, this.selectedSearchTarget.value);
        this.syncHiddenInputs();
    }

    renderSelect(selectElement, items, filterQuery = '') {
        selectElement.innerHTML = '';
        const query = filterQuery.toLowerCase().trim();

        items.forEach(item => {
            const label = this.getItemLabel(item);
            if (!query || label.toLowerCase().includes(query)) {
                const opt = document.createElement('option');
                opt.value = item.sample_id;
                opt.textContent = label;
                selectElement.appendChild(opt);
            }
        });
    }

    getItemLabel(item) {
        const label = `${item.body_site} (${item.gender}) - ${item.sample_id}`;
        return label;
    }

    // Generates hidden fields so the selected items submit natively with the Symfony Form
    syncHiddenInputs() {
        const ids = this.selectedItems.map(item => item.sample_id);
        this.selectedMetagenomesTarget.value = ids.join(',');
    }

    restoreExistingSelection() {
        const existingData = this.hiddenInputTarget.value;
        if (!existingData || !this.availableItems.length) return;

        try {
            const existingIds = JSON.parse(existingData);
            this.selectedItems = this.availableItems.filter(item => 
                existingIds.includes(String(item.id))
            );
            this.availableItems = this.availableItems.filter(item => 
                !existingIds.includes(String(item.id))
            );
            this.render();
        } catch (e) {
            console.error("Failed to parse existing metagenomes data", e);
        }
    }
}
