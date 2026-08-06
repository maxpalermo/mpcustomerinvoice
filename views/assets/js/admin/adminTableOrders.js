class AdminTableOrders {
    constructor(tableId, adminControllerUrl, orderPageLink, customerPageLink, orderStates, orderFlagItems, orderCountries, invoicePdfLink, deliveryPdfLink, labelPrintEndpoint, orderStateColors, notesHoverDelaySec) {
        this.table = document.getElementById(tableId);
        this.adminControllerUrl = adminControllerUrl;
        this.orderPageLink = orderPageLink;
        this.customerPageLink = customerPageLink;
        this.orderStates = orderStates || {};
        this.orderStateColors = orderStateColors || {};
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
        this.brtShipmentsUrl = (typeof orderTableLinks !== 'undefined' && orderTableLinks.brtShipmentsUrl) ? orderTableLinks.brtShipmentsUrl : '';
        const showCustomizationsEl = document.getElementById("show-customizations-column");
        this.showCustomizationsColumn = showCustomizationsEl ? Boolean(Number(JSON.parse(showCustomizationsEl.textContent))) : false;

        const delayEl = document.getElementById("notes-hover-delay");
        const delaySec = notesHoverDelaySec !== undefined ? Number(notesHoverDelaySec) : (delayEl ? Number(JSON.parse(delayEl.textContent)) : 1);
        this.notesHoverDelay = (delaySec > 0 ? delaySec : 1) * 1000;

        this.limit = 25;
        this.offset = 0;
        this.sort = "id_order";
        this.order = "desc";
        this.filters = {};
        this.filterableFields = ["id_order", "order_flag_item", "delivery_country", "reference", "email", "customer", "id_eurosolution", "total_paid_tax_incl", "payment", "status", "date_add"];
        if (this.showCustomizationsColumn) {
            this.filterableFields.splice(1, 0, "has_customization");
        }
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
        const columns = [
            { checkbox: true, align: "center", valign: "middle" },
            { field: "id_order", title: "ID", sortable: true, align: "center", filterControl: "input" },
        ];

        if (this.showCustomizationsColumn) {
            columns.push({
                field: "has_customization",
                title: "Custom",
                sortable: true,
                align: "center",
                filterControl: "select",
                filterData: `json:{"": "-- Tutti --", "1": "Con personalizzazioni", "0": "Senza personalizzazioni"}`,
                formatter: (value) => (Number(value) === 1 ? '<span class="material-icons text-success" title="Contiene prodotti con personalizzazione" style="font-size:20px;vertical-align:middle;">check_circle</span>' : '--'),
            });
        }

        columns.push(
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
            { field: "status", title: "Stato", sortable: true, filterControl: "select", filterData: `json:${JSON.stringify(this.orderStates)}`, formatter: (value, row) => this.formatStatusColumn(value, row) },
            { field: "notes", title: "Note", align: "center", formatter: (value, row) => this.formatNotes(row) },
            { field: "date_add", title: "Data", sortable: true, align: "center", filterControl: "input", filterControlPlaceholder: "Da - A (GG/MM/AAAA)" },
            {
                field: "actions",
                title: "Azioni",
                align: "center",
                formatter: (value, row) => {
                    const orderUrl = this.orderPageLink.replace("999999999", row.id_order);
                    const actionButtonStyle = "display:flex;align-items:center;justify-content:center;box-sizing:border-box;width:34px;height:34px;padding:0;margin:0;";
                    return `<div role="group" style="display:flex;flex-direction:row;gap:3px;align-items:center;justify-content:center;">
                        <a class="btn btn-default" style="${actionButtonStyle}" href="${orderUrl}" target="_blank" title="Vedi ordine"><span class="material-icons">visibility</span></a>
                        <button class="btn btn-default js-order-action-print" style="${actionButtonStyle}" type="button" data-order-id="${row.id_order}" title="Stampa"><span class="material-icons">print</span></button>
                        <button class="btn btn-default js-order-action-export" style="${actionButtonStyle}" type="button" data-order-id="${row.id_order}" title="Esporta"><span class="material-icons">file_download</span></button>
                    </div>`;
                },
            }
        );

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
                    this.bindNotesHover();
                }, 0);
            },
            columns: columns,
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

    getContrastingTextColor(hexColor) {
        if (!hexColor || typeof hexColor !== "string") {
            return "#ffffff";
        }
        let hex = hexColor.replace("#", "").trim();
        if (hex.length === 3) {
            hex = hex.split("").map((c) => c + c).join("");
        }
        if (hex.length !== 6) {
            return "#ffffff";
        }
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);

        const yiq = (r * 299 + g * 587 + b * 114) / 1000;
        return yiq >= 128 ? "#1e293b" : "#ffffff";
    }

    formatStatusColumn(value, row) {
        if (!value) {
            return "--";
        }
        const color = (row && row.status_color) || (row && this.orderStateColors && this.orderStateColors[row.current_state]) || "#6c757d";
        const textColor = this.getContrastingTextColor(color);

        return `<span class="badge" style="background-color:${this.escape(color)}!important;color:${textColor}!important;font-weight:600;font-size:0.75rem;padding:0.45em 0.75em;border-radius:4px;display:inline-block;white-space:normal;text-align:center;">${this.escape(value)}</span>`;
    }

    formatCustomerColumn(value, row) {
        const customerName = this.escape(value || '--');
        let customerHtml = customerName;

        if (row && row.id_customer && this.customerPageLink) {
            const custUrl = this.customerPageLink.replace('999999999', row.id_customer);
            customerHtml = `<a href="${custUrl}" target="_blank" style="font-weight:600;color:#007bff;text-decoration:none;" title="(${row.id_customer}) Vedi scheda cliente">${customerName}</a>`;
        }

        const isInvoiceRequested = row && (
            parseInt(row.invoice_requested) === 1
            || (row.vat_number && String(row.vat_number).trim() !== '')
            || (row.dni && String(row.dni).trim() !== '')
        );

        if (isInvoiceRequested) {
            customerHtml += `<br><span class="badge badge-warning" style="margin-top:3px;display:inline-block;">Richiede Fattura</span>`;
        }

        if (row && (row.is_invalid_document || Boolean(row.is_invalid_document))) {
            customerHtml += `<br><span class="badge badge-danger" style="margin-top:3px;display:inline-block;" title="Il documento generato non corrisponde alla richiesta del cliente">Documento errato</span>`;
        }

        // Render Shadcn Document Icons Bar (nessuno, fattura, nota di vendita, segnacollo brt)
        const hasInvoice = row && (parseInt(row.has_invoice) === 1 || parseInt(row.invoice_number) > 0);
        const hasDelivery = row && (parseInt(row.has_delivery) === 1 || parseInt(row.delivery_number) > 0);
        const hasBrt = row && parseInt(row.has_brt) === 1;

        let docIconsHtml = '';
        if (!hasInvoice && !hasDelivery && !hasBrt) {
            docIconsHtml = `<span class="mp-doc-badge mp-doc-none" title="Nessun documento creato" style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:6px;background:#f1f5f9;color:#64748b;border:1px solid #cbd5e1;cursor:pointer;" data-toggle="tooltip"><i class="material-icons" style="font-size:15px;">do_not_disturb_on</i></span>`;
        } else {
            if (hasInvoice) {
                const invText = row.invoice_number ? ` (N. ${row.invoice_number})` : '';
                docIconsHtml += `<span class="mp-doc-badge mp-doc-invoice" title="Fattura creata${invText}" style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:6px;background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;cursor:pointer;" data-toggle="tooltip"><i class="material-icons" style="font-size:15px;">receipt_long</i></span>`;
            }
            if (hasDelivery) {
                const delText = row.delivery_number ? ` (N. ${row.delivery_number})` : '';
                docIconsHtml += `<span class="mp-doc-badge mp-doc-delivery" title="Nota di vendita creata${delText}" style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:6px;background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;cursor:pointer;" data-toggle="tooltip"><i class="material-icons" style="font-size:15px;">article</i></span>`;
            }
            if (hasBrt) {
                docIconsHtml += `<span class="mp-doc-badge mp-doc-brt" title="Segnacollo BRT creato" style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:6px;background:#fef2f2;color:#b91c1c;border:1px solid #fca5a5;cursor:pointer;" data-toggle="tooltip"><i class="material-icons" style="font-size:15px;">local_shipping</i></span>`;
            }
        }

        customerHtml += `<div class="mp-doc-icons-bar" style="display:flex;align-items:center;gap:4px;margin-top:4px;">${docIconsHtml}</div>`;

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

        return `<div class="js-notes-hover-trigger" data-order-id="${row.id_order}" data-customer-id="${row.id_customer || 0}" style="display:flex;flex-direction:column;gap:4px;min-width:76px;">
            <span class="text-info d-flex align-items-center justify-content-between" title="Note cliente"><i class="material-icons">person</i><span class="badge badge-info">${customer}</span></span>
            <span class="text-success d-flex align-items-center justify-content-between" title="Note ordine"><i class="material-icons">shopping_cart</i><span class="badge badge-success">${order}</span></span>
            <span class="text-danger d-flex align-items-center justify-content-between" title="Note ricamo"><i class="material-icons">content_cut</i><span class="badge badge-danger">${embroidery}</span></span>
            <div class="mp-notes-hover-progress"></div>
        </div>`;
    }

    bindNotesHover() {
        const $table = $(this.table);
        $table.off("mouseenter.notesHover mouseleave.notesHover click.notesHover", ".js-notes-hover-trigger");

        let hoverTimer = null;
        let activeTrigger = null;

        const closePopover = () => {
            if (this.currentNotesPopover) {
                this.currentNotesPopover.remove();
                this.currentNotesPopover = null;
            }
            if (activeTrigger) {
                $(activeTrigger).find(".mp-notes-hover-progress").css({ transition: "none", width: "0%" });
                activeTrigger = null;
            }
        };

        $(document).off("click.shadcnNotesClose").on("click.shadcnNotesClose", (e) => {
            if (this.currentNotesPopover && !$(e.target).closest(".mp-shadcn-popover, .js-notes-hover-trigger").length) {
                closePopover();
            }
        });

        $table.on("mouseenter.notesHover", ".js-notes-hover-trigger", (event) => {
            const trigger = event.currentTarget;
            const $trigger = $(trigger);
            const orderId = $trigger.data("order-id") || $trigger.attr("data-order-id");
            const customerId = $trigger.data("customer-id") || $trigger.attr("data-customer-id");

            if (!orderId) return;

            activeTrigger = trigger;
            const delayMs = this.notesHoverDelay || 1000;
            const delaySec = delayMs / 1000;

            const $progress = $trigger.find(".mp-notes-hover-progress");
            $progress.css({ transition: "none", width: "0%" });
            void $progress[0].offsetHeight;
            $progress.css({ transition: `width ${delaySec}s linear`, width: "100%" });

            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(() => {
                this.fetchAndShowNotesPopover(orderId, customerId, trigger);
            }, delayMs);
        });

        $table.on("mouseleave.notesHover", ".js-notes-hover-trigger", (event) => {
            clearTimeout(hoverTimer);
            const trigger = event.currentTarget;
            $(trigger).find(".mp-notes-hover-progress").css({ transition: "none", width: "0%" });

            setTimeout(() => {
                if (this.currentNotesPopover && !this.currentNotesPopover.matches(":hover") && !$(trigger).is(":hover")) {
                    closePopover();
                }
            }, 400);
        });

        $table.on("click.notesHover", ".js-notes-hover-trigger", (event) => {
            event.stopPropagation();
            const trigger = event.currentTarget;
            const $trigger = $(trigger);
            const orderId = $trigger.data("order-id") || $trigger.attr("data-order-id");
            const customerId = $trigger.data("customer-id") || $trigger.attr("data-customer-id");

            if (!orderId) return;

            clearTimeout(hoverTimer);
            $trigger.find(".mp-notes-hover-progress").css({ transition: "none", width: "0%" });
            this.fetchAndShowNotesPopover(orderId, customerId, trigger);
        });
    }

    async fetchAndShowNotesPopover(orderId, customerId, trigger) {
        if (this.currentNotesPopover) {
            this.currentNotesPopover.remove();
            this.currentNotesPopover = null;
        }

        const payload = new URLSearchParams({
            ajax: "1",
            action: "getOrderLatestNotes",
            id_order: String(orderId),
            id_customer: String(customerId || 0),
        });

        try {
            const response = await fetch(this.adminControllerUrl, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body: payload,
            });
            const data = await response.json();

            if (!data.success || !Array.isArray(data.notes) || data.notes.length === 0) {
                console.warn("Nessuna nota trovata per ordine #", orderId, data);
                return;
            }

            this.renderShadcnNotesPopover(data.notes, orderId, trigger);
        } catch (err) {
            console.error("Errore recupero ultime note ordine #", orderId, err);
        }
    }

    renderShadcnNotesPopover(notes, orderId, trigger) {
        if (this.currentNotesPopover) {
            this.currentNotesPopover.remove();
            this.currentNotesPopover = null;
        }

        const popover = document.createElement("div");
        popover.className = "mp-shadcn-popover";

        let notesHtml = notes
            .map((note) => {
                const iconName = note.icon || "chat";
                return `<div class="mp-shadcn-note-item">
                    <div class="mp-shadcn-note-meta">
                        <span class="mp-shadcn-note-badge ${this.escape(note.badge_class || "")}">
                            <i class="material-icons" style="font-size:13px;line-height:1;">${this.escape(iconName)}</i>
                            ${this.escape(note.type_label)}
                        </span>
                        <span class="mp-shadcn-note-author">${this.escape(note.author)} • ${this.escape(note.date_add)}</span>
                    </div>
                    <div class="mp-shadcn-note-content">${this.escape(note.content)}</div>
                </div>`;
            })
            .join("");

        popover.innerHTML = `
            <div class="mp-shadcn-popover-header">
                <div class="mp-shadcn-popover-title">
                    <i class="material-icons text-primary" style="font-size:18px;">subtitles</i>
                    <span>Ultimi Messaggi (Ordine #${this.escape(orderId)})</span>
                </div>
                <button type="button" class="mp-shadcn-popover-close" title="Chiudi">&times;</button>
            </div>
            <div class="mp-shadcn-notes-list">
                ${notesHtml}
            </div>
        `;

        document.body.appendChild(popover);
        this.currentNotesPopover = popover;

        // Correct viewport calculation for position: fixed
        const rect = trigger.getBoundingClientRect();
        const popoverRect = popover.getBoundingClientRect();

        let top = rect.top - 6;
        let left = rect.right + 12;

        if (left + popoverRect.width > window.innerWidth - 16) {
            left = rect.left - popoverRect.width - 12;
        }
        if (left < 16) {
            left = Math.max(16, (window.innerWidth - popoverRect.width) / 2);
        }

        if (top + popoverRect.height > window.innerHeight - 16) {
            top = Math.max(16, window.innerHeight - popoverRect.height - 16);
        }
        if (top < 16) {
            top = 16;
        }

        popover.style.top = `${top}px`;
        popover.style.left = `${left}px`;

        popover.querySelector(".mp-shadcn-popover-close").addEventListener("click", () => {
            popover.remove();
            this.currentNotesPopover = null;
        });

        popover.addEventListener("mouseleave", () => {
            setTimeout(() => {
                if (this.currentNotesPopover && !popover.matches(":hover") && !$(trigger).is(":hover")) {
                    popover.remove();
                    this.currentNotesPopover = null;
                }
            }, 400);
        });
    }

    bindRowActions() {
        const openPrint = (orderId) => {
            if (typeof MpPrintDialog !== 'undefined') {
                MpPrintDialog.open(orderId);
            } else if (typeof window.AdminOrderExportHelper !== 'undefined') {
                window.AdminOrderExportHelper.openPrintDialog(orderId);
            }
        };

        $(this.table)
            .off("click.orderPrint", ".js-order-action-print")
            .off("click.orderExport", ".js-order-action-export")
            .on("click.orderPrint", ".js-order-action-print", (event) => {
                event.preventDefault();
                const $btn = $(event.currentTarget);
                const orderId = $btn.data("order-id") || $btn.attr("data-order-id") || (event.currentTarget ? event.currentTarget.dataset.orderId : null);
                openPrint(orderId);
            })
            .on("click.orderExport", ".js-order-action-export", (event) => {
                event.preventDefault();
                const $btn = $(event.currentTarget);
                const orderId = $btn.data("order-id") || $btn.attr("data-order-id") || (event.currentTarget ? event.currentTarget.dataset.orderId : null);
                if (typeof window.AdminOrderExportHelper !== 'undefined') {
                    window.AdminOrderExportHelper.exportDocument(orderId);
                }
            });
    }

    renderSearchActions() {
        const $toolbar = $(this.table).closest(".bootstrap-table").find(".fixed-table-toolbar");
        const $columns = $toolbar.find(".columns");

        if ($toolbar.find("[data-orders-search]").length) {
            return;
        }

        let $leftBars = $toolbar.find(".bs-bars");
        if (!$leftBars.length) {
            $leftBars = $("<div>", {
                class: "bs-bars pull-left float-left d-inline-flex align-items-center flex-wrap",
                style: "gap: 8px;",
            });
            $toolbar.prepend($leftBars);
        } else {
            $leftBars.addClass("d-inline-flex align-items-center flex-wrap").css("gap", "8px");
        }

        if (this.brtShipmentsUrl && !$leftBars.find("[data-orders-bordero]").length) {
            $("<a>", {
                class: "btn btn-dark font-weight-bold text-uppercase d-inline-flex align-items-center",
                href: this.brtShipmentsUrl,
                "data-orders-bordero": "",
                style: "background-color: #1e1e1e; color: #ffffff; border-color: #1e1e1e; font-size: 0.85rem; height: 36px; padding: 0 12px;",
                html: '<i class="material-icons mr-1" style="font-size:18px;">local_shipping</i> BORDERÒ',
            }).appendTo($leftBars);
        }

        if (!$leftBars.find("[data-batch-state-wrapper]").length) {
            const $wrapper = $("<div>", {
                class: "input-group d-inline-flex align-items-center",
                "data-batch-state-wrapper": "",
                style: "width: auto;",
            });

            const $select = $("<select>", {
                class: "form-control chosen-ignore",
                style: "max-width: 240px; font-size: 0.85rem; height: 36px; padding: 4px 8px; border-top-right-radius: 0; border-bottom-right-radius: 0;",
            });

            $select.append('<option value="">-- Cambia stato a... --</option>');
            Object.entries(this.orderStates).forEach(([idState, stateName]) => {
                $select.append(`<option value="${idState}">${this.escape(stateName)}</option>`);
            });

            const $btnGroupAppend = $("<div>", { class: "input-group-append" });

            const $btnChange = $("<button>", {
                type: "button",
                class: "btn btn-dark font-weight-bold text-uppercase d-inline-flex align-items-center",
                style: "background-color: #1e1e1e; color: #ffffff; border-color: #1e1e1e; font-size: 0.85rem; height: 36px; padding: 0 12px; border-top-left-radius: 0; border-bottom-left-radius: 0;",
                html: '<i class="material-icons mr-1" style="font-size:18px;">published_with_changes</i> CAMBIA STATO ORDINE',
            }).on("click", () => this.handleBatchChangeStatus($select.val()));

            const $btnPrint = $("<button>", {
                type: "button",
                class: "btn btn-primary font-weight-bold text-uppercase d-inline-flex align-items-center ml-1",
                style: "height: 36px; padding: 0 12px;",
                html: '<i class="material-icons mr-1" style="font-size:18px;">print</i> STAMPA',
            }).on("click", () => this.handleBatchPrint());

            $btnGroupAppend.append($btnChange);
            $wrapper.append($select).append($btnGroupAppend);
            $leftBars.append($wrapper).append($btnPrint);
        }

        $("<button>", {
            type: "button",
            class: "btn btn-info font-weight-bold d-inline-flex align-items-center ml-1",
            "data-orders-search": "",
            style: "height: 36px;",
            html: '<i class="material-icons mr-1" style="font-size:18px;">search</i> Cerca',
        })
            .on("click", () => this.applyFilters())
            .appendTo($columns);

        $("<button>", {
            type: "button",
            class: "btn btn-warning font-weight-bold d-inline-flex align-items-center ml-1",
            "data-orders-reset": "",
            style: "height: 36px; background-color: #fff8e1; border-color: #ffe082; color: #5d4037;",
            html: '<i class="material-icons mr-1" style="font-size:18px;">refresh</i> Reset',
        })
            .on("click", () => this.resetFilters())
            .appendTo($columns);
    }

    handleBatchPrint() {
        const selectedRows = $(this.table).bootstrapTable("getSelections");

        if (!selectedRows || selectedRows.length === 0) {
            if (typeof showNoticeMessage === "function") {
                showNoticeMessage("Seleziona almeno un ordine dalla tabella per la stampa massiva.");
            } else {
                alert("Seleziona almeno un ordine dalla tabella per la stampa massiva.");
            }
            return;
        }

        const orderIds = selectedRows
            .map((row) => parseInt(row.id_order || row.id || 0, 10))
            .filter((id) => id > 0);

        if (orderIds.length === 0) {
            if (typeof showErrorMessage === "function") {
                showErrorMessage("Nessun ID ordine valido trovato tra gli elementi selezionati.");
            } else {
                alert("Nessun ID ordine valido trovato tra gli elementi selezionati.");
            }
            return;
        }

        if (typeof MpPrintDialog !== "undefined") {
            MpPrintDialog.openBatch(orderIds);
        } else {
            console.error("Componente MpPrintDialog non disponibile per la stampa massiva.");
        }
    }

    handleBatchChangeStatus(targetStateId) {
        const selectedRows = $(this.table).bootstrapTable("getSelections");
        const stateId = parseInt(targetStateId);

        if (!selectedRows || selectedRows.length === 0) {
            if (typeof showNoticeMessage === "function") {
                showNoticeMessage("Seleziona almeno un ordine dalla tabella.");
            } else {
                alert("Seleziona almeno un ordine dalla tabella.");
            }
            return;
        }

        if (!stateId) {
            if (typeof showNoticeMessage === "function") {
                showNoticeMessage("Seleziona uno stato di destinazione dal menu a tendina.");
            } else {
                alert("Seleziona uno stato di destinazione dal menu a tendina.");
            }
            return;
        }

        const stateName = this.orderStates[stateId] || `ID ${stateId}`;

        if (!this.batchProgressDialog) {
            this.batchProgressDialog = new MpBatchProgressDialog({
                title: "Aggiornamento Stato Ordini",
                onComplete: () => {
                    this.load();
                },
                onStop: () => {
                    this.load();
                },
            });
        }

        this.batchProgressDialog.setTitle(`Cambio Stato in "${stateName}"`);

        this.batchProgressDialog.runBatch(selectedRows, async (row, signal) => {
            const formData = new FormData();
            formData.append("ajax", "1");
            formData.append("action", "changeOrderStatus");
            formData.append("id_order", row.id_order);
            formData.append("id_order_state", stateId);

            const response = await fetch(this.adminControllerUrl, {
                method: "POST",
                body: formData,
                signal: signal,
            });

            if (!response.ok) {
                throw new Error(`Errore Server HTTP ${response.status}`);
            }

            const data = await response.json();
            return data;
        });
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
