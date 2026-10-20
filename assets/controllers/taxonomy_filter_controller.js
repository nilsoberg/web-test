import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['collection', 'predefinedSelect', 'operatorSelect'];
    static values = {
        prototype: String,
        index: Number,
        autocompleteUrl: String,
        predefinedData: Object // Parsed JSON of predefined sets
    };

    connect() {
        // Set the initial index so we don't overwrite existing rows
        this.indexValue = this.collectionTarget.children.length;

        // If the form was submitted, but kicked back here with populated values after validation
        // failed, we need to scramble the IDs to avoid the Firefox caching issue
        this.collectionTarget.querySelectorAll('.taxonomy-row').forEach(row => {
            this.scrambleInputName(row);
        });

        // If the form loaded with a predefined preset but NO saved rows (because we 
        // stopped saving them to the DB), trigger the logic to render them immediately.
        if (this.indexValue === 0 && this.hasPredefinedSelectTarget && this.predefinedSelectTarget.value !== '') {
            this.applyPredefined({ target: this.predefinedSelectTarget });
        }

        this.element.setAttribute('autocomplete', 'off');
        // Clear the value momentarily on focus to avoid history
        this.element.addEventListener('focus', () => {
            this.element.setAttribute('autocomplete', 'do-not-autofill');
        });

        const form = this.element.closest('form');
        if (form) {
            form.addEventListener('submit', () => this.restoreRealNames());
        }
    }

    clearPredefined() {
        if (this.hasPredefinedSelectTarget && this.predefinedSelectTarget.value !== '') {
            this.predefinedSelectTarget.value = '';
        }
    }

    add(event) {
        if (event) {
            event.preventDefault();
            this.clearPredefined();
        }

        // Replace '__name__' in the prototype with the current index
        const newFormHtml = this.prototypeValue.replace(/__name__/g, this.indexValue);
        this.collectionTarget.insertAdjacentHTML('beforeend', newFormHtml);
        this.indexValue++;

        const newRow = this.collectionTarget.lastElementChild;

        this.scrambleInputName(newRow);

        return newRow;
    }

    // Scramble names to bypass Firefox cache
    scrambleInputName(row) {
        if (!row) return;

        const valueInput = row.querySelector('.taxonomy-value-input');
        if (valueInput && valueInput.hasAttribute('name')) {
            const realName = valueInput.getAttribute('name');
            // Store the real name if we haven't already
            if (!valueInput.hasAttribute('data-real-name')) {
                valueInput.setAttribute('data-real-name', realName);
            }
            // Overwrite name with an unmapped string to stop Firefox autocomplete
            valueInput.setAttribute('name', `fx_bypass_${Math.random().toString(36).substr(2, 9)}`);
        }
    }

    // Restore names before Symfony processes request
    restoreRealNames() {
        this.element.querySelectorAll('.taxonomy-value-input[data-real-name]').forEach(input => {
            input.setAttribute('name', input.getAttribute('data-real-name'));
        });
    }

    remove(event) {
        event.preventDefault();
        event.target.closest('.taxonomy-row').remove();
        this.clearPredefined();
    }

    applyPredefined(event) {
        const selectedKey = event.target.value;
        const filterSet = this.predefinedDataValue[selectedKey];

        this.collectionTarget.innerHTML = '';
        this.indexValue = 0;

        if (!filterSet) {
            return;
        }

        // Set the global operator (AND/OR)
        if (this.hasOperatorSelectTarget) {
            this.operatorSelectTarget.value = filterSet.logic || 'AND';
        }

        if (filterSet.rules) {
            // Generate the rows from the nested rules array
            filterSet.rules.forEach(rule => {
                const newRow = this.add();
                const rankSelect = newRow.querySelector('.taxonomy-rank-select');
                const operatorSelect = newRow.querySelector('.taxonomy-operator-select');
                const valueInput = newRow.querySelector('.taxonomy-value-input');

                // Programmatic assignments don't trigger standard JS 'change' events,
                // so this is completely safe and won't trigger clearPredefined().
                if (rankSelect && valueInput) {
                    rankSelect.value = rule.field;
                    valueInput.value = rule.value;
                    if (operatorSelect) {
                        operatorSelect.value = rule.operator || '='; 
                    }
                }
            });
        }
    }

    async autocomplete(event) {
        const input = event.target;
        const query = input.value;

        if (input.getAttribute('autocomplete') !== 'do-not-autofill') {
            input.setAttribute('autocomplete', 'do-not-autofill');
        }

        // Wait until at least 3 characters are typed
        let datalistId = input.getAttribute('list');
        if (query.length < 3) {
            if (datalistId) {
                const datalist = document.getElementById(datalistId);
                if (datalist) {
                    datalist.innerHTML = '';
                }
            }
            return;
        }

        // Find the sibling select box to know WHICH taxonomic rank we are searching in
        const row = input.closest('.taxonomy-row');
        const rank = row.querySelector('.taxonomy-rank-select').value;

        try {
            // Call your Symfony database endpoint
            const response = await fetch(`${this.autocompleteUrlValue}?q=${encodeURIComponent(query)}&rank=${encodeURIComponent(rank)}`);
            const data = await response.json(); // Expected format: [{value: "Homo"}, {value: "Hominidae"}]

            // Generate or update the native HTML5 datalist for this specific input
            let datalistId = input.getAttribute('list');
            let datalist;

            if (!datalistId) {
                datalistId = 'list-' + Math.random().toString(36).substr(2, 9);
                input.setAttribute('list', datalistId);
                datalist = document.createElement('datalist');
                datalist.id = datalistId;
                document.body.appendChild(datalist);
            } else {
                datalist = document.getElementById(datalistId);
            }

            // Wipe to prevent mixing
            datalist.innerHTML = '';

            // Populate datalist options
            datalist.innerHTML = data.map(item => `<option value="${item.value}">`).join('');
        } catch (error) {
            console.error('Taxonomy Autocomplete failed:', error);
        }
    }
}
