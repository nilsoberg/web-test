document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-prefix]').forEach(container => {
        const prefix = container.getAttribute('data-prefix');
        const preselectDropdown = document.getElementById(`${prefix}-taxonomy-select`);
        const addButton = document.getElementById(`${prefix}-taxonomy-add-btn`);

    // Define the autopopulate options based on the input

    const presetOptions = {
        bacteria_fungi: [
            { rank: 'Superkingdom', value: 'Bacteria' },
            { rank: 'Superkingdom', value: 'Archaea' },
            { rank: 'Phylum', value: 'Ascomycota' },
            { rank: 'Phylum', value: 'Basidiomycota' },
            { rank: 'Phylum', value: 'Fungi incertae sedis' },
            { rank: 'Phylum', value: 'unclassified fungi' },
        ],
        eukaroyta_no_fungi: [
            { rank: 'Superkingdom', value: 'Eukaryota' },
            { rank: 'Phylum', value: 'NOT Ascomycota' },
            { rank: 'Phylum', value: 'NOT Basidiomycota' },
            { rank: 'Phylum', value: 'NOT Fungi incertae sedis' },
            { rank: 'Phylum', value: 'NOT unclassified fungi' },
            { rank: 'Species', value: 'NOT metagenome' },
        ],
        fungi: [
            { rank: 'Phylum', value: 'Ascomycota' },
            { rank: 'Phylum', value: 'Basidiomycota' },
            { rank: 'Phylum', value: 'Fungi incertae sedis' },
            { rank: 'Phylum', value: 'unclassified fungi' },
        ],
        viruses: [
            { rank: 'Superkingdom', value: 'Viruses' },
        ],
        bacteria: [
            { rank: 'Superkingdom', value: 'Bacteria' },
        ],
        eukaryota: [
            { rank: 'Superkingdom', value: 'Eukaryota' },
        ],
        archaea: [
            { rank: 'Superkingdom', value: 'Archaea' },
        ],
    };

    // List the Taxonomy Ranks for Dropdown Menu

    const taxonomyRanks = [
        'Superkingdom',
        'Kingdom',
        'Phylum',
        'Class',
        'Order',
        'Family',
        'Genus',
        'Species',
    ];

    // Reset the autopopulate variable for status control

    let isAutoPopulated = false;

    // Function to set a manual taxonomy filter item

    const createTaxonomyItem = (rank = '', value = '') => {
        const div = document.createElement('div');
        div.className = 'taxonomy-item';
        div.innerHTML = `
            <select class="taxonomy-dropdown">
                ${taxonomyRanks.map(rankOption => `
                    <option value="${rankOption}" ${rankOption === rank ? 'selected' : ''}>
                        ${rankOption}
                    </option>
                `).join('')}
            </select>
            <input type="text" class="taxonomy-input" value="${value}" placeholder="" />
            <button type="button" class="taxonomy-trash-btn trash-btn" title="Remove filter">
                <i class="fas fa-trash"></i>
            </button>
        `;

        // Adds an event listener to remove item whenever we click on its trash bin

        const deleteButton = div.querySelector('.taxonomy-trash-btn');
        deleteButton.addEventListener('click', () => {
            container.removeChild(div);
            checkIfResetNeeded();
        });

        return div;
    };

    // Function to check if Reset button is needed. If yes add it.

    const checkIfResetNeeded = () => {
        if (isAutoPopulated && container.children.length > 0) {
            addButton.textContent = 'Reset';
            addButton.classList.add('reset-button');
        } else {
            addButton.textContent = 'Add Taxonomy Filter';
            addButton.classList.remove('reset-button');
        }
    };

    // Autopopulate based on preselec value

    preselectDropdown.addEventListener('change', (e) => {
        const selectedValue = e.target.value;
        container.innerHTML = '';

        if (presetOptions[selectedValue]) {
            presetOptions[selectedValue].forEach(({ rank, value }) => {
                const item = createTaxonomyItem(rank, value);
                container.appendChild(item);
            });
            isAutoPopulated = true;
        } else {
            isAutoPopulated = false;
        }
        checkIfResetNeeded();
    });

    // Add or Reset functionality to the button

    addButton.addEventListener('click', () => {
        if (addButton.classList.contains('reset-button')) {
            // Reset functionality
            container.innerHTML = '';
            preselectDropdown.value = '';
            isAutoPopulated = false;
            checkIfResetNeeded();
        } else {
            // Add a new blank taxonomy filter item
            const item = createTaxonomyItem();
            container.appendChild(item);
            checkIfResetNeeded();
        }
    });
});

});