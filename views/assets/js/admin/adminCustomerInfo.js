/**
 * MP Customer Invoice Admin Card Controller (Vanilla JS)
 * Relocates card above customer-private-note-card and manages CRUD via AJAX fetch (form-urlencoded).
 * Enhances customer address grid table with "Tipo Indirizzo" column.
 */

class MpCustomerInvoiceAdminCard {
    constructor() {
        this.initialized = false;
        this.init();
    }

    init() {
        this.trySetup();
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.trySetup());
        }
        setTimeout(() => this.trySetup(), 100);
        setTimeout(() => this.trySetup(), 500);
        setTimeout(() => this.trySetup(), 1500);
    }

    trySetup() {
        // Clean up duplicate wrappers if rendered by multiple hooks
        const wrappers = document.querySelectorAll('#mpcustomerinvoice-customer-card-wrapper');
        if (wrappers.length > 1) {
            for (let i = 1; i < wrappers.length; i++) {
                wrappers[i].remove();
            }
        }

        this.cardWrapper = document.getElementById('mpcustomerinvoice-customer-card-wrapper');
        if (this.cardWrapper) {
            this.relocateCardAbovePrivateNotes();
        }

        // Enhance addresses table if present on page
        this.enhanceAddressGridTable();

        if (this.initialized) return;
        if (!this.cardWrapper) return;
        this.initialized = true;

        this.form = document.getElementById('mpcustomerinvoice-card-form');
        this.btnEdit = document.getElementById('mpcustomerinvoice-btn-edit');
        this.btnSave = document.getElementById('mpcustomerinvoice-btn-save');
        this.btnCancel = document.getElementById('mpcustomerinvoice-btn-cancel');
        this.alertContainer = document.getElementById('mpcustomerinvoice-alert-container');
        this.viewMode = document.getElementById('mpcustomerinvoice-view-mode');
        this.editMode = document.getElementById('mpcustomerinvoice-edit-mode');
        this.jobAreaSelect = document.getElementById('field-id_customer_invoice_job_area');
        this.jobPositionSelect = document.getElementById('field-id_customer_invoice_job_position');

        this.bindEvents();
    }

    getCustomerId() {
        if (this.form) {
            const input = this.form.querySelector('input[name="id_customer"]');
            if (input && input.value) return parseInt(input.value);
        }
        const m = window.location.href.match(/customers\/(\d+)/);
        if (m) return parseInt(m[1]);
        return 0;
    }

    /**
     * Intercepts customer_address_grid_table and inserts 'Tipo Indirizzo' column (Fatturazione vs Spedizione)
     */
    async enhanceAddressGridTable(forceRefresh = false) {
        const addressCard = document.querySelector('.customer-addresses-card') || document.querySelector('[data-role="customer-addresses-card"]');
        let table = null;
        if (addressCard) {
            table = addressCard.querySelector('table');
        }
        if (!table) {
            table = document.getElementById('customer_address_grid_table') || document.querySelector('table.grid-table');
        }
        if (!table) return;

        if (forceRefresh) {
            delete table.dataset.enhancedTypeAddress;
            table.querySelectorAll('.col-type-address').forEach(el => el.remove());
        }

        // Prevent duplicate processing with immediate synchronous lock
        if (table.dataset.enhancedTypeAddress === 'true' || table.querySelector('th.col-type-address')) {
            return;
        }
        table.dataset.enhancedTypeAddress = 'true';

        // Clean up any existing duplicate headers/cells
        table.querySelectorAll('.col-type-address').forEach(el => el.remove());

        const idCustomer = this.getCustomerId();
        if (!idCustomer) {
            table.dataset.enhancedTypeAddress = 'false';
            return;
        }

        const endpoint = (this.form && typeof this.form.getAttribute === 'function' && this.form.getAttribute('action')) || window.MPCUSTOMERINVOICE_ADMIN_CONTROLLER;
        if (!endpoint) {
            table.dataset.enhancedTypeAddress = 'false';
            return;
        }

        let idAddressInvoice = 0;
        try {
            const url = `${endpoint}&ajax=1&action=getCustomerInvoiceData&id_customer=${idCustomer}`;
            const response = await fetch(url);
            const res = await response.json();
            if (res.success && res.data && res.data.model) {
                idAddressInvoice = parseInt(res.data.model.id_address_invoice || 0);
            }
        } catch (err) {
            console.error('Errore recupero id_address_invoice per tabella indirizzi:', err);
        }

        // Re-clean state before inserting DOM elements
        table.querySelectorAll('.col-type-address').forEach(el => el.remove());

        const thList = table.querySelectorAll('thead tr th');
        if (thList.length === 0) return;

        let idThIndex = 0;
        for (let i = 0; i < thList.length; i++) {
            const text = thList[i].textContent.trim().toLowerCase();
            if (text === 'id' || text.startsWith('id ')) {
                idThIndex = i;
                break;
            }
        }

        const newTh = document.createElement('th');
        newTh.className = 'col-type-address text-center';
        newTh.innerHTML = 'Tipo Indirizzo';
        thList[idThIndex].parentNode.insertBefore(newTh, thList[idThIndex].nextSibling);

        const trList = table.querySelectorAll('tbody tr');
        trList.forEach(tr => {
            const tdList = tr.querySelectorAll('td');
            if (tdList.length <= idThIndex) return;

            let rowAddrId = parseInt(tr.getAttribute('data-id') || tr.dataset.id || 0);
            if (!rowAddrId && tdList[idThIndex]) {
                const rawText = tdList[idThIndex].textContent.trim().replace(/\s+/g, '');
                rowAddrId = parseInt(rawText);
            }
            if (!rowAddrId) {
                const link = tr.querySelector('a[href*="id_address="], a[href*="address_id="]');
                if (link) {
                    const m = link.href.match(/(?:id_address|address_id)=(\d+)/);
                    if (m) rowAddrId = parseInt(m[1]);
                }
            }

            const isInvoice = (idAddressInvoice > 0 && rowAddrId === idAddressInvoice);

            const badgeHtml = isInvoice
                ? '<span class="badge px-2 py-1" style="background-color: #28a745 !important; color: #ffffff !important; font-weight: 600; font-size: 11px; border-radius: 4px; display: inline-flex; align-items: center;"><i class="material-icons" style="font-size: 13px; margin-right: 3px; color: #ffffff;">receipt</i> Fatturazione</span>'
                : '<span class="badge px-2 py-1" style="background-color: #17a2b8 !important; color: #ffffff !important; font-weight: 600; font-size: 11px; border-radius: 4px; display: inline-flex; align-items: center;"><i class="material-icons" style="font-size: 13px; margin-right: 3px; color: #ffffff;">local_shipping</i> Spedizione</span>';

            const newTd = document.createElement('td');
            newTd.className = 'col-type-address text-center align-middle';
            newTd.innerHTML = badgeHtml;

            tdList[idThIndex].parentNode.insertBefore(newTd, tdList[idThIndex].nextSibling);
        });
    }

    /**
     * Relocates the Customer Invoice card directly above .customer-private-note-card
     */
    relocateCardAbovePrivateNotes() {
        if (!this.cardWrapper) return;

        const targetSelectors = [
            '.customer-private-note-card',
            '#customer-private-note-card',
            '[data-role="private-note"]',
            '[data-role="customer-private-note-card"]'
        ];

        let targetCard = null;
        for (const selector of targetSelectors) {
            targetCard = document.querySelector(selector);
            if (targetCard) break;
        }

        if (!targetCard) {
            const cards = document.querySelectorAll('.card');
            for (const card of cards) {
                const text = card.textContent || '';
                if (text.includes('Messaggi privati sul cliente') || text.includes('Messaggi privati') || text.includes('Private notes')) {
                    targetCard = card;
                    break;
                }
            }
        }

        if (targetCard && targetCard.parentNode) {
            if (this.cardWrapper.nextElementSibling !== targetCard) {
                targetCard.parentNode.insertBefore(this.cardWrapper, targetCard);
            }
        }
    }

    bindEvents() {
        if (this.btnEdit) {
            this.btnEdit.addEventListener('click', () => this.switchMode('edit'));
        }

        if (this.btnCancel) {
            this.btnCancel.addEventListener('click', () => this.switchMode('view'));
        }

        if (this.btnSave) {
            this.btnSave.addEventListener('click', (e) => {
                e.preventDefault();
                this.saveData();
            });
        }

        if (this.form) {
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveData();
            });
        }

        if (this.jobAreaSelect) {
            this.jobAreaSelect.addEventListener('change', () => this.onJobAreaChange());
        }
    }

    switchMode(mode) {
        if (mode === 'edit') {
            this.viewMode.classList.add('d-none');
            this.editMode.classList.remove('d-none');
            this.btnEdit.classList.add('d-none');
            this.btnSave.classList.remove('d-none');
            this.btnCancel.classList.remove('d-none');
        } else {
            this.editMode.classList.add('d-none');
            this.viewMode.classList.remove('d-none');
            this.btnSave.classList.add('d-none');
            this.btnCancel.classList.add('d-none');
            this.btnEdit.classList.remove('d-none');
            this.hideAlert();
        }
    }

    showAlert(message, type = 'success') {
        if (!this.alertContainer) return;
        this.alertContainer.className = `alert alert-${type} alert-dismissible fade show mb-3`;
        this.alertContainer.innerHTML = `
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close" onclick="this.parentElement.style.display='none';">
                <span aria-hidden="true">&times;</span>
            </button>
        `;
        this.alertContainer.style.display = 'block';
    }

    hideAlert() {
        if (this.alertContainer) {
            this.alertContainer.style.display = 'none';
        }
    }

    /**
     * Dynamic Job Area change -> Fetch Job Positions via AJAX
     */
    async onJobAreaChange() {
        if (!this.jobAreaSelect || !this.jobPositionSelect) return;

        const idJobArea = this.jobAreaSelect.value;
        this.jobPositionSelect.innerHTML = '<option value="0">Caricamento...</option>';

        if (parseInt(idJobArea) <= 0) {
            this.jobPositionSelect.innerHTML = '<option value="0">-- Seleziona Professione --</option>';
            return;
        }

        const endpoint = (this.form && typeof this.form.getAttribute === 'function' && this.form.getAttribute('action')) || window.MPCUSTOMERINVOICE_ADMIN_CONTROLLER;
        const url = `${endpoint}&ajax=1&action=getJobPositions&idJobArea=${idJobArea}`;

        try {
            const response = await fetch(url);
            const res = await response.json();

            if (res.success && Array.isArray(res.jobPositions)) {
                let options = '<option value="0">-- Seleziona Professione --</option>';
                res.jobPositions.forEach(pos => {
                    options += `<option value="${pos.id}">${pos.name}</option>`;
                });
                this.jobPositionSelect.innerHTML = options;
            } else {
                this.jobPositionSelect.innerHTML = '<option value="0">Nessuna professione trovata</option>';
            }
        } catch (err) {
            console.error('Errore caricamento professioni:', err);
            this.jobPositionSelect.innerHTML = '<option value="0">-- Seleziona Professione --</option>';
        }
    }

    /**
     * Submit form via AJAX fetch using POST application/x-www-form-urlencoded
     */
    async saveData() {
        if (!this.form) return;

        this.hideAlert();
        this.btnSave.disabled = true;
        const originalSaveText = this.btnSave.innerHTML;
        this.btnSave.innerHTML = '<i class="material-icons spinner-border spinner-border-sm mr-1">sync</i> Salvataggio...';

        const formData = new FormData(this.form);
        const searchParams = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            searchParams.append(key, value);
        }

        searchParams.set('action', 'saveCustomerInvoiceData');
        searchParams.set('ajax', '1');

        const actionUrl = (this.form && typeof this.form.getAttribute === 'function' && this.form.getAttribute('action')) || window.MPCUSTOMERINVOICE_ADMIN_CONTROLLER;

        try {
            const response = await fetch(actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: searchParams.toString()
            });

            const res = await response.json();

            if (res.success && res.data) {
                this.showAlert(res.message || 'Dati salvati con successo!', 'success');
                this.updateViewMode(res.data);
                this.switchMode('view');
                this.enhanceAddressGridTable(true);
            } else {
                this.showAlert(res.error || 'Errore durante il salvataggio dei dati.', 'danger');
            }
        } catch (err) {
            console.error('Errore fetch saveCustomerInvoiceData:', err);
            this.showAlert('Errore di connessione al server.', 'danger');
        } finally {
            this.btnSave.disabled = false;
            this.btnSave.innerHTML = originalSaveText;
        }
    }

    /**
     * Update View Mode fields dynamically after successful AJAX save
     */
    updateViewMode(data) {
        if (!data || !data.model) return;
        const m = data.model;

        // Soggetto badge
        const typeContainer = document.getElementById('view-type-container');
        if (typeContainer) {
            let badgeHtml = '';
            if (m.type === 'PARTITA_IVA') {
                badgeHtml = '<span class="badge badge-info"><i class="material-icons" style="font-size: 12px;">business</i> Partita IVA</span>';
            } else if (m.type === 'ENTE') {
                badgeHtml = '<span class="badge badge-warning"><i class="material-icons" style="font-size: 12px;">account_balance</i> Ente Pubblico / P.A.</span>';
            } else {
                badgeHtml = '<span class="badge badge-secondary"><i class="material-icons" style="font-size: 12px;">person</i> Privato</span>';
            }
            if (m.is_foreign) {
                badgeHtml += ' <span class="badge badge-dark ml-1"><i class="material-icons" style="font-size: 12px;">public</i> Estero</span>';
            }
            typeContainer.innerHTML = badgeHtml;
        }

        // Text fields
        const setField = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val || '--';
        };

        setField('view-company', m.company);
        setField('view-vat_number', m.vat_number);
        setField('view-dni', m.dni);
        setField('view-pec', m.pec);
        setField('view-cig', m.cig);
        setField('view-cup', m.cup);
        setField('view-cuu', m.cuu);
        setField('view-id_eurosolution', m.id_eurosolution);
        setField('view-job_area', data.job_area_name);
        setField('view-job_position', data.job_position_name);

        const sdiEl = document.getElementById('view-sdi');
        if (sdiEl) {
            sdiEl.innerHTML = m.sdi ? `<code>${m.sdi}</code>` : '--';
        }

        // Billing address display update
        const addrContainer = document.getElementById('view-id_address_invoice');
        if (addrContainer) {
            if (m.id_address_invoice > 0 && data.invoice_address_text) {
                addrContainer.innerHTML = `<span class="badge px-2 py-1" style="background-color: #28a745 !important; color: #ffffff !important; font-weight: 600; font-size: 12px; border-radius: 4px; display: inline-flex; align-items: center;"><i class="material-icons" style="font-size: 14px; margin-right: 4px; color: #ffffff;">receipt</i> ${data.invoice_address_text}</span>`;
            } else {
                addrContainer.innerHTML = `<span class="badge px-2 py-1" style="background-color: #ffc107 !important; color: #212529 !important; font-weight: 600; font-size: 12px; border-radius: 4px; display: inline-flex; align-items: center;"><i class="material-icons" style="font-size: 14px; margin-right: 4px; color: #212529;">warning</i> Nessun indirizzo di fatturazione selezionato</span>`;
            }
        }
    }
}

// Auto-instantiate singleton
window.mpCustomerInvoiceAdminCard = new MpCustomerInvoiceAdminCard();
