/**
 * Componente JS indipendente e riutilizzabile per la gestione del modale e delle chiamate di Stampa Documenti.
 * Supporta la visualizzazione dinamica in formato Card UI con icone e schede colorate.
 */
class MpPrintDialog {
    /**
     * Inietta le regole CSS custom per lo stile moderno delle schede.
     */
    static injectStyles() {
        if (document.getElementById("mp-print-dialog-styles")) {
            return;
        }

        const style = document.createElement("style");
        style.id = "mp-print-dialog-styles";
        style.textContent = `
            dialog#mp-print-dialog {
                border: none !important;
                border-radius: 14px !important;
                padding: 0 !important;
                width: 95% !important;
                max-width: 580px !important;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.08) !important;
                background: #ffffff !important;
                overflow: hidden !important;
                margin: auto !important;
            }

            dialog#mp-print-dialog::backdrop {
                background: rgba(15, 23, 42, 0.5) !important;
                backdrop-filter: blur(4px) !important;
            }

            #growls, #growls-default, #growls.default, .growl, .growl-notice, .growl-error, .growl-warning, .growl-success, .alert-growl, .component-growl {
                z-index: 999999999 !important;
            }
            #growls {
                position: fixed !important;
                top: 20px !important;
                right: 20px !important;
                z-index: 999999999 !important;
            }

            .mp-print-dialog-container {
                display: flex;
                flex-direction: column;
                margin: 0;
                padding: 0;
            }

            .mp-print-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
            }

            .mp-print-header h4 {
                margin: 0;
                font-size: 1.1rem;
                font-weight: 700;
                color: #0f172a;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .mp-print-header .mp-order-ref-badge {
                background: #e2e8f0;
                color: #334155;
                font-size: 0.8rem;
                padding: 2px 8px;
                border-radius: 6px;
                font-weight: 600;
            }

            .mp-print-close-btn {
                background: transparent;
                border: none;
                color: #64748b;
                cursor: pointer;
                border-radius: 6px;
                padding: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.2s;
            }
            .mp-print-close-btn:hover {
                background: #e2e8f0;
                color: #0f172a;
            }

            .mp-print-body {
                padding: 20px;
                min-height: 180px;
            }

            /* Loading Spinner & Skeleton */
            .mp-print-loader {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 40px 20px;
                gap: 12px;
                color: #64748b;
            }
            .mp-print-spinner {
                width: 36px;
                height: 36px;
                border: 3px solid #e2e8f0;
                border-top-color: #3b82f6;
                border-radius: 50%;
                animation: mp-spin 0.8s linear infinite;
            }
            @keyframes mp-spin {
                to { transform: rotate(360deg); }
            }

            /* Cards Grid */
            .mp-print-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 14px;
            }

            @media (max-width: 520px) {
                .mp-print-grid {
                    grid-template-columns: 1fr;
                }
            }

            .mp-print-card {
                position: relative;
                border: 2px solid #e2e8f0;
                border-radius: 12px;
                padding: 16px;
                background: #ffffff;
                cursor: pointer;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
                flex-direction: column;
                gap: 10px;
                user-select: none;
            }

            .mp-print-card:hover {
                transform: translateY(-2px);
                border-color: #cbd5e1;
                box-shadow: 0 6px 16px rgba(0, 0, 0, 0.06);
            }

            .mp-print-card.selected {
                border-color: var(--accent-color, #3b82f6);
                background: var(--bg-tint, rgba(59, 130, 246, 0.04));
                box-shadow: 0 4px 14px var(--shadow-tint, rgba(59, 130, 246, 0.15));
            }

            .mp-card-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .mp-card-icon-wrap {
                width: 42px;
                height: 42px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: var(--icon-bg, #f1f5f9);
                color: var(--accent-color, #334155);
            }

            .mp-card-icon-wrap .material-icons {
                font-size: 24px;
            }

            .mp-card-check {
                color: var(--accent-color, #3b82f6);
                font-size: 22px;
                opacity: 0;
                transform: scale(0.7);
                transition: all 0.2s ease;
            }

            .mp-print-card.selected .mp-card-check {
                opacity: 1;
                transform: scale(1);
            }

            .mp-card-title {
                font-size: 0.95rem;
                font-weight: 700;
                color: #0f172a;
                margin: 0;
            }

            .mp-card-desc {
                font-size: 0.78rem;
                color: #64748b;
                line-height: 1.35;
                margin: 0;
            }

            /* Embedded Copies Control in Address Card */
            .mp-copies-wrap {
                margin-top: 6px;
                padding-top: 10px;
                border-top: 1px dashed #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .mp-copies-label {
                font-size: 0.78rem;
                font-weight: 600;
                color: #475569;
            }

            .mp-copies-stepper {
                display: inline-flex;
                align-items: center;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                overflow: hidden;
                background: #ffffff;
            }

            .mp-copies-btn {
                background: #f1f5f9;
                border: none;
                color: #334155;
                width: 28px;
                height: 28px;
                font-weight: 700;
                font-size: 14px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.15s;
            }
            .mp-copies-btn:hover {
                background: #e2e8f0;
            }

            .mp-copies-input {
                width: 36px;
                height: 28px;
                border: none;
                text-align: center;
                font-weight: 700;
                font-size: 0.85rem;
                color: #0f172a;
                outline: none;
                -moz-appearance: textfield;
            }
            .mp-copies-input::-webkit-outer-spin-button,
            .mp-copies-input::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }

            /* Footer */
            .mp-print-footer {
                padding: 14px 20px;
                background: #f8fafc;
                border-top: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 10px;
            }

            .mp-btn-cancel {
                padding: 8px 16px;
                border-radius: 8px;
                border: 1px solid #cbd5e1;
                background: #ffffff;
                color: #475569;
                font-weight: 600;
                font-size: 0.85rem;
                cursor: pointer;
                transition: all 0.15s;
            }
            .mp-btn-cancel:hover {
                background: #f1f5f9;
                color: #0f172a;
            }

            .mp-btn-submit {
                padding: 8px 20px;
                border-radius: 8px;
                border: none;
                background: #2563eb;
                color: #ffffff;
                font-weight: 600;
                font-size: 0.85rem;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
                transition: all 0.15s;
            }
            .mp-btn-submit:hover {
                background: #1d4ed8;
                box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Recupera l'URL base del controller admin.
     */
    static getAdminControllerUrl() {
        if (window.mpCustomerInvoiceAdminUrl) {
            return window.mpCustomerInvoiceAdminUrl;
        }
        if (window.MPCUSTOMERINVOICE_ADMIN_CONTROLLER) {
            return window.MPCUSTOMERINVOICE_ADMIN_CONTROLLER;
        }
        if (typeof adminControllerUrl !== "undefined") {
            return adminControllerUrl;
        }
        const existingLink = document.querySelector('a[href*="AdminMpCustomerInvoice"]');
        if (existingLink && existingLink.href) {
            return existingLink.href;
        }
        const tokenMatch = window.location.href.match(/_token=([^&]+)/);
        const token = tokenMatch ? tokenMatch[1] : "";
        const basePath = window.location.origin + window.location.pathname.replace(/\/index\.php.*$/, "/index.php");
        return `${basePath}?controller=AdminMpCustomerInvoice${token ? "&_token=" + token : ""}`;
    }

    /**
     * Garantisce la presenza dell'elemento <dialog id="mp-print-dialog"> dedicato nel DOM.
     */
    static ensureDialogElement() {
        this.injectStyles();
        let dialog = document.getElementById("mp-print-dialog");
        if (dialog) {
            return dialog;
        }

        dialog = document.createElement("dialog");
        dialog.id = "mp-print-dialog";
        dialog.innerHTML = `
            <form method="dialog" id="mp-print-form" class="mp-print-dialog-container">
                <div class="mp-print-header">
                    <h4>
                        <span class="material-icons" style="color:#2563eb;">print</span>
                        <span>Stampa documento</span>
                        <span class="mp-order-ref-badge" id="mp-print-order-ref"></span>
                    </h4>
                    <button type="button" class="mp-print-close-btn" id="mp-print-dialog-close-x" title="Chiudi">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                <div class="mp-print-body" id="mp-print-dialog-body"></div>
                <div class="mp-print-footer">
                    <button type="button" class="mp-btn-cancel" id="mp-print-dialog-cancel-btn">Annulla</button>
                    <button type="submit" class="mp-btn-submit" value="confirm" id="mp-print-dialog-confirm">
                        <span class="material-icons" style="font-size:18px;">print</span> STAMPA DOCUMENTO
                    </button>
                </div>
            </form>
        `;
        document.body.appendChild(dialog);

        return dialog;
    }

    /**
     * Apre il modale di stampa per l'ordine specificato.
     * @param {number|string} idOrder
     */
    /**
     * Apre il modale di stampa o esportazione per l'ordine specificato.
     * @param {number|string} idOrder
     * @param {string} mode ('print' | 'export')
     */
    static async open(idOrder, mode = "print") {
        if (!idOrder) {
            const match = window.location.pathname.match(/orders\/(\d+)/) || window.location.search.match(/id_order=(\d+)/);
            idOrder = match ? match[1] : null;
        }

        if (!idOrder) {
            if (typeof showErrorMessage === "function") {
                showErrorMessage("Impossibile determinare l'ID dell'ordine per la procedura.");
            } else {
                alert("Impossibile determinare l'ID dell'ordine per la procedura.");
            }
            return;
        }

        const isExport = mode === "export";

        const dialog = this.ensureDialogElement();
        const bodyElem = dialog.querySelector("#mp-print-dialog-body");
        const refBadge = dialog.querySelector("#mp-print-order-ref");
        const formElem = dialog.querySelector("#mp-print-form");

        // Personalizza header e pulsante in base a modalità (print vs export)
        const headerIcon = dialog.querySelector(".mp-print-header .material-icons");
        if (headerIcon) {
            headerIcon.textContent = isExport ? "file_download" : "print";
        }

        const headerTitle = dialog.querySelector(".mp-print-header h4 span:nth-child(2)");
        if (headerTitle) {
            headerTitle.textContent = isExport ? "Esporta documento" : "Stampa documento";
        }

        const confirmBtn = dialog.querySelector("#mp-print-dialog-confirm");
        if (confirmBtn) {
            confirmBtn.innerHTML = isExport ? '<span class="material-icons" style="font-size:18px;">file_download</span> ESPORTA DOCUMENTO' : '<span class="material-icons" style="font-size:18px;">print</span> STAMPA DOCUMENTO';
        }

        if (refBadge) {
            refBadge.textContent = `#${idOrder}`;
        }

