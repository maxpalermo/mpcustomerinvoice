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
        if (typeof MpPrintDialog !== 'undefined') {
            MpPrintDialog.open(idOrder, 'export');
        } else {
            console.error('Componente MpPrintDialog non disponibile.');
        }
    }

    static openPrintDialog(idOrder) {
        if (typeof MpPrintDialog !== 'undefined') {
            MpPrintDialog.open(idOrder);
        } else {
            console.error('Componente MpPrintDialog non disponibile.');
        }
    }

    static executePrint(idOrder, documentType = 'order', copies = 1) {
        if (typeof MpPrintDialog !== 'undefined') {
            MpPrintDialog.executePrint(idOrder, documentType, copies);
        } else {
            console.error('Componente MpPrintDialog non disponibile.');
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

    static updateGenerateInvoiceButtonLabel() {
        const forms = document.querySelectorAll('form[action*="/sell/orders/"][action*="/invoice"], form[action*="generateInvoice"]');
        forms.forEach((form) => {
            const buttons = form.querySelectorAll('button');
            buttons.forEach((btn) => {
                btn.childNodes.forEach((node) => {
                    if (node.nodeType === Node.TEXT_NODE && node.textContent.toLowerCase().includes('genera fattura')) {
                        node.textContent = node.textContent.replace(/Genera fattura/gi, 'Genera documento');
                    }
                });
            });
        });
    }

    static bindGenerateDocumentFormSubmit() {
        if (window.mpGenerateDocFormBound) return;
        window.mpGenerateDocFormBound = true;

        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!form || !form.action) return;

            const match = form.action.match(/\/sell\/orders\/(\d+)\/invoice/i);
            if (match) {
                e.preventDefault();
                e.stopPropagation();

                const idOrder = match[1];
                const btn = form.querySelector('button');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="material-icons spinning">autorenew</i> Elaborazione...';
                }

                const adminUrl = AdminOrderExportHelper.getAdminControllerUrl();
                const params = new URLSearchParams();
                params.append('ajax', '1');
                params.append('action', 'HandleGenerateInvoice');
                params.append('id_order', idOrder);

                fetch(adminUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: params.toString(),
                })
                    .then(r => r.json())
                    .then(res => {
                        window.location.reload();
                    })
                    .catch(err => {
                        console.error('Errore generazione documento:', err);
                        window.location.reload();
                    });
            }
        }, true);
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
    AdminOrderExportHelper.updateGenerateInvoiceButtonLabel();
    AdminOrderExportHelper.bindGenerateDocumentFormSubmit();
});
setTimeout(() => {
    AdminOrderExportHelper.checkAndInjectInvoiceRequestedBadge();
    AdminOrderExportHelper.updateGenerateInvoiceButtonLabel();
    AdminOrderExportHelper.bindGenerateDocumentFormSubmit();
}, 300);
setTimeout(() => {
    AdminOrderExportHelper.checkAndInjectInvoiceRequestedBadge();
    AdminOrderExportHelper.updateGenerateInvoiceButtonLabel();
    AdminOrderExportHelper.bindGenerateDocumentFormSubmit();
}, 1200);

