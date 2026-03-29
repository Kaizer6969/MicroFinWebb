/**
 * Philippine Address Selector
 * Uses PSGC API to populate Province, City/Municipality, and Barangay dropdowns.
 * 
 * Usage:
 * Ensure your select elements have the following IDs:
 * - province: [id_prefix]province
 * - city: [id_prefix]city
 * - barangay: [id_prefix]barangay
 * 
 * Then initialize: new AddressSelector('present_');
 */

class AddressSelector {
    constructor(prefix = '') {
        this.prefix = prefix;
        this.provinceSelect = document.getElementById(prefix + 'province');
        this.citySelect = document.getElementById(prefix + 'city');
        this.barangaySelect = document.getElementById(prefix + 'barangay');
        
        // Base API URL
        this.apiBase = 'https://psgc.gitlab.io/api';
        
        // Initializer
        if (this.provinceSelect && this.citySelect && this.barangaySelect) {
            this.init();
        } else {
            console.warn('AddressSelector: Required select elements not found for prefix:', prefix);
        }
    }

    async init() {
        this.disableDetails();
        await this.loadProvinces();
        
        this.provinceSelect.addEventListener('change', () => this.handleProvinceChange());
        this.citySelect.addEventListener('change', () => this.handleCityChange());
    }

    disableDetails() {
        this.citySelect.disabled = true;
        this.barangaySelect.disabled = true;
        this.citySelect.innerHTML = '<option value="">Select Province First</option>';
        this.barangaySelect.innerHTML = '<option value="">Select City First</option>';
    }

    async loadProvinces() {
        try {
            this.provinceSelect.innerHTML = '<option value="">Loading...</option>';
            const response = await fetch(`${this.apiBase}/provinces/`);
            const data = await response.json();
            
            // Sort alphabetically
            data.sort((a, b) => a.name.localeCompare(b.name));
            
            this.provinceSelect.innerHTML = '<option value="">Select Province</option>';
            data.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.name; // Using Name as value to be compatible with DB
                opt.dataset.code = p.code;
                opt.textContent = p.name;
                this.provinceSelect.appendChild(opt);
            });
            
            // Handle pre-selection if value exists (for edit mode)
            const currentVal = this.provinceSelect.dataset.current;
            if (currentVal) {
                this.provinceSelect.value = currentVal;
                // Trigger change to load cities if province is selected
                if(this.provinceSelect.value === currentVal) {
                   this.handleProvinceChange(true);
                }
            }
            
        } catch (error) {
            console.error('Error loading provinces:', error);
            this.provinceSelect.innerHTML = '<option value="">Error loading data</option>';
        }
    }

    async handleProvinceChange(isPreload = false) {
        const selectedOption = this.provinceSelect.options[this.provinceSelect.selectedIndex];
        const code = selectedOption.dataset.code;
        
        this.citySelect.innerHTML = '<option value="">Loading...</option>';
        this.barangaySelect.innerHTML = '<option value="">Select City First</option>';
        this.barangaySelect.disabled = true;
        this.citySelect.disabled = true;
        
        if (!code) return; // Reset
        
        try {
            const response = await fetch(`${this.apiBase}/provinces/${code}/cities-municipalities/`);
            const data = await response.json();
            
            data.sort((a, b) => a.name.localeCompare(b.name));
            
            this.citySelect.innerHTML = '<option value="">Select City/Municipality</option>';
            data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.name;
                opt.dataset.code = c.code;
                opt.textContent = c.name;
                this.citySelect.appendChild(opt);
            });
            this.citySelect.disabled = false;
            
            // Pre-select city
            const currentVal = this.citySelect.dataset.current;
            if (isPreload && currentVal) {
                this.citySelect.value = currentVal;
                if(this.citySelect.value === currentVal) {
                    this.handleCityChange(true);
                }
            }
            
        } catch (error) {
            console.error('Error loading cities:', error);
            this.citySelect.innerHTML = '<option value="">Error loading data</option>';
        }
    }

    async handleCityChange(isPreload = false) {
        const selectedOption = this.citySelect.options[this.citySelect.selectedIndex];
        const code = selectedOption.dataset.code;
        
        this.barangaySelect.innerHTML = '<option value="">Loading...</option>';
        this.barangaySelect.disabled = true;
        
        if (!code) return;
        
        try {
            const response = await fetch(`${this.apiBase}/cities-municipalities/${code}/barangays/`);
            const data = await response.json();
            
            data.sort((a, b) => a.name.localeCompare(b.name));
            
            this.barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            data.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.name;
                opt.textContent = b.name;
                this.barangaySelect.appendChild(opt);
            });
            this.barangaySelect.disabled = false;
            
            // Pre-select barangay
            const currentVal = this.barangaySelect.dataset.current;
            if (isPreload && currentVal) {
                this.barangaySelect.value = currentVal;
            }
            
        } catch (error) {
            console.error('Error loading barangays:', error);
            this.barangaySelect.innerHTML = '<option value="">Error loading data</option>';
        }
    }
}