        // 1. Mostra loader iniziale nel body del modale
        if (bodyElem) {
            bodyElem.innerHTML = `
                <div class="mp-print-loader">
                    <div class="mp-print-spinner"></div>
                    <span style="font-size:0.88rem;font-weight:600;color:#475569;">Verifica disponibilità documenti ordine...</span>
                </div>
            `;
        }

        // Mostra il modale a schermo
        if (typeof dialog.showModal === "function") {
            try {
                if (!dialog.open) dialog.showModal();
            } catch (e) {
                dialog.setAttribute("open", "");
            }
        } else {
            dialog.setAttribute("open", "");
        }

        // Bind pulsanti chiudi/annulla subito per garantire la chiusura anche durante il caricamento
        const closeX = dialog.querySelector("#mp-print-dialog-close-x");
        const cancelBtn = dialog.querySelector("#mp-print-dialog-cancel-btn");
        const handleClose = (e) => {
            if (e) e.preventDefault();
            dialog.close();
        };
        if (closeX) closeX.onclick = handleClose;
        if (cancelBtn) cancelBtn.onclick = handleClose;

        // 2. Fetch informazioni ordine via AJAX
        let orderInfo = {
            has_invoice: false,
            is_brt_active: typeof window.isBrtModuleActive !== "undefined" ? window.isBrtModuleActive : false,
            has_brt_label: false,
            reference: "",
        };

