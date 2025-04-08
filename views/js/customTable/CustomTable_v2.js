// CustomTable.js (COMPLETO e RIFATTORIZZATO)

class CustomTable extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: "open" });

        // --- Stato Interno ---
        this._columns = [];
        this._data = []; // Dati della pagina corrente forniti dall'esterno
        this._itemsPerPage = 10; // Per info display o config esterna
        this._totalItems = 0; // Per info display
        this._currentPageForInfo = 1; // Pagina corrente (solo per info display)
        this._filters = {}; // Stato filtri attivi { columnKey: filterValue }
        this._debounceTimer = null; // Timer per debounce filtri

        // --- Elementi UI nel Shadow DOM ---
        // Contenitore generale
        this._container = document.createElement("div");
        this._container.classList.add("table-main-container"); // Nome classe aggiornato

        // Contenitore per la tabella effettiva (per scrolling)
        this._tableRoot = document.createElement("div");
        this._tableRoot.classList.add("table-scroll-container");
        this._tableElement = document.createElement("table");
        this._tableHead = this._tableElement.createTHead();
        this._tableBody = this._tableElement.createTBody();
        this._tableRoot.appendChild(this._tableElement);

        // Contenitore per lo slot (dove andrà il paginator)
        this._slotContainer = document.createElement("div");
        this._slotContainer.classList.add("slot-container");
        const contentSlot = document.createElement("slot"); // Slot di default
        this._slotContainer.appendChild(contentSlot);

        // Aggiungi elementi al container principale
        this._container.appendChild(this._tableRoot);
        this._container.appendChild(this._slotContainer);
        this.shadowRoot.appendChild(this._container);

        // Applica stili CSS
        this._applyStyles();
    }

    // --- Utility Debounce ---
    _debounce(func, delay = 350) {
        // Leggermente aumentato il delay
        clearTimeout(this._debounceTimer);
        this._debounceTimer = setTimeout(() => {
            func.apply(this);
        }, delay);
    }

    // --- Attributi Osservati ---
    static get observedAttributes() {
        // currentPage è opzionale, solo se si vuole mostrare "Pagina X" nella tabella
        return ["theme", "items-per-page", "total-items", "current-page"];
    }

    // --- Callback Attributi Cambiati ---
    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        switch (name) {
            case "theme":
                // Lo stile è gestito da CSS :host([theme="..."])
                console.log(`CustomTable: Tema cambiato a ${newValue}`);
                break;
            case "items-per-page":
                // Aggiorna stato interno se diverso
                const newItems = parseInt(newValue, 10) || 10;
                if (this._itemsPerPage !== newItems) {
                    this._itemsPerPage = newItems;
                    console.log(`CustomTable: itemsPerPage aggiornato a ${this._itemsPerPage}`);
                    // Nota: non forziamo più il reload da qui, è gestito esternamente
                }
                break;
            case "total-items":
                // Aggiorna stato interno se diverso
                const newTotal = parseInt(newValue, 10) || 0;
                if (this._totalItems !== newTotal) {
                    this._totalItems = newTotal;
                    console.log(`CustomTable: totalItems aggiornato a ${this._totalItems}`);
                    // Qui potresti aggiornare un'area info interna se esistesse
                    // this._renderInfo();
                }
                break;
            case "current-page":
                // Aggiorna stato interno (per info) se diverso
                const newPageInfo = parseInt(newValue, 10) || 1;
                if (this._currentPageForInfo !== newPageInfo) {
                    this._currentPageForInfo = newPageInfo;
                    console.log(`CustomTable: currentPage (info) aggiornato a ${this._currentPageForInfo}`);
                    // this._renderInfo();
                }
                break;
        }
    }

    // --- Proprietà JS ---

    get theme() {
        return this.getAttribute("theme");
    }
    set theme(value) {
        if (value) {
            this.setAttribute("theme", value.toLowerCase());
        } else {
            this.removeAttribute("theme");
        }
    }

    get columns() {
        return this._columns;
    }
    set columns(value) {
        if (!Array.isArray(value)) {
            throw new Error("Columns must be an array.");
        }
        this._columns = value;
        this._renderHeader(); // Ridisegna header (potrebbe cambiare filtri)
        this._renderTableBody(); // Ridisegna corpo (potrebbero mancare dati)
    }

    // Riceve solo i dati della pagina corrente
    get data() {
        return this._data;
    }
    set data(value) {
        if (!Array.isArray(value)) {
            throw new Error("Data must be an array.");
        }
        this._data = value;
        this._renderTableBody(); // Ridisegna solo il corpo con i nuovi dati
    }

    get itemsPerPage() {
        return this._itemsPerPage;
    }
    set itemsPerPage(value) {
        const num = parseInt(value, 10);
        if (!isNaN(num) && num > 0) {
            if (this._itemsPerPage !== num) {
                this.setAttribute("items-per-page", num); // Rifletti attributo
                // Non fare altro, il cambio effettivo è gestito esternamente
            }
        } else {
            console.warn("itemsPerPage deve essere un numero positivo.");
        }
    }

    get totalItems() {
        return this._totalItems;
    }
    set totalItems(value) {
        const num = parseInt(value, 10);
        if (!isNaN(num) && num >= 0) {
            if (this._totalItems !== num) {
                this.setAttribute("total-items", num); // Rifletti attributo
            }
        } else {
            console.warn("totalItems deve essere un numero positivo >= 0.");
        }
    }

    // Pagina corrente (solo per info display interno)
    get currentPage() {
        return this._currentPageForInfo;
    }
    set currentPage(value) {
        const page = parseInt(value, 10) || 1;
        if (this._currentPageForInfo !== page) {
            this.setAttribute("current-page", page); // Rifletti attributo
        }
    }

    // Filtri correnti (sola lettura)
    get currentFilters() {
        return { ...this._filters };
    }

    // --- Metodi Rendering ---

    _applyStyles() {
        const style = document.createElement("style");
        style.textContent = `
            /* ----- Variabili Base e Stili Host ----- */
            :host {
                /* Colori, Font, Padding, etc. come prima */
                --table-bg-color: #ffffff; --table-text-color: #333333; --table-border-color: #e0e0e0;
                --header-bg-color: #f8f9fa; --header-text-color: #212529; --row-hover-bg-color: #f1f3f5;
                --row-alternate-bg-color: #f8f9fa; --cell-padding: 12px 15px;
                --table-border-style: 1px solid var(--table-border-color); --row-border-bottom: 1px solid var(--table-border-color);
                --table-font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
                --table-font-size: 14px; --header-font-weight: 600;
                --button-bg-color: #007bff; --button-text-color: white; --button-border-color: #007bff;
                --button-hover-bg-color: #0056b3; --button-padding: 6px 12px; --button-border-radius: 4px;
                --badge-success-bg: #d4edda; --badge-success-text: #155724; --badge-warning-bg: #fff3cd; --badge-warning-text: #856404;
                --badge-danger-bg: #f8d7da; --badge-danger-text: #721c24; --badge-info-bg: #d1ecf1; --badge-info-text: #0c5460;
                --badge-padding: .3em .6em; --badge-font-size: 75%; --badge-border-radius: .25rem;
                --filter-input-bg: #ffffff; --filter-input-text: var(--table-text-color); --filter-input-border: #ced4da;
                --filter-input-focus-border: #80bdff; --filter-input-placeholder: #6c757d;
                --filter-input-padding: 6px 8px; --filter-input-margin-top: 8px;

                display: block; /* Comportamento blocco */
                font-family: var(--table-font-family);
                font-size: var(--table-font-size);
                color: var(--table-text-color);
                /* Nota: Sfondo e bordi sono ora sul wrapper esterno nell'HTML */
            }

            /* Contenitore generale interno */
            .table-main-container {
                 /* Potrebbe non servire stile specifico se il wrapper esterno ha già tutto */
            }

            /* Contenitore tabella per scrolling */
            .table-scroll-container {
                 overflow-x: auto; /* Scroll orizzontale solo se necessario */
            }

            /* Stili tabella */
            table {
                width: 100%;
                border-collapse: collapse;
                border-spacing: 0;
                background-color: var(--table-bg-color); /* Sfondo tabella */
                /* Rimuovi bordi esterni della tabella se il wrapper li gestisce */
            }

            /* Stili Celle Header e Dati */
            th, td {
                padding: var(--cell-padding);
                text-align: left;
                vertical-align: middle;
                border: none; /* Rimuovi tutti i bordi interni di default */
                border-bottom: var(--row-border-bottom); /* Solo linea sotto ogni riga */
            }

            /* Stili Header Specifici */
            th {
                background-color: var(--header-bg-color);
                color: var(--header-text-color);
                font-weight: var(--header-font-weight);
                font-size: calc(var(--table-font-size) * 0.95);
                border-bottom-width: 2px; /* Linea sotto header più spessa */
                vertical-align: top;
                padding-bottom: calc(var(--cell-padding) / 2);
                position: sticky; /* Opzione: Rendi header sticky */
                top: 0; /* Necessario con sticky */
                z-index: 1; /* Sopra il corpo tabella durante scroll */
            }
            .th-content { /* Contenitore titolo in TH */
                 display: block;
                 margin-bottom: var(--filter-input-margin-top);
             }

            /* Stili Corpo Tabella */
            tbody tr:nth-child(even) {
                background-color: var(--row-alternate-bg-color);
            }
            tbody tr:hover {
                background-color: var(--row-hover-bg-color);
            }

            /* Placeholder per tbody vuoto */
            .empty-tbody-placeholder td {
                text-align: center;
                padding: 20px;
                color: var(--filter-input-placeholder, #6c757d);
                background-color: var(--table-bg-color); /* Assicura sfondo corretto */
            }


            /* Stili Input Filtro */
            .filter-input {
                /* ... (stessi stili di prima per .filter-input) ... */
                display: block; width: calc(100% - 16px); padding: var(--filter-input-padding);
                font-size: calc(var(--table-font-size) * 0.9); color: var(--filter-input-text);
                background-color: var(--filter-input-bg); border: 1px solid var(--filter-input-border);
                border-radius: var(--button-border-radius); box-sizing: border-box;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }
            .filter-input::placeholder { color: var(--filter-input-placeholder); opacity: 0.8; }
             .filter-input:focus { border-color: var(--filter-input-focus-border); outline: 0; box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); }

            /* Stili Elementi interni (Bottoni, Badge) */
            /* Necessari se usati nelle celle tramite col.render */
            button { /* ... come prima ... */ }
            .badge { /* ... come prima ... */ }


            /* Stili Contenitore Slot */
            .slot-container {
                 padding: 10px 0 0 0; /* Spazio sopra lo slot */
                 /* Rimosso bordo e sfondo, gestito da stile paginator o wrapper esterno */
             }

             /* ----- Tema Scuro ----- */
             :host([theme="dark"]) {
                /* Sovrascrittura variabili per tema scuro (SENZA variabili paginator) */
                --table-bg-color: #2c3e50; --table-text-color: #ecf0f1; --table-border-color: #34495e;
                --header-bg-color: #34495e; --header-text-color: #ecf0f1; --row-hover-bg-color: #46627f;
                --row-alternate-bg-color: #3a5068;
                --button-bg-color: #3498db; --button-text-color: #ffffff; --button-border-color: #3498db;
                --button-hover-bg-color: #2980b9;
                --badge-success-bg: #27ae60; --badge-success-text: #ffffff; --badge-warning-bg: #f39c12; --badge-warning-text: #ffffff;
                --badge-danger-bg: #e74c3c; --badge-danger-text: #ffffff; --badge-info-bg: #3498db; --badge-info-text: #ffffff;
                --filter-input-bg: #3a5068; --filter-input-text: var(--table-text-color); --filter-input-border: #46627f;
                --filter-input-focus-border: #3498db; --filter-input-placeholder: #95a5a6;

                 /* Stile slot nel tema scuro */
                 .slot-container {
                    /* border-top-color: var(--table-border-color); */ /* Esempio se si vuole bordo */
                    /* background-color: var(--header-bg-color); */ /* Esempio se si vuole sfondo */
                 }
             }

             /* ----- Tema Minimal ----- */
             :host([theme="minimal"]) {
                 /* Sovrascrittura variabili per tema minimal */
                --row-border-bottom: 1px dashed #ccc;
                --header-bg-color: transparent;
                --header-font-weight: 700;
                --row-alternate-bg-color: transparent;
                --row-hover-bg-color: #efefef;
                --table-border-style: none; /* Rimuove bordi se usati */
                /* Potrebbe essere necessario azzerare padding/ombre se presenti sull'host */
                 .slot-container {
                     padding: 10px 0;
                     border-top: 1px dashed #ccc;
                 }
             }
        `;
        this.shadowRoot.appendChild(style);
    }

    _renderHeader() {
        this._tableHead.innerHTML = ""; // Pulisci header precedente
        if (!this._columns || this._columns.length === 0) return;

        const headerRow = this._tableHead.insertRow();
        this._columns.forEach((col) => {
            const th = document.createElement("th");
            const titleContainer = document.createElement("div");
            titleContainer.classList.add("th-content");
            titleContainer.textContent = col.title || "";
            th.appendChild(titleContainer);

            if (col.filterable) {
                const filterInput = document.createElement("input");
                filterInput.type = "search";
                filterInput.classList.add("filter-input");
                filterInput.placeholder = `Filtra ${col.title || ""}...`;
                filterInput.value = this._filters[col.key] || "";
                filterInput.addEventListener("input", (event) => {
                    const value = event.target.value;
                    this._debounce(() => this._handleFilterInput(col.key, value));
                });
                th.appendChild(filterInput);
            }
            headerRow.appendChild(th);
        });
    }

    _renderTableBody() {
        this._tableBody.innerHTML = ""; // Pulisci corpo precedente

        if (!this._data || this._data.length === 0) {
            const numCols = this._columns.length || 1;
            const placeholderRow = this._tableBody.insertRow();
            placeholderRow.classList.add("empty-tbody-placeholder"); // Applica stile specifico
            const placeholderCell = placeholderRow.insertCell();
            placeholderCell.colSpan = numCols;
            placeholderCell.textContent = "Nessun dato da visualizzare.";
            return;
        }

        this._data.forEach((rowData, rowIndex) => {
            const dataRow = this._tableBody.insertRow();
            this._columns.forEach((col) => {
                const td = dataRow.insertCell();
                const cellValue = rowData[col.key];
                if (typeof col.render === "function") {
                    const content = col.render(cellValue, rowData, rowIndex);
                    td.innerHTML = ""; // Pulisci cella
                    if (content instanceof Node) {
                        td.appendChild(content);
                    } else if (content !== null && content !== undefined) {
                        td.textContent = content;
                    }
                } else {
                    td.textContent = cellValue !== undefined && cellValue !== null ? cellValue : "";
                }
            });
        });
    }

    // --- Gestione Filtri Interna ---
    _handleFilterInput(columnKey, value) {
        console.log(`CustomTable: Filter Input Colonna=${columnKey}, Valore=${value}`);
        const trimmedValue = value.trim();
        let filtersChanged = false;

        if (trimmedValue) {
            if (this._filters[columnKey] !== trimmedValue) {
                this._filters[columnKey] = trimmedValue;
                filtersChanged = true;
            }
        } else {
            if (this._filters.hasOwnProperty(columnKey)) {
                delete this._filters[columnKey];
                filtersChanged = true;
            }
        }
        if (filtersChanged) {
            this._triggerFilterChangeForReload();
        }
    }

    // Emette evento per segnalare cambio filtri (implica sempre reset pagina 1 esternamente)
    _triggerFilterChangeForReload() {
        console.log("CustomTable: Emitting filter-change:", this._filters);
        this.dispatchEvent(
            new CustomEvent("filter-change", {
                detail: { filters: { ...this._filters } }, // Invia copia
                bubbles: true,
                composed: true
            })
        );
    }

    // --- Lifecycle Callbacks ---
    connectedCallback() {
        console.log("CustomTable (refactored) aggiunto al DOM!");
        // Render iniziale della struttura vuota (header e body)
        this._renderHeader();
        this._renderTableBody();
        // Non chiama più _renderPaginator
    }

    disconnectedCallback() {
        console.log("CustomTable (refactored) rimosso dal DOM.");
        // Qui potresti voler rimuovere eventuali event listener globali se ne avessi aggiunti
    }
}

customElements.define("custom-table", CustomTable);
