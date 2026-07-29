/**
 * Helper JS per la gestione e riutilizzo del modale <dialog id="order-action-dialog">
 * ed esportazione dei documenti XML (Ordine, Fattura, Nota di Consegna).
 */
class AdminOrderExportHelper {
    static getAdminControllerUrl() {
        if (window.mpCustomerInvoiceAdminUrl) {
            return window.mpCustomerInvoiceAdminUrl;
        }
        if (window.MPCUSTOMERINVOICE_ADMIN_CONTROLLER) {
            return window.MPCUSTOMERINVOICE_ADMIN_CONTROLLER;
        }
        if (typeof adminControllerUrl !== 'undefined') {
            return adminControllerUrl;
        }
        const existingLink = document.querySelector('a[href*="AdminMpCustomerInvoice"]');
        if (existingLink && existingLink.href) {
            return existingLink.href;
        }
        const tokenMatch = window.location.href.match(/_token=([^&]+)/);
        const token = tokenMatch ? tokenMatch[1] : '';
        const basePath = window.location.origin + window.location.pathname.replace(/\/index\.php.*$/, '/index.php');
        return `${basePath}?controller=AdminMpCustomerInvoice${token ? '&_token=' + token : ''}`;
    }

    static getOrderIdFromUrlOrData(target) {
        if (target && target.dataset && target.dataset.id_order) {
            return target.dataset.id_order;
        }
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('id_order')) {
            return urlParams.get('id_order');
        }
        const match = window.location.pathname.match(/orders\/(\d+)/);
        return match ? match[1] : null;
    }

    static exportDocument(idOrder, documentType = 'order') {
        const adminUrl = this.getAdminControllerUrl();
        if (!adminUrl || !idOrder) {
            console.error('URL controller o ID ordine mancante per l\'esportazione.');
            alert('Impossibile completare l\'esportazione: dati insufficienti.');
            return;
        }
        const exportUrl = `${adminUrl}&action=showCustomExportPage&id_order=${idOrder}&document_type=${documentType}`;
        window.open(exportUrl, '_blank');
    }

    static ensureDialogElement() {
        let dialog = document.getElementById('order-action-dialog');
        if (dialog) {
            return dialog;
        }

        dialog = document.createElement('dialog');
        dialog.id = 'order-action-dialog';
        dialog.className = 'order-action-dialog';
        dialog.innerHTML = `
            <form method="dialog" id="order-action-form">
                <div class="dialog-header">
                    <h4 id="order-action-dialog-title">Esporta documento</h4>
                </div>
                <div class="dialog-body" id="order-action-dialog-body"></div>
                <div class="dialog-footer">
                    <button type="button" class="btn btn-secondary" id="order-action-dialog-cancel">Annulla</button>
                    <button type="submit" class="btn btn-primary" value="confirm" id="order-action-dialog-confirm">CONFERMA</button>
                </div>
            </form>
        `;
        document.body.appendChild(dialog);

        return dialog;
    }

    static openExportDialog(idOrder) {
        if (!idOrder) {
            idOrder = this.getOrderIdFromUrlOrData();
        }
        if (!idOrder) {
            alert('Impossibile determinare l\'ID dell\'ordine per l\'esportazione.');
            return;
        }

        const dialog = this.ensureDialogElement();
        const titleElem = dialog.querySelector('#order-action-dialog-title');
        const bodyElem = dialog.querySelector('#order-action-dialog-body');
        const formElem = dialog.querySelector('#order-action-form');

        if (titleElem) {
            titleElem.textContent = 'Esporta documento';
        }

        if (bodyElem) {
            bodyElem.innerHTML = `
                <div class="dialog-option">
                    <label><input type="radio" name="document" value="order" checked> Esporta Ordine</label>
                </div>
                <div class="dialog-option">
                    <label><input type="radio" name="document" value="invoice"> Esporta Fattura</label>
                </div>
                <div class="dialog-option">
                    <label><input type="radio" name="document" value="sales_note"> Esporta Nota vendita</label>
                </div>
            `;
        }

        // Sostituzione form per resettare listener ed evitare duplicati
        const newFormElem = formElem.cloneNode(true);
        formElem.parentNode.replaceChild(newFormElem, formElem);

        // Re-bind del pulsante Annulla
        const cancelBtn = newFormElem.querySelector('#order-action-dialog-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                dialog.close();
            });
        }

        // Handler invio form di conferma
        newFormElem.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(newFormElem);
            const documentType = formData.get('document') || 'order';
            dialog.close();
            this.exportDocument(idOrder, documentType);
        });

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
    }

    static openPrintDialog(idOrder) {
        if (!idOrder) {
            idOrder = this.getOrderIdFromUrlOrData();
        }
        if (!idOrder) {
            if (typeof showErrorMessage === 'function') {
                showErrorMessage('Impossibile determinare l\'ID dell\'ordine per la stampa.');
            } else {
                alert('Impossibile determinare l\'ID dell\'ordine per la stampa.');
            }
            return;
        }

        const dialog = this.ensureDialogElement();
        const titleElem = dialog.querySelector('#order-action-dialog-title');
        const bodyElem = dialog.querySelector('#order-action-dialog-body');
        const formElem = dialog.querySelector('#order-action-form');

        if (titleElem) {
            titleElem.textContent = 'Stampa documento';
        }

        if (bodyElem) {
            bodyElem.innerHTML = `
                <div class="dialog-option">
                    <label><input type="radio" name="document" value="order" checked> Ordine</label>
                </div>
                <div class="dialog-option">
                    <label><input type="radio" name="document" value="invoice"> Fattura</label>
                </div>
                <div class="dialog-option">
                    <label><input type="radio" name="document" value="delivery"> Spedizione</label>
                </div>
                <div class="dialog-option">
                    <label><input type="radio" name="document" value="address"> Etichetta Indirizzo</label>
                </div>
                <div class="dialog-suboption" id="print-copies-container" style="display:none;margin-top:10px;margin-left:24px;">
                    <label style="display:flex;align-items:center;gap:8px;">
                        Copie:
                        <input type="number" name="copies" id="print-copies-input" value="1" min="1" max="99" class="form-control" style="width:80px;display:inline-block;">
                    </label>
                </div>
            `;
        }

        const newFormElem = formElem.cloneNode(true);
        formElem.parentNode.replaceChild(newFormElem, formElem);

        const radios = newFormElem.querySelectorAll('input[name="document"]');
        const copiesContainer = newFormElem.querySelector('#print-copies-container');

        radios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.value === 'address' && radio.checked) {
                    if (copiesContainer) copiesContainer.style.display = 'block';
                } else {
                    if (copiesContainer) copiesContainer.style.display = 'none';
                }
            });
        });

        const cancelBtn = newFormElem.querySelector('#order-action-dialog-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                dialog.close();
            });
        }

        newFormElem.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(newFormElem);
            const documentType = formData.get('document') || 'order';
            const copies = parseInt(formData.get('copies') || '1') || 1;
            dialog.close();
            this.executePrint(idOrder, documentType, copies);
        });

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
    }

    static async executePrint(idOrder, documentType = 'order', copies = 1) {
        const adminUrl = this.getAdminControllerUrl();
        if (!adminUrl || !idOrder) {
            if (typeof showErrorMessage === 'function') {
                showErrorMessage('Impossibile completare la stampa: dati insufficienti.');
            } else {
                alert('Impossibile completare la stampa: dati insufficienti.');
            }
            return;
        }

        // Apriamo la nuova scheda prima del fetch asincrono per evitare il blocco popup del browser
        let printWindow = null;
        try {
            printWindow = window.open('about:blank', '_blank');
        } catch (e) {
            console.warn('Impossibile aprire nuova scheda in anticipo:', e);
        }

        try {
            const printUrl = `${adminUrl}&ajax=1&action=renderPdfDocument&id_order=${idOrder}&document_type=${documentType}&copies=${copies}`;
            const response = await fetch(printUrl);
            const res = await response.json();
            if (res && res.success) {
                if (typeof showSuccessMessage === 'function') {
                    showSuccessMessage(res.message);
                } else if (typeof showNoticeMessage === 'function') {
                    showNoticeMessage(res.message);
                } else {
                    alert(res.message);
                }

                if (res.pdf) {
                    const byteCharacters = atob(res.pdf);
                    const byteNumbers = new Array(byteCharacters.length);
                    for (let i = 0; i < byteCharacters.length; i++) {
                        byteNumbers[i] = byteCharacters.charCodeAt(i);
                    }
                    const byteArray = new Uint8Array(byteNumbers);
                    const blob = new Blob([byteArray], { type: 'application/pdf' });
                    const blobUrl = URL.createObjectURL(blob);

                    if (printWindow && !printWindow.closed) {
                        printWindow.location.href = blobUrl;
                    } else {
                        window.open(blobUrl, '_blank');
                    }
                } else if (printWindow && !printWindow.closed) {
                    printWindow.close();
                }
            } else {
                if (printWindow && !printWindow.closed) {
                    printWindow.close();
                }
                const msg = (res && res.message) ? res.message : 'Errore durante la generazione del documento PDF.';
                if (typeof showErrorMessage === 'function') {
                    showErrorMessage(msg);
                } else {
                    alert(msg);
                }
            }
        } catch (err) {
            if (printWindow && !printWindow.closed) {
                printWindow.close();
            }
            console.error('Errore durante la richiesta di stampa PDF:', err);
            if (typeof showErrorMessage === 'function') {
                showErrorMessage('Errore di comunicazione durante la richiesta di stampa.');
            }
        }
    }

    static async checkAndInjectInvoiceRequestedBadge() {
        if (document.getElementById('mpcustomerinvoice-requested-badge')) {
            return;
        }

        const customerInfo = document.getElementById('customerInfo') || document.querySelector('.customer-card #customerInfo') || document.querySelector('#customerCard');
        if (!customerInfo) {
            return;
        }

        let idCustomer = 0;
        // Metodo 1: Estrazione dal testo dell'header (es. #108802)
        const idMatch = customerInfo.textContent.match(/#(\d+)/);
        if (idMatch) {
            idCustomer = parseInt(idMatch[1]);
        }

        // Metodo 2: Fallback tramite link al cliente
        if (!idCustomer) {
            const linkElem = customerInfo.querySelector('a[href*="customers"], a[href*="customerId"]') || document.querySelector('a[href*="customers"]');
            if (linkElem && linkElem.href) {
                const m = linkElem.href.match(/(?:id_customer|customerId|customers)\/=?(\d+)/) || linkElem.href.match(/id_customer=(\d+)/);
                if (m) idCustomer = parseInt(m[1]);
            }
        }

        if (!idCustomer) return;

        const adminUrl = this.getAdminControllerUrl();
        if (!adminUrl) return;

        try {
            const fetchUrl = `${adminUrl}&ajax=1&action=getCustomerInvoiceData&id_customer=${idCustomer}`;
            const response = await fetch(fetchUrl);
            const res = await response.json();
            if (res && res.success && res.data && res.data.model) {
                const isRequested = parseInt(res.data.model.invoice_requested) === 1
                    || (res.data.model.vat_number && res.data.model.vat_number.trim() !== '')
                    || (res.data.model.dni && res.data.model.dni.trim() !== '');

                if (isRequested) {
                    if (document.getElementById('mpcustomerinvoice-requested-badge')) return;

                    const badge = document.createElement('span');
                    badge.id = 'mpcustomerinvoice-requested-badge';
                    badge.className = 'badge badge-warning';
                    badge.innerHTML = 'Richiede Fattura';

                    let targetContainer = customerInfo.querySelector('.customer-groups');
                    if (!targetContainer) {
                        targetContainer = customerInfo;
                    }
                    targetContainer.appendChild(badge);
                }
            }
        } catch (err) {
            console.error('Errore recupero dati fatturazione per il badge ordine:', err);
        }
    }
}

window.AdminOrderExportHelper = AdminOrderExportHelper;

function exportXML(idOrder) {
    AdminOrderExportHelper.openExportDialog(idOrder);
}
window.exportXML = exportXML;

function openPrintDialog(idOrder) {
    AdminOrderExportHelper.openPrintDialog(idOrder);
}
window.openPrintDialog = openPrintDialog;

document.addEventListener('DOMContentLoaded', () => {
    AdminOrderExportHelper.checkAndInjectInvoiceRequestedBadge();
});
setTimeout(() => AdminOrderExportHelper.checkAndInjectInvoiceRequestedBadge(), 300);
setTimeout(() => AdminOrderExportHelper.checkAndInjectInvoiceRequestedBadge(), 1200);

