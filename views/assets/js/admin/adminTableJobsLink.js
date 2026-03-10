class AdminTableJobsLink {
    tableId = null;
    table = null;
    toolbarId = null;
    toolbar = null;
    adminControllerUrl = null;

    constructor(tableId, adminControllerUrl, toolbarId) {
        this.tableId = tableId;
        this.adminControllerUrl = adminControllerUrl;
        this.table = document.getElementById(tableId);
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
                return {
                    ajax: 1,
                    action: "renderJobsLink",
                    limit: params.limit,
                    offset: params.offset,
                    search: params.search,
                    sort: params.sort == undefined ? "jal.name" : params.sort,
                    order: params.order == undefined ? "asc" : params.order,
                };
            },
            serverSort: true,
            sidePagination: "server",
            pagination: true,
            search: true,
            showRefresh: true,
            showColumns: false,
            pageSize: 10,
            pageList: [10, 25, 50, 100, 250, 500],
            locale: "it-IT",
            classes: "table table-bordered table-hover",
            theadClasses: "thead-light",
            showExport: false,
            toolbar: `#${self.toolbarId}`,
            uniqueId: "id_customer_invoice_job_area",
            detailView: true,
            detailFormatter: (index, row) => {
                return '<div id="detail-' + row.id_customer_invoice_job_area + '">Caricamento...</div>';
            },
            onExpandRow: (index, row, $detail) => {
                fetch(self.adminControllerUrl, {
                    method: "POST",
                    body: new URLSearchParams({
                        action: "renderJobsPositionsByJobArea",
                        ajax: 1,
                        idJobArea: row.id_customer_invoice_job_area,
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
                    field: "id_customer_invoice_job_area",
                    title: "ID",
                    align: "center",
                    width: 50,
                    sortable: true,
                },
                {
                    field: "job_area",
                    title: "SETTORE",
                    align: "center",
                    sortable: true,
                },
                {
                    field: "job_positions_count",
                    title: "PROFESSIONI",
                    align: "center",
                    sortable: true,
                    formatter: function (value) {
                        return `<span class="badge badge-primary">${value}</span>`;
                    },
                },
                {
                    field: "date_add",
                    title: "DATA",
                    align: "center",
                    sortable: true,
                    formatter: function (value) {
                        //Controlla se è una data valida
                        if (value == null || value == "" || value == "0000-00-00 00:00:00") {
                            return "--";
                        }
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
                        return `
                            <div class="d-flex justify-content-center align-items-center gap-2">
                                <button type="button" class="btn btn-info btn-sm" name="btn-add-job-position" title="Aggiungi professione" data-id="${row.id_customer_invoice_job_area}">
                                    <span class="material-icons">add</span>
                                </button>
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
}
