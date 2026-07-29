class AdminTableOrders {
    constructor(tableId, adminControllerUrl, orderPageLink, customerPageLink, orderStates, orderFlagItems, orderCountries, invoicePdfLink, deliveryPdfLink, labelPrintEndpoint) {
        this.table = document.getElementById(tableId);
        this.adminControllerUrl = adminControllerUrl;
        this.orderPageLink = orderPageLink;
        this.customerPageLink = customerPageLink;
        this.orderStates = orderStates || {};
        this.orderFlags = (orderFlagItems || []).reduce((items, item) => {
            items[item.id_order_flag_item] = item;
            return items;
        }, {});
        this.orderFlagFilterOptions = Object.values(this.orderFlags).reduce((options, item) => {
            options[item.id_order_flag_item] = item.name;
            return options;
        }, {});
        this.orderCountries = orderCountries || {};
        this.invoicePdfLink = invoicePdfLink;
        this.deliveryPdfLink = deliveryPdfLink;
        this.labelPrintEndpoint = labelPrintEndpoint;
        this.limit = 25;
        this.offset = 0;
        this.sort = "id_order";
        this.order = "desc";
        this.filters = {};
        this.filterableFields = ["id_order", "order_flag_item", "delivery_country", "reference", "email", "customer", "id_eurosolution", "total_paid_tax_incl", "payment", "status", "date_add"];
        this.excludedStates = this.getExcludedStates();
        this.currentAction = null;
        this.currentOrderId = null;
        this.dialog = null;
        this.dialogBound = false;

        if (!this.table) {
            return;
        }

        this.initTable();
        this.bindExcludedStates();
        this.load();
        window.adminTableOrdersInstance = this;
    }

    initTable() {
        $(this.table).bootstrapTable({
            filterControl: true,
            filterControlVisible: true,
            filterControlSearchClear: true,
            showFilterControlSwitch: true,
            searchOnEnterKey: true,
            sidePagination: "server",
            pagination: true,
            showRefresh: true,
            pageSize: this.limit,
            pageList: [10, 25, 50, 100],
            locale: "it-IT",
            classes: "table table-bordered table-hover",
            theadClasses: "thead-light",
            sortName: this.sort,
            sortOrder: this.order,
            uniqueId: "id_order",
            onPostBody: () => {
                fixBootstrapTable(this.table.id);
                setTimeout(() => {
                    this.restoreFilterValues();
                    this.renderSearchActions();
                    this.bindRowActions();
                }, 0);
            },
            columns: [
                { field: "id_order", title: "ID", sortable: true, align: "center", filterControl: "input" },
                { field: "order_flag_item", title: "Semaforo", align: "center", filterControl: "select", filterData: `json:${JSON.stringify(this.orderFlagFilterOptions)}`, formatter: (value) => this.formatOrderFlag(value) },
                { field: "delivery_country", title: "Consegna", align: "center", filterControl: "select", filterData: `json:${JSON.stringify(this.orderCountries)}`, formatter: (value) => this.orderCountries[value] || "--" },
                { field: "reference", title: "Riferimento", sortable: true, filterControl: "input" },
                { field: "email", title: "Email", sortable: true, filterControl: "input" },
                { field: "customer", title: "Cliente", sortable: true, filterControl: "input", formatter: (value, row) => this.formatCustomerColumn(value, row) },
                {
                    field: "id_eurosolution",
                    title: "Eurosolution",
                    sortable: true,
                    align: "center",
                    filterControl: "input",
                    formatter: (value) => (value ? `<span class="badge badge-info">${this.escape(value)}</span>` : "--"),
                },
                {
                    field: "total_paid_tax_incl",
                    title: "Totale",
                    sortable: true,
                    align: "right",
                    filterControl: "input",
                    formatter: (value, row) => this.formatTotalColumn(value, row),
                },
                { field: "payment", title: "Pagamento", sortable: true, filterControl: "input" },
                { field: "status", title: "Stato", sortable: true, filterControl: "select", filterData: `json:${JSON.stringify(this.orderStates)}` },
                { field: "notes", title: "Note", align: "center", formatter: (value, row) => this.formatNotes(row) },
                { field: "date_add", title: "Data", sortable: true, align: "center", filterControl: "input", filterControlPlaceholder: "Da - A (GG/MM/AAAA)" },
                {
                    field: "actions",
                    title: "Azioni",
                    align: "center",
                    formatter: (value, row) => {
                        const orderUrl = this.orderPageLink.replace("999999999", row.id_order);
                        const actionButtonStyle = "display:flex;align-items:center;justify-content:center;box-sizing:border-box;width:34px;height:34px;padding:0;margin:0;";
                        return `<div role="group" style="display:grid;grid-template-columns:repeat(2, 34px);grid-template-rows:repeat(2, 34px);gap:3px;width:71px;">
                            <a class="btn btn-default" style="${actionButtonStyle}" href="${orderUrl}" target="_blank" title="Vedi ordine"><span class="material-icons">visibility</span></a>
                            <button class="btn btn-default js-order-action-print" style="${actionButtonStyle}" type="button" data-order-id="${row.id_order}" title="Stampa"><span class="material-icons">print</span></button>
                            <button class="btn btn-default js-order-action-export" style="${actionButtonStyle}" type="button" data-order-id="${row.id_order}" title="Esporta"><span class="material-icons">file_download</span></button>
                            <button class="btn btn-default js-order-action-label" style="${actionButtonStyle}" type="button" data-order-id="${row.id_order}" title="Etichette"><span class="material-icons">label</span></button>
                        </div>`;
                    },
                },
            ],
        });

        $(this.table).on("page-change.bs.table", (event, page, pageSize) => {
            this.limit = pageSize;
            this.offset = (page - 1) * pageSize;
            this.load();
        });
        $(this.table).on("sort.bs.table", (event, name, order) => {
            this.sort = name;
            this.order = order;
            this.offset = 0;
            this.load();
        });
        $(this.table).on("column-search.bs.table", () => {});
        $(this.table).on("refresh.bs.table", () => this.load());
    }

    async load() {
        const payload = new URLSearchParams({
            ajax: "1",
            action: "renderOrdersData",
            limit: String(this.limit),
            offset: String(this.offset),
            sort: this.sort,
            order: this.order,
            filter: JSON.stringify(this.filters),
            excludedStates: JSON.stringify(this.excludedStates),
        });

        try {
            const response = await fetch(this.adminControllerUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body: payload,
            });
            const data = await response.json();

            if (!response.ok || !Array.isArray(data.rows)) {
                throw new Error("Risposta non valida");
            }

            $(this.table).bootstrapTable("load", data);
            this.updateStatistics(data.statistics);
        } catch (error) {
            $(this.table).bootstrapTable("load", { total: 0, rows: [] });
            console.error("Errore caricamento ordini:", error);
        }
    }

    formatOrderFlag(value) {
        const flag = this.orderFlags[value];
        if (!flag) {
            return "--";
        }

        const color = /^#[0-9a-f]{3,8}$/i.test(flag.color || "") ? flag.color : "#6c757d";
        return `<span class="material-icons" title="${this.escape(flag.name || "")}" style="color:${color};font-size:20px;vertical-align:middle;">${this.escape(flag.icon || "flag")}</span>`;
    }

    formatCustomerColumn(value, row) {
        const customerName = this.escape(value || '--');
        let customerHtml = customerName;

        if (row && row.id_customer && this.customerPageLink) {
            const custUrl = this.customerPageLink.replace('999999999', row.id_customer);
            customerHtml = `<a href="${custUrl}" target="_blank" style="font-weight:600;color:#007bff;text-decoration:none;" title="Vedi scheda cliente">${customerName}</a>`;
        }

        const isInvoiceRequested = row && (
            parseInt(row.invoice_requested) === 1
            || (row.vat_number && String(row.vat_number).trim() !== '')
            || (row.dni && String(row.dni).trim() !== '')
        );

        if (isInvoiceRequested) {
            customerHtml += `<br><span class="badge badge-warning" style="margin-top:3px;display:inline-block;">Richiede Fattura</span>`;
        }

        return customerHtml;
    }

    formatTotalColumn(value, row) {
        const feeAmount = Number(row ? row.payment_fee_amount : 0) || 0;
        const baseTotal = Number(row && row.order_base_total !== undefined ? row.order_base_total : value || 0);
        const realTotal = Number(row && row.real_total !== undefined ? row.real_total : baseTotal + feeAmount);

        const formatCurrency = (val) => val.toLocaleString("it-IT", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " €";

        if (feeAmount <= 0) {
            return `<div style="text-align:right;font-weight:600;font-size:13px;color:#2c3e50;">${formatCurrency(baseTotal)}</div>`;
        }

        return `<div style="display:flex;flex-direction:column;min-width:145px;font-size:12px;line-height:1.4;">
            <div style="display:flex;justify-content:space-between;align-items:center;color:#6c757d;font-size:11px;">
                <span>Ordine</span>
                <span>${formatCurrency(baseTotal)}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;color:#c0392b;font-size:11px;margin-top:1px;">
                <span>Commissioni</span>
                <span>+ ${formatCurrency(feeAmount)}</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:13px;color:#2e7d32;border-top:1px dashed #cccccc;margin-top:3px;padding-top:3px;">
                <span>Totale</span>
                <span>${formatCurrency(realTotal)}</span>
            </div>
        </div>`;
    }

    formatNotes(row) {
        const customer = Number(row.notes_customer || 0);
        const order = Number(row.notes_order || 0);
        const embroidery = Number(row.notes_embroidery || 0);
        if (!customer && !order && !embroidery) {
            return "--";
        }

        return `<div style="display:flex;flex-direction:column;gap:4px;min-width:76px;">
            <span class="text-info d-flex align-items-center justify-content-between" title="Note cliente"><i class="material-icons">person</i><span class="badge badge-info">${customer}</span></span>
            <span class="text-success d-flex align-items-center justify-content-between" title="Note ordine"><i class="material-icons">shopping_cart</i><span class="badge badge-success">${order}</span></span>
            <span class="text-danger d-flex align-items-center justify-content-between" title="Note ricamo"><i class="material-icons">content_cut</i><span class="badge badge-danger">${embroidery}</span></span>
        </div>`;
    }

    bindRowActions() {
        $(this.table)
            .off("click.orderPrint", ".js-order-action-print")
            .off("click.orderExport", ".js-order-action-export")
            .off("click.orderLabel", ".js-order-action-label")
            .on("click.orderPrint", ".js-order-action-print", (event) => {
                event.preventDefault();
                this.openActionDialog("printOrder", Number(event.currentTarget.dataset.orderId));
            })
            .on("click.orderExport", ".js-order-action-export", (event) => {
                event.preventDefault();
                this.openActionDialog("exportOrder", Number(event.currentTarget.dataset.orderId));
            })
            .on("click.orderLabel", ".js-order-action-label", (event) => {
                event.preventDefault();
                this.openActionDialog("printLabel", Number(event.currentTarget.dataset.orderId));
            });
    }

    getDialog() {
        if (this.dialog) {
            return this.dialog;
        }

        this.dialog = document.getElementById("order-action-dialog");
        if (!this.dialog) {
            console.error("Elemento <dialog> #order-action-dialog non trovato");
            return null;
        }

        const form = this.dialog.querySelector("form");
        if (form && !this.dialogBound) {
            form.addEventListener("submit", (event) => this.handleDialogSubmit(event));
            const cancelBtn = this.dialog.querySelector("#order-action-dialog-cancel");
            if (cancelBtn) {
                cancelBtn.addEventListener("click", () => this.dialog.close());
            }
            this.dialogBound = true;
        }

        return this.dialog;
    }

    openActionDialog(action, orderId) {
        const dialog = this.getDialog();
        if (!dialog) {
            return;
        }

        this.currentAction = action;
        this.currentOrderId = orderId;

        const titles = {
            printOrder: "Stampa documento",
            exportOrder: "Esporta documento",
            printLabel: "Stampa etichette",
        };

        dialog.querySelector("#order-action-dialog-title").textContent = titles[action] || "Azione";
        dialog.querySelector("#order-action-dialog-body").innerHTML = this.buildDialogBody(action);
        dialog.showModal();
    }

    buildDialogBody(action) {
        if (action === "printOrder" || action === "exportOrder") {
            const verb = action === "printOrder" ? "Stampa" : "Esporta";
            return `
                <div class="dialog-option">
                    <label><input type="radio" name="document" value="order" checked> ${verb} Ordine</label>
                </div>
                <div class="dialog-option">
                    <label><input type="radio" name="document" value="invoice"> ${verb} Fattura</label>
                </div>
                <div class="dialog-option">
                    <label><input type="radio" name="document" value="sales_note"> ${verb} Nota vendita</label>
                </div>
            `;
        }

        if (action === "printLabel") {
            return `
                <div class="dialog-option">
                    <label><input type="radio" name="labelType" value="address" checked> Etichette indirizzi</label>
                    <div class="dialog-suboption">
                        <label>Copie <input type="number" name="copies" value="1" min="1" max="99" style="width:60px;"></label>
                    </div>
                </div>
                <div class="dialog-option">
                    <label><input type="radio" name="labelType" value="brt"> Segnacollo Bartolini</label>
                </div>
            `;
        }

        return "";
    }

    getPrintUrl(documentType, orderId) {
        let baseLink = "";
        let targetDoc = "";

        if (documentType === "invoice") {
            baseLink = this.invoicePdfLink;
            targetDoc = "generate-invoice-pdf";
        } else if (documentType === "sales_note") {
            baseLink = this.deliveryPdfLink;
            targetDoc = "generate-delivery-slip-pdf";
        } else {
            baseLink = this.invoicePdfLink;
            targetDoc = "generate-order-pdf";
        }

        if (!baseLink) {
            return "";
        }

        let url = baseLink.replace("999999999", String(orderId));
        url = url.replace(/generate-(invoice|delivery-slip|order)-pdf/, targetDoc);

        return url;
    }

    async handleDialogSubmit(event) {
        event.preventDefault();

        const dialog = this.getDialog();
        if (!dialog) {
            return;
        }

        const form = event.currentTarget;
        const formData = new FormData(form);
        const action = this.currentAction;
        const orderId = this.currentOrderId;

        dialog.close();

        if (action === "printOrder") {
            const documentType = formData.get("document") || "order";
            const url = this.getPrintUrl(documentType, orderId);
            if (url) {
                window.open(url, "_blank");
            }
            return;
        }

        if (action === "exportOrder") {
            const documentType = formData.get("document") || "order";
            if (window.AdminOrderExportHelper) {
                window.AdminOrderExportHelper.exportDocument(orderId, documentType);
            } else {
                const url = `${this.adminControllerUrl}&action=showCustomExportPage&id_order=${orderId}&document_type=${documentType}`;
                window.open(url, "_blank");
            }
            return;
        }

        const payload = new URLSearchParams({ ajax: "1", action, id_order: String(orderId) });
        if (action === "printLabel") {
            payload.set("labelType", formData.get("labelType") || "address");
            payload.set("copies", formData.get("copies") || "1");
        } else {
            payload.set("document", formData.get("document") || "order");
        }

        try {
            const response = await fetch(this.adminControllerUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body: payload,
            });
            const data = await response.json();

            if (!response.ok || !data || data.success === false) {
                throw new Error(data.message || "Risposta non valida");
            }

            console.log(`Risposta ${action}:`, data);
            window.alert(data.message || "Operazione simulata completata");
        } catch (error) {
            console.error(`Errore ${action}:`, error);
            window.alert("Si è verificato un errore nella richiesta.");
        }
    }

    renderSearchActions() {
        const $columns = $(this.table).closest(".bootstrap-table").find(".fixed-table-toolbar .columns");

        if ($columns.find("[data-orders-search]").length) {
            return;
        }

        $("<button>", {
            type: "button",
            class: "btn btn-info",
            "data-orders-search": "",
            html: '<i class="material-icons">search</i> Cerca',
        })
            .on("click", () => this.applyFilters())
            .appendTo($columns);

        $("<button>", {
            type: "button",
            class: "btn btn-warning",
            "data-orders-reset": "",
            html: '<i class="material-icons">refresh</i> Reset',
        })
            .on("click", () => this.resetFilters())
            .appendTo($columns);
    }

    applyFilters() {
        this.filters = this.getCurrentFilters();
        this.offset = 0;
        this.load();
    }

    resetFilters() {
        $(this.table).bootstrapTable("clearFilterControl");
        this.filters = {};
        this.offset = 0;
        this.load();
    }

    restoreFilterValues() {
        const $wrapper = $(this.table).closest(".bootstrap-table");

        this.filterableFields.forEach((field) => {
            $wrapper.find(`.bootstrap-table-filter-control-${field}`).val(this.filters[field] || "");
        });
    }

    getCurrentFilters() {
        const $wrapper = $(this.table).closest(".bootstrap-table");
        const filters = {};

        this.filterableFields.forEach((field) => {
            const value = $wrapper.find(`.bootstrap-table-filter-control-${field}`).first().val();
            if (value !== undefined && value !== "") {
                filters[field] = value;
            }
        });

        return filters;
    }

    bindExcludedStates() {
        const $excludedStates = $("#order-excluded-states");

        if ($.fn.chosen) {
            $excludedStates.chosen({ disable_search_threshold: 10, search_contains: true, width: "100%" });
        }

        $excludedStates.on("change", () => {
            this.excludedStates = this.getExcludedStates();
            this.load();
        });
    }

    getExcludedStates() {
        return ($("#order-excluded-states").val() || []).map((value) => Number(value));
    }

    updateStatistics(statistics) {
        if (!statistics) {
            return;
        }

        $("#orders-archive-total").text(this.formatCurrency(statistics.archive.total_paid_tax_incl));
        $("#orders-archive-count").text(Number(statistics.archive.count || 0).toLocaleString("it-IT"));
        $("#orders-filtered-total").text(this.formatCurrency(statistics.filtered.total_paid_tax_incl));
        $("#orders-filtered-count").text(Number(statistics.filtered.count || 0).toLocaleString("it-IT"));
    }

    formatCurrency(value) {
        return Number(value || 0).toLocaleString("it-IT", { style: "currency", currency: "EUR" });
    }

    escape(value) {
        const element = document.createElement("span");
        element.textContent = value;
        return element.innerHTML;
    }
}
