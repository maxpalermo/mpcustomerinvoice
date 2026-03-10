class AdminTableCustomers {
    tableId = null;
    table = null;
    toolbarId = null;
    toolbar = null;
    adminControllerUrl = null;
    jobAreas = [];
    jobPositions = [];

    constructor(tableId, adminControllerUrl, toolbarId, jobAreas, jobPositions) {
        this.tableId = tableId;
        this.adminControllerUrl = adminControllerUrl;
        this.table = document.getElementById(tableId);
        this.jobAreas = jobAreas;
        this.jobPositions = jobPositions;

        console.log(this.jobAreas, this.jobPositions);

        if (!this.table) {
            console.error('Table with id "' + tableId + '" not found');
            return;
        }
        if (toolbarId != null) {
            this.toolbarId = toolbarId;
            this.toolbar = document.getElementById(toolbarId);
            if (!this.toolbar) {
                console.error('Toolbar with id "' + toolbarId + '" not found');
                return;
            }
        }
        this.init();
    }

    init() {
        console.log("AdminTableCustomers initialized with tableId:", this.tableId);
        this.initTable();
    }

    initTable() {
        const self = this;
        $(self.table).bootstrapTable({
            url: self.adminControllerUrl,
            method: "post",
            contentType: "application/x-www-form-urlencoded",
            queryParams: function (params) {
                console.log("QUERY PARAMS:", params);
                return {
                    ajax: 1,
                    action: "renderCustomersData",
                    limit: params.limit,
                    offset: params.offset,
                    search: params.search == undefined ? "" : params.search,
                    sort: params.sort == undefined ? "c.id_customer" : params.sort,
                    order: params.order == undefined ? "asc" : params.order,
                    filter: params.filter == undefined ? "" : params.filter,
                };
            },
            filterControl: true,
            filterControlVisible: true,
            filterControlSearchClear: true,
            showFilterControlSwitch: true,
            searchOnEnterKey: true,
            sortSelectOptions: true,
            serverSort: true,
            sidePagination: "server",
            pagination: true,
            search: false,
            showRefresh: true,
            showColumns: false,
            pageSize: 25,
            pageList: [10, 25, 50, 100, 250, 500],
            locale: "it-IT",
            classes: "table table-bordered table-hover",
            theadClasses: "thead-light",
            showExport: false,
            toolbar: `#${self.toolbarId}`,
            uniqueId: "id_customer",
            detailView: true,
            detailFormatter: (index, row) => {
                return '<div id="detail-' + row.id_customer + '">Caricamento...</div>';
            },
            onExpandRow: (index, row, $detail) => {
                fetch(self.adminControllerUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        action: "getCustomerAddresses",
                        ajax: 1,
                        id_customer: row.id_customer,
                    }),
                })
                    .then((response) => response.json())
                    .then((data) => {
                        // data.table è l’HTML generato dal Twig customerAddresses.html.twig
                        $detail.html(data.table);
                    })
                    .catch((error) => {
                        console.error("Errore caricamento dettaglio:", error);
                        $detail.html('<div class="text-danger">Errore nel caricamento dei dettagli.</div>');
                    });
            },
            iconsPrefix: "icon", // usa Font Awesome invece delle glyphicons
            icons: {
                detailOpen: "icon-plus icon-2x", // icona quando è chiuso
                detailClose: "icon-minus icon-2x", // icona quando è aperto
            },
            onPostBody: function () {
                console.log("Bootstrap Table initialized successfully");
                self.bindActionButtons();
                self.setBootstrapTableIcons();
                self.fixBootstrapTable();
            },
            columns: [
                {
                    field: "id_customer",
                    title: "ID",
                    align: "center",
                    width: 50,
                    sortable: true,
                    filterControl: "input",
                },
                {
                    field: "id_eurosolution",
                    title: "EUROSOLUTION",
                    align: "center",
                    width: 50,
                    sortable: true,
                    filterControl: "input",
                    formatter: function (value, row, index) {
                        value = String(value).toUpperCase();
                        if (value == "" || value == 0 || value == null || value == undefined || value == "NULL") {
                            return "--";
                        }
                        return value ? `<span class="badge badge-info">${value}</span>` : "--";
                    },
                },
                {
                    field: "type",
                    title: "TIPO",
                    align: "center",
                    sortable: true,
                    filterControl: "select",
                    filterData:
                        "json:" +
                        JSON.stringify({
                            ENTE: "ENTE",
                            PARTITA_IVA: "PARTITA IVA",
                            PRIVATO: "PRIVATO",
                        }),
                    formatter: function (value, row, index) {
                        switch (value) {
                            case "ENTE":
                                return `
                                    <span class="badge badge-info">ENTE</span>
                                `;
                            case "PARTITA IVA":
                                return `
                                    <span class="badge badge-warning">PARTITA IVA</span>
                                `;
                            case "PRIVATO":
                                return `
                                    <span class="badge badge-success">PRIVATO</span>
                                `;
                            default:
                                return "--";
                        }
                    },
                },
                {
                    field: "firstname",
                    title: "Nome",
                    align: "left",
                    sortable: true,
                    filterControl: "input",
                    formatter: function (value, row, index) {
                        return value ? String(value).toUpperCase() : "--";
                    },
                },
                {
                    field: "lastname",
                    title: "Cognome",
                    align: "left",
                    sortable: true,
                    filterControl: "input",
                    formatter: function (value, row, index) {
                        return value ? String(value).toUpperCase() : "--";
                    },
                },
                {
                    field: "email",
                    title: "Email",
                    align: "left",
                    sortable: true,
                    filterControl: "input",
                },
                {
                    field: "dni",
                    title: "Cod Fiscale",
                    align: "left",
                    sortable: true,
                    filterControl: "input",
                },
                {
                    field: "vat_number",
                    title: "Partita IVA",
                    align: "left",
                    sortable: true,
                    filterControl: "input",
                },
                {
                    field: "pec",
                    title: "PEC",
                    align: "left",
                    sortable: true,
                    filterControl: "input",
                    formatter: function (value, row, index) {
                        return value ? String(value).toLowerCase() : "--";
                    },
                },
                {
                    field: "sdi",
                    title: "SDI",
                    align: "left",
                    sortable: true,
                    filterControl: "input",
                },

                {
                    field: "cig",
                    title: "CIG",
                    align: "left",
                    sortable: true,
                    filterControl: "input",
                },

                {
                    field: "cup",
                    title: "CUP",
                    align: "left",
                    sortable: true,
                    filterControl: "input",
                },

                {
                    field: "job_area",
                    title: "SETTORE",
                    align: "left",
                    sortable: true,
                    filterControl: "select",
                    filterData: "json:" + JSON.stringify(adminTableCustomers.jobAreas),
                    filterOrderBy: "name",
                },
                {
                    field: "job_position",
                    title: "PROFESSIONE",
                    align: "left",
                    sortable: true,
                    filterControl: "select",
                    filterData: "json:" + JSON.stringify(adminTableCustomers.jobPositions),
                    filterOrderBy: "name",
                },
                {
                    field: "date_add",
                    title: "DATA",
                    align: "center",
                    sortable: true,
                    formatter: function (value) {
                        return new Date(value).toLocaleDateString("it-IT");
                    },
                },
                {
                    field: "action",
                    title: "Azioni",
                    align: "center",
                    width: 50,
                    sortable: false,
                    formatter: function (value, row, index) {
                        let customerPageUrl = window.customerPageLink.replace("999999999", row.id_customer);
                        return `
                            <div class="d-flex justify-content-center align-items-center gap-2">
                                <a target="_blank" href="${customerPageUrl}" type="button" class="btn btn-info btn-sm" title="Vedi scheda cliente">
                                    <span class="material-icons">person</span>
                                </a>
                            </div>
                        `;
                    },
                },
            ],
        });
    }

    fixBootstrapTable() {
        // Normalizza il markup del dropdown page-size a Bootstrap 3
        $(".fixed-table-pagination .btn-group.dropdown").each(function () {
            var $group = $(this);
            var $menuDiv = $group.find("> .dropdown-menu");

            if ($menuDiv.length) {
                // Se non è già <ul>, converti
                if ($menuDiv.prop("tagName") !== "UL") {
                    var $ul = $('<ul class="dropdown-menu" role="menu"></ul>');

                    $menuDiv.find("a").each(function () {
                        var $a = $(this);
                        var $li = $("<li></li>");
                        $a.removeClass("dropdown-item"); // classe BS4/5 inutile qui
                        $li.append($a);
                        $ul.append($li);
                    });

                    $menuDiv.replaceWith($ul);
                }
            }

            // Assicura data-toggle (non data-bs-toggle) e inizializza il plugin
            var $btn = $group.find("> .dropdown-toggle");
            if ($btn.attr("data-bs-toggle") === "dropdown") {
                $btn.removeAttr("data-bs-toggle").attr("data-toggle", "dropdown");
            }
            if (typeof $.fn.dropdown === "function") {
                $btn.dropdown();
            }
        });

        $("button[name=refresh] i").removeClass("material-icons").addClass("icon icon-refresh").val("");

        $(".fixed-table-pagination .dropdown-toggle")
            .off("click")
            .on("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $(this);
                const $menu = $btn.closest(".btn-group").find(".dropdown-menu");

                $(".fixed-table-pagination .dropdown-menu").not($menu).removeClass("show");
                $menu.toggleClass("show");
            });

        $("button[name=filterControlSwitch]").html("<i class='icon icon-filter'></i>");

        $(document)
            .off("click.bs-table-page-size")
            .on("click.bs-table-page-size", function () {
                $(".fixed-table-pagination .dropdown-menu").removeClass("show");
            });
    }

    bindActionButtons() {
        // Qui andranno i tuoi event listener per i pulsanti delle azioni
        // Ad esempio: $(".btn-edit").on("click", function() { ... });
    }

    setBootstrapTableIcons() {
        // Assicura che le icone di Bootstrap Table siano correttamente caricate
        $(".fixed-table-toolbar .search input").addClass("form-control");
        $(".fixed-table-toolbar .columns label").addClass("checkbox-inline");
    }

    getJobAreas() {
        return this.jobAreas;
    }

    getJobPositions() {
        return this.jobPositions;
    }
}