        try {
            const adminUrl = this.getAdminControllerUrl();
            const response = await fetch(`${adminUrl}&ajax=1&action=getOrderPrintInfo&id_order=${idOrder}`);
            const res = await response.json();
            if (res && res.success) {
                orderInfo = res;
                if (refBadge) {
                    refBadge.textContent = res.reference ? `#${idOrder} (${res.reference})` : `#${idOrder}`;
                }
            }
        } catch (e) {
            console.warn("Impossibile recuperare info avanzate ordine per il modale:", e);
        }

        // 3. Renderizza le schede informative dinamiche
        this.renderCards(bodyElem, orderInfo, mode);

        // 4. Bind selezione schede e stepper copie
        this.bindCardSelection(formElem);

        // 5. Gestione Submit Form
        formElem.onsubmit = (e) => {
            e.preventDefault();
            const selectedCard = formElem.querySelector(".mp-print-card.selected");
            const documentType = selectedCard ? selectedCard.dataset.value : "order";

            dialog.close();

            if (isExport) {
                if (typeof AdminOrderExportHelper !== "undefined") {
                    AdminOrderExportHelper.exportDocument(idOrder, documentType);
                } else {
                    console.error("AdminOrderExportHelper non disponibile per l'esportazione.");
                }
                return;
            }

            let copies = 1;
            const copiesInput = formElem.querySelector("#mp-print-copies-input");
            if (copiesInput) {
                copies = parseInt(copiesInput.value || "1", 10) || 1;
            }

            if (documentType === "brt") {
                if (typeof window.brtRestApiPrintLabel === "function") {
                    window.brtRestApiPrintLabel(null, idOrder);
                } else if (window.brtModalInstance && typeof window.brtModalInstance.printLabel === "function") {
                    window.brtModalInstance.printLabel(null, idOrder);
                } else {
                    this.executePrint(idOrder, documentType, copies);
                }
            } else {
                this.executePrint(idOrder, documentType, copies);
            }
        };
    }

    /**
     * Costruisce il layout HTML delle Schede Moderne.
     */
    static renderCards(container, orderInfo, mode = "print") {
        if (!container) return;

        const isExport = mode === "export";

        // Scheda 1: ORDINE (Sempre visibile)
        const orderCard = `
            <div class="mp-print-card selected" data-value="order" 
                 style="--accent-color:#2563eb; --bg-tint:rgba(37,99,235,0.04); --shadow-tint:rgba(37,99,235,0.15); --icon-bg:#dbeafe;">
                <div class="mp-card-top">
                    <div class="mp-card-icon-wrap">
                        <span class="material-icons">description</span>
                    </div>
                    <span class="material-icons mp-card-check">check_circle</span>
                </div>
                <div>
                    <h5 class="mp-card-title">Ordine</h5>
                    <p class="mp-card-desc">${isExport ? "Esporta scheda riepilogativa XML" : "Scheda riepilogativa dell'ordine"}</p>
                </div>
            </div>
        `;

        // Scheda 2: FATTURA o NOTA DI VENDITA
        let invoiceOrDeliveryCard = "";
        if (orderInfo.has_invoice) {
            invoiceOrDeliveryCard = `
                <div class="mp-print-card" data-value="invoice" 
                     style="--accent-color:#059669; --bg-tint:rgba(5,150,105,0.04); --shadow-tint:rgba(5,150,105,0.15); --icon-bg:#d1fae5;">
                    <div class="mp-card-top">
                        <div class="mp-card-icon-wrap">
                            <span class="material-icons">receipt_long</span>
                        </div>
                        <span class="material-icons mp-card-check">check_circle</span>
                    </div>
                    <div>
                        <h5 class="mp-card-title">Fattura</h5>
                        <p class="mp-card-desc">${isExport ? "Esporta fattura fiscale emessa XML" : "Fattura fiscale emessa per l'ordine"}</p>
                    </div>
                </div>
            `;
        } else {
            const val = isExport ? "sales_note" : "delivery";
            invoiceOrDeliveryCard = `
                <div class="mp-print-card" data-value="${val}" 
                     style="--accent-color:#7c3aed; --bg-tint:rgba(124,58,237,0.04); --shadow-tint:rgba(124,58,237,0.15); --icon-bg:#ede9fe;">
                    <div class="mp-card-top">
                        <div class="mp-card-icon-wrap">
                            <span class="material-icons">local_shipping</span>
                        </div>
                        <span class="material-icons mp-card-check">check_circle</span>
                    </div>
                    <div>
                        <h5 class="mp-card-title">Nota di vendita</h5>
                        <p class="mp-card-desc">${isExport ? "Esporta nota di vendita XML" : "Documento di trasporto / Nota vendita"}</p>
                    </div>
                </div>
            `;
        }

        if (isExport) {
            container.innerHTML = `
                <div class="mp-print-grid">
                    ${orderCard}
                    ${invoiceOrDeliveryCard}
                </div>
            `;
            return;
        }

        // Scheda 3: ETICHETTA INDIRIZZO (Sempre visibile + Campo Copie Integrato)
        const addressCard = `
            <div class="mp-print-card" data-value="address" 
                 style="--accent-color:#d97706; --bg-tint:rgba(217,119,6,0.04); --shadow-tint:rgba(217,119,6,0.15); --icon-bg:#fef3c7;">
                <div class="mp-card-top">
                    <div class="mp-card-icon-wrap">
                        <span class="material-icons">pin_drop</span>
                    </div>
                    <span class="material-icons mp-card-check">check_circle</span>
                </div>
                <div>
                    <h5 class="mp-card-title">Etichetta Indirizzo</h5>
                    <p class="mp-card-desc">Etichetta per la spedizione del collo</p>
                </div>
                <div class="mp-copies-wrap">
                    <span class="mp-copies-label">Copie:</span>
                    <div class="mp-copies-stepper">
                        <button type="button" class="mp-copies-btn mp-btn-minus">-</button>
                        <input type="number" name="copies" id="mp-print-copies-input" value="1" min="1" max="99" class="mp-copies-input">
                        <button type="button" class="mp-copies-btn mp-btn-plus">+</button>
                    </div>
                </div>
            </div>
        `;

        // Scheda 4: SEGNACOLLO BARTOLINI (Visibile solo se modulo BRT attivo E segnacollo presente)
        let brtCard = "";
        if (orderInfo.is_brt_active && orderInfo.has_brt_label) {
            brtCard = `
                <div class="mp-print-card" data-value="brt" 
                     style="--accent-color:#dc2626; --bg-tint:rgba(220,38,38,0.04); --shadow-tint:rgba(220,38,38,0.15); --icon-bg:#fee2e2;">
                    <div class="mp-card-top">
                        <div class="mp-card-icon-wrap">
                            <span class="material-icons">qr_code_2</span>
                        </div>
                        <span class="material-icons mp-card-check">check_circle</span>
                    </div>
                    <div>
                        <h5 class="mp-card-title">Segnacollo Bartolini</h5>
                        <p class="mp-card-desc">Etichetta segnacollo ufficiale BRT</p>
                    </div>
                </div>
            `;
        }

        container.innerHTML = `
            <div class="mp-print-grid">
                ${orderCard}
                ${invoiceOrDeliveryCard}
                ${addressCard}
                ${brtCard}
            </div>
        `;
    }

    /**
     * Gestisce il binding delle schede e dei tasti + / - per le copie.
     */
    static bindCardSelection(formElem) {
        const cards = formElem.querySelectorAll(".mp-print-card");

        cards.forEach((card) => {
            card.addEventListener("click", (e) => {
                // Se si interagisce con il selettore copie, non cambiare la scheda a meno che non sia quella attiva
                if (e.target.closest(".mp-copies-stepper")) {
                    return;
                }
                cards.forEach((c) => c.classList.remove("selected"));
                card.classList.add("selected");
            });
        });

        // Stepper Copie
        const btnMinus = formElem.querySelector(".mp-btn-minus");
        const btnPlus = formElem.querySelector(".mp-btn-plus");
        const copiesInput = formElem.querySelector("#mp-print-copies-input");

        if (btnMinus && copiesInput) {
            btnMinus.onclick = (e) => {
                e.stopPropagation();
                let current = parseInt(copiesInput.value || "1", 10);
                if (current > 1) {
                    copiesInput.value = current - 1;
                }
                const addrCard = formElem.querySelector('.mp-print-card[data-value="address"]');
                if (addrCard) {
                    cards.forEach((c) => c.classList.remove("selected"));
                    addrCard.classList.add("selected");
                }
            };
        }

        if (btnPlus && copiesInput) {
            btnPlus.onclick = (e) => {
                e.stopPropagation();
                let current = parseInt(copiesInput.value || "1", 10);
                if (current < 99) {
                    copiesInput.value = current + 1;
                }
                const addrCard = formElem.querySelector('.mp-print-card[data-value="address"]');
                if (addrCard) {
                    cards.forEach((c) => c.classList.remove("selected"));
                    addrCard.classList.add("selected");
                }
            };
        }
    }

    /**
     * Esegue la chiamata AJAX di generazione e visualizzazione del PDF.
     * @param {number|string} idOrder
     * @param {string} documentType
     * @param {number} copies
     */
    static async executePrint(idOrder, documentType = "order", copies = 1) {
        const adminUrl = this.getAdminControllerUrl();
        if (!adminUrl || !idOrder) {
            if (typeof showErrorMessage === "function") {
                showErrorMessage("Impossibile completare la stampa: dati insufficienti.");
            } else {
                alert("Impossibile completare la stampa: dati insufficienti.");
            }
            return;
        }

        let printWindow = null;
        try {
            printWindow = window.open("about:blank", "_blank");
        } catch (e) {
            console.warn("Impossibile aprire nuova scheda in anticipo:", e);
        }

        try {
            const printUrl = `${adminUrl}&ajax=1&action=renderPdfDocument&id_order=${idOrder}&document_type=${documentType}&copies=${copies}`;
            const response = await fetch(printUrl);
            const res = await response.json();

            if (res && res.success) {
                const targetPrinter = (window.mpWebPrintPrinters && window.mpWebPrintPrinters[documentType]) || "";
                const isWebPrintActive = !!(window.mpWebPrintEnable && typeof WebPrint !== "undefined");

                if (isWebPrintActive) {
                    if (!targetPrinter) {
                        if (printWindow && !printWindow.closed) {
                            try { printWindow.close(); } catch(e) {}
                        }
                        const docLabels = {
                            order: 'Ordine',
                            invoice: 'Fattura',
                            delivery: 'Spedizione / Consegna',
                            address: 'Etichetta Indirizzo',
                            brt: 'Segnacollo BRT'
                        };
                        const docLabel = docLabels[documentType] || documentType;
                        const msg = `Impossibile completare l'operazione: la stampa diretta WebPrint è attiva per l'operatore corrente, ma non è stata configurata la stampante per "${docLabel}". Configura la stampante nelle impostazioni del modulo.`;
                        if (typeof showErrorMessage === "function") {
                            showErrorMessage(msg);
                        } else {
                            alert(msg);
                        }
                        return;
                    }

                    if (res.pdf) {
                        if (printWindow && !printWindow.closed) {
                            try { printWindow.close(); } catch(e) {}
                        }
                        const host = window.mpWebPrintHost || "127.0.0.1";
                        const port = window.mpWebPrintPort || "8085";
                        const wp = new WebPrint(true, {
                            relayHost: host,
                            relayPort: port
                        });
                        for (let c = 0; c < copies; c++) {
                            wp.printRaw(res.pdf, targetPrinter);
                        }
                        const msg = `Stampa inviata direttamente a "${targetPrinter}" tramite WebPrint (${copies} copia/e).`;
                        if (typeof showSuccessMessage === "function") {
                            showSuccessMessage(msg);
                        } else {
                            alert(msg);
                        }
                        return;
                    }
                }

                if (typeof showSuccessMessage === "function") {
                    showSuccessMessage(res.message);
                } else if (typeof showNoticeMessage === "function") {
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
                    const blob = new Blob([byteArray], { type: "application/pdf" });
                    const blobUrl = URL.createObjectURL(blob);

                    if (printWindow && !printWindow.closed) {
                        printWindow.location.href = blobUrl;
                    } else {
                        window.open(blobUrl, "_blank");
                    }
                } else if (printWindow && !printWindow.closed) {
                    printWindow.close();
                }
            } else {
                if (printWindow && !printWindow.closed) {
                    printWindow.close();
                }
                const msg = res && res.message ? res.message : "Errore durante la generazione del documento PDF.";
                if (typeof showErrorMessage === "function") {
                    showErrorMessage(msg);
                } else {
                    alert(msg);
                }
            }
        } catch (err) {
            if (printWindow && !printWindow.closed) {
                printWindow.close();
            }
            console.error("Errore durante la richiesta di stampa PDF:", err);
            if (typeof showErrorMessage === "function") {
                showErrorMessage("Errore di comunicazione durante la richiesta di stampa.");
            }
        }
    }

    /**
     * Apre il modale di stampa massiva per una lista di ordini selezionati.
     * @param {Array<number|string>} orderIds
     */
    static async openBatch(orderIds) {
        if (!orderIds || !Array.isArray(orderIds) || orderIds.length === 0) {
            const msg = "Seleziona almeno un ordine per la stampa massiva.";
            if (typeof showErrorMessage === "function") {
                showErrorMessage(msg);
            } else {
                alert(msg);
            }
            return;
        }

        const dialog = this.ensureDialogElement();
        const bodyElem = dialog.querySelector("#mp-print-dialog-body");
        const refBadge = dialog.querySelector("#mp-print-order-ref");
        const formElem = dialog.querySelector("#mp-print-form");

        const headerIcon = dialog.querySelector(".mp-print-header .material-icons");
        if (headerIcon) {
            headerIcon.textContent = "print";
        }

        const headerTitle = dialog.querySelector(".mp-print-header h4 span:nth-child(2)");
        if (headerTitle) {
            headerTitle.textContent = `Stampa massiva (${orderIds.length} ordini)`;
        }

        const confirmBtn = dialog.querySelector("#mp-print-dialog-confirm");
        if (confirmBtn) {
            confirmBtn.innerHTML = '<span class="material-icons" style="font-size:18px;">print</span> STAMPA DOCUMENTI';
        }

        if (refBadge) {
            refBadge.textContent = `#${orderIds.slice(0, 3).join(", #")}${orderIds.length > 3 ? ` (+${orderIds.length - 3})` : ""}`;
        }

        if (bodyElem) {
            bodyElem.innerHTML = `
                <div class="mp-print-grid">
                    <div class="mp-print-card selected" data-value="order" 
                         style="--accent-color:#2563eb; --bg-tint:rgba(37,99,235,0.04); --shadow-tint:rgba(37,99,235,0.15); --icon-bg:#dbeafe;">
                        <div class="mp-card-top">
                            <div class="mp-card-icon-wrap">
                                <span class="material-icons">description</span>
                            </div>
                            <span class="material-icons mp-card-check">check_circle</span>
                        </div>
                        <div>
                            <h5 class="mp-card-title">Ordini</h5>
                            <p class="mp-card-desc">Stampa la scheda dell'ordine per tutti gli ordini selezionati</p>
                        </div>
                    </div>

                    <div class="mp-print-card" data-value="invoice" 
                         style="--accent-color:#059669; --bg-tint:rgba(5,150,105,0.04); --shadow-tint:rgba(5,150,105,0.15); --icon-bg:#d1fae5;">
                        <div class="mp-card-top">
                            <div class="mp-card-icon-wrap">
                                <span class="material-icons">receipt_long</span>
                            </div>
                            <span class="material-icons mp-card-check">check_circle</span>
                        </div>
                        <div>
                            <h5 class="mp-card-title">Fatture</h5>
                            <p class="mp-card-desc">Stampa le fatture fiscali per gli ordini selezionati</p>
                        </div>
                    </div>

                    <div class="mp-print-card" data-value="delivery" 
                         style="--accent-color:#7c3aed; --bg-tint:rgba(124,58,237,0.04); --shadow-tint:rgba(124,58,237,0.15); --icon-bg:#ede9fe;">
                        <div class="mp-card-top">
                            <div class="mp-card-icon-wrap">
                                <span class="material-icons">local_shipping</span>
                            </div>
                            <span class="material-icons mp-card-check">check_circle</span>
                        </div>
                        <div>
                            <h5 class="mp-card-title">Note di Spedizione</h5>
                            <p class="mp-card-desc">Stampa le note di consegna per gli ordini selezionati</p>
                        </div>
                    </div>
                </div>
            `;
        }

        if (typeof dialog.showModal === "function") {
            try {
                if (!dialog.open) dialog.showModal();
            } catch (e) {
                dialog.setAttribute("open", "");
            }
        } else {
            dialog.setAttribute("open", "");
        }

        const closeX = dialog.querySelector("#mp-print-dialog-close-x");
        const cancelBtn = dialog.querySelector("#mp-print-dialog-cancel-btn");
        const handleClose = (e) => {
            if (e) e.preventDefault();
            dialog.close();
        };
        if (closeX) closeX.onclick = handleClose;
        if (cancelBtn) cancelBtn.onclick = handleClose;

        this.bindCardSelection(formElem);

        formElem.onsubmit = (e) => {
            e.preventDefault();
            const selectedCard = formElem.querySelector(".mp-print-card.selected");
            const documentType = selectedCard ? selectedCard.dataset.value : "order";

            dialog.close();
            this.executeBatchPrint(orderIds, documentType);
        };
    }

    /**
     * Esegue la chiamata AJAX di stampa massiva e apre il PDF unito in una nuova scheda.
     * @param {Array<number|string>} orderIds
     * @param {string} documentType
     */
    static async executeBatchPrint(orderIds, documentType = "order") {
        const adminUrl = this.getAdminControllerUrl();
        if (!adminUrl || !orderIds || orderIds.length === 0) {
            const msg = "Impossibile completare la stampa massiva: dati insufficienti.";
            if (typeof showErrorMessage === "function") {
                showErrorMessage(msg);
            } else {
                alert(msg);
            }
            return;
        }

        let printWindow = null;
        try {
            printWindow = window.open("about:blank", "_blank");
            if (printWindow && printWindow.document) {
                try {
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Generazione Stampa Massiva PDF...</title>
                            <style>
                                body { font-family: system-ui, -apple-system, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f8fafc; color: #334155; }
                                .spinner { width: 48px; height: 48px; border: 4px solid #e2e8f0; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 16px; }
                                @keyframes spin { to { transform: rotate(360deg); } }
                                h3 { font-size: 1.1rem; font-weight: 600; margin: 0 0 8px 0; }
                                p { font-size: 0.9rem; color: #64748b; margin: 0; }
                            </style>
                        </head>
                        <body>
                            <div class="spinner"></div>
                            <h3>Generazione stampa massiva PDF in corso...</h3>
                            <p>Attendere il completamento dell'unione dei documenti.</p>
                        </body>
                        </html>
                    `);
                } catch (e) {}
            }
        } catch (e) {
            console.warn("Impossibile aprire nuova scheda in anticipo:", e);
        }

        try {
            const idsParam = orderIds.join(",");
            const printUrl = `${adminUrl}&ajax=1&action=renderBatchPdfDocuments&id_orders=${idsParam}&document_type=${documentType}`;
            const response = await fetch(printUrl);
            const res = await response.json();

            if (res && res.success) {
                const targetPrinter = (window.mpWebPrintPrinters && window.mpWebPrintPrinters[documentType]) || "";
                const isWebPrintActive = !!(window.mpWebPrintEnable && typeof WebPrint !== "undefined");

                if (isWebPrintActive) {
                    if (!targetPrinter) {
                        if (printWindow && !printWindow.closed) {
                            try { printWindow.close(); } catch(e) {}
                        }
                        const docLabels = {
                            order: 'Ordine',
                            invoice: 'Fattura',
                            delivery: 'Spedizione / Consegna',
                            address: 'Etichetta Indirizzo',
                            brt: 'Segnacollo BRT'
                        };
                        const docLabel = docLabels[documentType] || documentType;
                        const msg = `Impossibile completare l'operazione: la stampa diretta WebPrint è attiva per l'operatore corrente, ma non è stata configurata la stampante per "${docLabel}". Configura la stampante nelle impostazioni del modulo.`;
                        if (typeof showErrorMessage === "function") {
                            showErrorMessage(msg);
                        } else {
                            alert(msg);
                        }
                        return;
                    }

                    if (res.pdf) {
                        if (printWindow && !printWindow.closed) {
                            try { printWindow.close(); } catch(e) {}
                        }
                        const host = window.mpWebPrintHost || "127.0.0.1";
                        const port = window.mpWebPrintPort || "8085";
                        const wp = new WebPrint(true, {
                            relayHost: host,
                            relayPort: port
                        });
                        wp.printRaw(res.pdf, targetPrinter);

                        const msg = `Stampa massiva (${orderIds.length} ordini) inviata direttamente a "${targetPrinter}" tramite WebPrint.`;
                        if (typeof showSuccessMessage === "function") {
                            showSuccessMessage(msg);
                        } else {
                            alert(msg);
                        }
                        return;
                    }
                }

                if (typeof showSuccessMessage === "function") {
                    showSuccessMessage(res.message);
                } else if (typeof showNoticeMessage === "function") {
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
                    const blob = new Blob([byteArray], { type: "application/pdf" });
                    const blobUrl = URL.createObjectURL(blob);

                    if (printWindow && !printWindow.closed) {
                        printWindow.location.href = blobUrl;
                    } else {
                        window.open(blobUrl, "_blank");
                    }
                } else if (printWindow && !printWindow.closed) {
                    try {
                        printWindow.close();
                    } catch (e) {}
                }
            } else {
                if (printWindow && !printWindow.closed) {
                    try {
                        printWindow.close();
                    } catch (e) {}
                }
                const msg = res && res.message ? res.message : "Errore durante la generazione della stampa massiva PDF.";
                if (typeof showErrorMessage === "function") {
                    showErrorMessage(msg);
                } else {
                    alert(msg);
                }
            }
        } catch (err) {
            if (printWindow && !printWindow.closed) {
                try {
                    printWindow.close();
                } catch (e) {}
            }
            console.error("Errore durante la richiesta di stampa massiva PDF:", err);
            if (typeof showErrorMessage === "function") {
                showErrorMessage("Errore di comunicazione durante la richiesta di stampa massiva.");
            }
        }
    }

    /**
     * Inietta il buttongroup con i bottoni separati per la stampa diretta nel div .order-actions della pagina dell'ordine.
     */
    static async initSeparatePrintButtons() {
        if (typeof window.mpShowSeparatePrintButtons !== "undefined" && !window.mpShowSeparatePrintButtons) {
            return;
        }

        const actionsContainer = document.querySelector(".order-actions");
        if (!actionsContainer) {
            return;
        }

        if (document.querySelector(".mp-separate-print-btn-group")) {
            return;
        }

        let idOrder = null;
        const match = window.location.pathname.match(/orders\/(\d+)/) || window.location.search.match(/id_order=(\d+)/);
        if (match) {
            idOrder = match[1];
        }

        if (!idOrder) {
            const el = document.querySelector("[data-order-id]");
            if (el) {
                idOrder = el.dataset.orderId || el.getAttribute("data-order-id");
            }
        }

        if (!idOrder) {
            return;
        }

        let orderInfo = {
            has_invoice: false,
            has_delivery: false,
        };

        try {
            const adminUrl = this.getAdminControllerUrl();
            const response = await fetch(`${adminUrl}&ajax=1&action=getOrderPrintInfo&id_order=${idOrder}`);
            const res = await response.json();
            if (res && res.success) {
                orderInfo = res;
            }
        } catch (e) {
            console.warn("Impossibile verificare disponibilità documenti ordine:", e);
        }

        const hasDomInvoice = !!document.querySelector('[data-role="view-invoice"], a[href*="generateInvoicePDF"], a[href*="generateInvoice"], .btn-print-invoice');
        const hasDomDelivery = !!document.querySelector('[data-role="view-delivery-slip"], a[href*="generateDeliverySlipPDF"], a[href*="generateDelivery"], .btn-print-delivery');

        const showInvoice = orderInfo.has_invoice || hasDomInvoice;
        const showDelivery = orderInfo.has_delivery || hasDomDelivery;

        if (document.querySelector(".mp-separate-print-btn-group")) {
            return;
        }

        const btnGroup = document.createElement("div");
        btnGroup.className = "btn-group mp-separate-print-btn-group ml-2 margin-left-2";
        btnGroup.setAttribute("role", "group");
        btnGroup.setAttribute("aria-label", "Stampa separata documenti");

        let html = `
            <button type="button" class="btn btn-outline-secondary js-mp-direct-print" data-doc-type="order" data-order-id="${idOrder}" title="Stampa Ordine">
                <i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;color:#2563eb;">description</i> Ordine
            </button>
        `;

        if (showInvoice) {
            html += `
                <button type="button" class="btn btn-outline-secondary js-mp-direct-print" data-doc-type="invoice" data-order-id="${idOrder}" title="Stampa Fattura">
                    <i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;color:#059669;">receipt_long</i> Fattura
                </button>
            `;
        }

        if (showDelivery) {
            html += `
                <button type="button" class="btn btn-outline-secondary js-mp-direct-print" data-doc-type="delivery" data-order-id="${idOrder}" title="Stampa Spedizione">
                    <i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;color:#7c3aed;">print</i> Spedizione
                </button>
            `;
        }

        html += `
            <button type="button" class="btn btn-outline-secondary js-mp-direct-print" data-doc-type="address" data-order-id="${idOrder}" title="Stampa Indirizzo">
                <i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:3px;color:#d97706;">print</i>
                <span class="d-none d-md-inline">Indirizzo</span>
                <input type="number" name="copies" id="mp-print-copies-input-address" value="1" min="1" max="99" class="mp-copies-input">
            </button>
        `;

        btnGroup.innerHTML = html;
        actionsContainer.prepend(btnGroup);

        btnGroup.querySelectorAll(".js-mp-direct-print").forEach((btn) => {
            btn.addEventListener("click", (e) => {
                if (e.target.closest("#mp-print-copies-input-address, .mp-copies-input, .mp-copies-stepper")) {
                    e.stopPropagation();
                    return;
                }
                e.preventDefault();
                const type = btn.getAttribute("data-doc-type");
                const oid = btn.getAttribute("data-order-id") || idOrder;
                let copies = 1;
                const copiesInput = btn.querySelector(".mp-copies-input") || btnGroup.querySelector("#mp-print-copies-input-address");
                if (copiesInput) {
                    copies = parseInt(copiesInput.value || "1", 10) || 1;
                }
                MpPrintDialog.executePrint(oid, type, copies);
            });
        });

        btnGroup.querySelectorAll(".mp-copies-input").forEach((input) => {
            input.addEventListener("click", (e) => e.stopPropagation());
            input.addEventListener("mousedown", (e) => e.stopPropagation());
            input.addEventListener("change", (e) => e.stopPropagation());
            input.addEventListener("input", (e) => e.stopPropagation());
            input.addEventListener("keydown", (e) => e.stopPropagation());
        });
    }
}

if (typeof window !== "undefined") {
    window.MpPrintDialog = MpPrintDialog;
}

if (typeof document !== "undefined") {
    const hideNativePrintButtons = () => {
        document.querySelectorAll('.js-print-order-view-page, [data-role="view-invoice"], [data-role="view-delivery-slip"]').forEach((el) => {
            el.style.setProperty("display", "none", "important");
        });
    };
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => {
            hideNativePrintButtons();
            MpPrintDialog.initSeparatePrintButtons();
        });
    } else {
        hideNativePrintButtons();
        MpPrintDialog.initSeparatePrintButtons();
    }
    setTimeout(hideNativePrintButtons, 300);
    setTimeout(hideNativePrintButtons, 1000);
    setTimeout(() => MpPrintDialog.initSeparatePrintButtons(), 300);
    setTimeout(() => MpPrintDialog.initSeparatePrintButtons(), 1200);

    document.addEventListener("click", (e) => {
        const target = e.target.closest(".js-order-action-label, .js-order-action-print");
        if (target) {
            e.preventDefault();
            const orderId = target.dataset.orderId || target.getAttribute("data-order-id");
            if (orderId) {
                MpPrintDialog.open(orderId);
            }
        }
    });
}

// Auto-teleport #growls inside active HTML5 Top Layer <dialog open>
if (typeof MutationObserver !== "undefined" && typeof document !== "undefined") {
    const teleportGrowlsToDialog = () => {
        const activeDialog = document.querySelector("dialog[open]");
        const growls = document.getElementById("growls") || document.querySelector(".growl")?.parentElement;
        if (activeDialog && growls && growls.parentElement !== activeDialog) {
            activeDialog.appendChild(growls);
        }
    };

    const growlObserver = new MutationObserver(teleportGrowlsToDialog);
    const startObserver = () => {
        if (document.body) {
            growlObserver.observe(document.body, { childList: true, subtree: true });
            teleportGrowlsToDialog();
        }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", startObserver);
    } else {
        startObserver();
    }
}
