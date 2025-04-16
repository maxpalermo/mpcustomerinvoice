class CustomTable extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: "open" });

        // Stato interno
        this._columns = [];
        this._data = []; // Dati pagina corrente
        this._itemsPerPage = 10;
        this._currentPage = 1;
        this._totalItems = 0;
        this._filters = {}; // Oggetto per i filtri attivi { columnKey: filterValue }
        this._debounceTimer = null; // Timer per debounce filtri

        // Elementi UI
        this._container = document.createElement("div");
        this._container.classList.add("table-container");

        this._tableElement = document.createElement("table"); // L'elemento <table> effettivo
        this._tableHead = this._tableElement.createTHead();
        this._tableBody = this._tableElement.createTBody();
        this._tableRoot = document.createElement("div"); // Contenitore tabella
        this._tableRoot.appendChild(this._tableElement);

        this._paginatorRoot = document.createElement("div");
        this._paginatorRoot.classList.add("paginator-container");

        this._container.appendChild(this._tableRoot);
        this._container.appendChild(this._paginatorRoot);
        this.shadowRoot.appendChild(this._container);

        this._applyStyles();
    }

    // --- Utility Debounce ---
    _debounce(func, delay = 300) {
        clearTimeout(this._debounceTimer);
        this._debounceTimer = setTimeout(() => {
            func.apply(this);
        }, delay);
    }

    // --- Gestione Attributi/Proprietà (inclusi filtri) ---
    static get observedAttributes() {
        return ["theme", "items-per-page", "current-page", "total-items"];
    }
    // ... (attributeChangedCallback come prima, non gestisce filtri direttamente) ...
    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;
        switch (name /* ... gestione theme, items-per-page, etc. come prima ... */) {
            case "theme":
                /* ... */ break;
            case "items-per-page":
                this.itemsPerPage = parseInt(newValue, 10) || 10;
                break;
            case "current-page":
                this._currentPage = parseInt(newValue, 10) || 1;
                this._renderPaginator();
                break;
            case "total-items":
                this.totalItems = parseInt(newValue, 10) || 0;
                break;
        }
    }

    // ... (get/set theme come prima) ...
    get theme() {
        return this.getAttribute("theme");
    }
    set theme(value) {
        /* ... come prima ... */
    }

    set columns(value) {
        if (!Array.isArray(value)) {
            throw new Error("Columns must be an array.");
        }
        this._columns = value;
        this._renderHeader(); // Ridisegna l'header (con i filtri)
        this._renderTableBody(); // Ridisegna il corpo
    }
    get columns() {
        return this._columns;
    }

    set data(value) {
        if (!Array.isArray(value)) {
            throw new Error("Data must be an array.");
        }
        this._data = value;
        this._renderTableBody(); // Ridisegna solo il corpo con i nuovi dati
    }
    get data() {
        return this._data;
    }

    // ... (get/set itemsPerPage, currentPage, totalItems, totalPages come prima) ...
    get itemsPerPage() {
        return this._itemsPerPage;
    }
    set itemsPerPage(value) {
        /* ... come prima, ma chiama _triggerFilterOrPageChange(1) alla fine? Meglio lasciarlo gestire al listener filter-change se necessario */
        const num = parseInt(value, 10);
        if (!isNaN(num) && num > 0 && this._itemsPerPage !== num) {
            this._itemsPerPage = num;
            this.setAttribute("items-per-page", num);
            // Cambiare itemsPerPage dovrebbe ricaricare dalla pagina 1 con filtri correnti
            this._triggerFilterOrPageChange(1); // Usa nuovo metodo helper
            this._renderPaginator();
        }
    }

    get currentPage() {
        return this._currentPage;
    }
    set currentPage(value) {
        this._goToPage(parseInt(value, 10) || 1);
    } // Usa _goToPage

    get totalItems() {
        return this._totalItems;
    }
    set totalItems(value) {
        /* ... come prima, gestisce aggiornamento paginator e currentPage se invalida ... */
        const num = parseInt(value, 10);
        if (!isNaN(num) && num >= 0 && this._totalItems !== num) {
            this._totalItems = num;
            this.setAttribute("total-items", num);
            const totalP = this.totalPages;
            if (this._currentPage > totalP && totalP > 0) {
                this._goToPage(totalP, false); // Vai all'ultima pagina valida senza emettere evento ora
            }
            this._renderPaginator(); // Aggiorna sempre il paginator
        }
    }

    get totalPages() {
        /* ... come prima ... */
        if (this._totalItems === 0 || this._itemsPerPage === 0) return 0;
        return Math.ceil(this._totalItems / this._itemsPerPage);
    }

    // Proprietà per ottenere lo stato corrente dei filtri (sola lettura dall'esterno)
    get currentFilters() {
        // Restituisce una copia per evitare modifiche esterne dirette
        return { ...this._filters };
    }

    // --- Metodi Rendering ---

    _applyStyles() {
        const style = document.createElement("style");
        style.textContent = `
            /* ... (TUTTI gli stili precedenti per :host, table, th, td, paginator, temi) ... */
            :host { /* ... variabili e stili base ... */
                 /* ... (come nell'esempio precedente) ... */
                --table-bg-color: #ffffff; --table-text-color: #333333; --table-border-color: #e0e0e0;
                --header-bg-color: #f8f9fa; --header-text-color: #212529; --row-hover-bg-color: #f1f3f5;
                --row-alternate-bg-color: #f8f9fa; --cell-padding: 12px 15px; --table-border-radius: 8px;
                --table-border-style: 1px solid var(--table-border-color); --row-border-bottom: 1px solid var(--table-border-color);
                --table-font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
                --table-font-size: 14px; --header-font-weight: 600;
                --button-bg-color: #007bff; --button-text-color: white; --button-border-color: #007bff;
                --button-hover-bg-color: #0056b3; --button-padding: 6px 12px; --button-border-radius: 4px;
                --badge-success-bg: #d4edda; --badge-success-text: #155724; --badge-warning-bg: #fff3cd; --badge-warning-text: #856404;
                --badge-danger-bg: #f8d7da; --badge-danger-text: #721c24; --badge-info-bg: #d1ecf1; --badge-info-text: #0c5460;
                --badge-padding: .3em .6em; --badge-font-size: 75%; --badge-border-radius: .25rem;
                --paginator-padding: 15px 0px; --paginator-text-color: var(--table-text-color); --paginator-button-bg: #ffffff;
                --paginator-button-text: var(--button-bg-color); --paginator-button-border: 1px solid #dee2e6;
                --paginator-button-hover-bg: #e9ecef; --paginator-button-active-bg: var(--button-bg-color);
                --paginator-button-active-text: var(--button-text-color); --paginator-button-disabled-bg: #f8f9fa;
                --paginator-button-disabled-text: #adb5bd; --paginator-button-disabled-border: #dee2e6;
                --paginator-button-size: 36px; --paginator-button-margin: 2px; --paginator-radius: var(--button-border-radius);

                /* Nuove Variabili per Filtri */
                --filter-input-bg: #ffffff;
                --filter-input-text: var(--table-text-color);
                --filter-input-border: #ced4da;
                --filter-input-focus-border: #80bdff;
                --filter-input-placeholder: #6c757d;
                --filter-input-padding: 6px 8px;
                --filter-input-margin-top: 8px; /* Spazio tra titolo e input filtro */

                display: block; font-family: var(--table-font-family); font-size: var(--table-font-size);
                color: var(--table-text-color); background-color: var(--table-bg-color);
                border-radius: var(--table-border-radius); overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }

            /* ... (Stili .table-container, table, tbody, bottoni, badge come prima) ... */
            .table-container { overflow-x: auto; padding: 5px; }
            table { width: 100%; border-collapse: collapse; border-spacing: 0; background-color: var(--table-bg-color); }
            th, td { padding: var(--cell-padding); text-align: left; vertical-align: middle; border: none; border-bottom: var(--row-border-bottom); }
            tbody tr:nth-child(even) { background-color: var(--row-alternate-bg-color); }
            tbody tr:hover { background-color: var(--row-hover-bg-color); }
             button { /* ... */ } .badge { /* ... */ }

            /* Stili Header (thead) */
            th {
                background-color: var(--header-bg-color);
                color: var(--header-text-color);
                font-weight: var(--header-font-weight);
                font-size: calc(var(--table-font-size) * 0.95);
                border-bottom: 2px solid var(--table-border-color); /* Bordo più spesso sotto header */
                position: relative; /* Necessario per posizionare l'input? Non per ora. */
                vertical-align: top; /* Allinea titolo in alto se c'è filtro sotto */
                padding-bottom: calc(var(--cell-padding) / 2); /* Riduci padding sotto il titolo se c'è filtro */
            }
             /* Stile Contenitore Titolo nel TH */
             .th-content {
                 display: block; /* O flex se vuoi allineare cose accanto */
                 margin-bottom: var(--filter-input-margin-top);
             }

            /* Stili Input Filtro */
            .filter-input {
                display: block; /* Occupa tutta la larghezza */
                width: calc(100% - 16px); /* Lascia spazio per padding */
                padding: var(--filter-input-padding);
                font-size: calc(var(--table-font-size) * 0.9);
                color: var(--filter-input-text);
                background-color: var(--filter-input-bg);
                border: 1px solid var(--filter-input-border);
                border-radius: var(--button-border-radius);
                box-sizing: border-box; /* Include padding/border nel width */
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }
            .filter-input::placeholder {
                color: var(--filter-input-placeholder);
                opacity: 0.8;
            }
             .filter-input:focus {
                 border-color: var(--filter-input-focus-border);
                 outline: 0;
                 box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
             }


            /* ... (Stili Paginator come prima) ... */
             .paginator-container { /* ... */ }
             .paginator-container button, .paginator-container span { /* ... */ }

            /* ----- Tema Scuro ----- */
            :host([theme="dark"]) {
                /* ... (sovrascrittura variabili tema scuro come prima) ... */
                --table-bg-color: #2c3e50; --table-text-color: #ecf0f1; --table-border-color: #34495e;
                --header-bg-color: #34495e; --header-text-color: #ecf0f1; --row-hover-bg-color: #46627f;
                --row-alternate-bg-color: #3a5068;
                --button-bg-color: #3498db; --button-text-color: #ffffff; --button-border-color: #3498db;
                --button-hover-bg-color: #2980b9;
                --badge-success-bg: #27ae60; --badge-success-text: #ffffff; --badge-warning-bg: #f39c12; --badge-warning-text: #ffffff;
                --badge-danger-bg: #e74c3c; --badge-danger-text: #ffffff; --badge-info-bg: #3498db; --badge-info-text: #ffffff;
                --paginator-padding: 15px 0px; --paginator-text-color: var(--table-text-color); --paginator-button-bg: #3a5068;
                --paginator-button-text: var(--table-text-color); --paginator-button-border: 1px solid #46627f;
                --paginator-button-hover-bg: #46627f; --paginator-button-active-bg: var(--button-bg-color);
                --paginator-button-active-text: var(--button-text-color); --paginator-button-disabled-bg: #2c3e50;
                --paginator-button-disabled-text: #566a7f; --paginator-button-disabled-border: #34495e;

                /* Sovrascrittura variabili Filtri per tema scuro */
                --filter-input-bg: #3a5068;
                --filter-input-text: var(--table-text-color);
                --filter-input-border: #46627f;
                --filter-input-focus-border: #3498db;
                --filter-input-placeholder: #95a5a6;
             }
             /* ... (altri temi come :host([theme="minimal"])) ... */
        `;
        this.shadowRoot.appendChild(style);
    }

    _renderHeader() {
        // Pulisci solo il contenuto dell'header precedente
        this._tableHead.innerHTML = "";

        if (!this._columns || this._columns.length === 0) return;

        const headerRow = this._tableHead.insertRow();
        this._columns.forEach((col) => {
            const th = document.createElement("th");

            // Contenitore per titolo (per layout flessibile)
            const titleContainer = document.createElement("div");
            titleContainer.classList.add("th-content");
            titleContainer.textContent = col.title || "";
            th.appendChild(titleContainer);

            // Aggiungi input filtro se la colonna è filterable
            if (col.filterable) {
                const filterInput = document.createElement("input");
                filterInput.type = "search"; // Usa tipo search per avere la 'x' (dipende dal browser)
                filterInput.classList.add("filter-input");
                filterInput.placeholder = `Filtra ${col.title || ""}...`;
                filterInput.value = this._filters[col.key] || ""; // Imposta valore attuale del filtro

                // Aggiungi event listener con debounce
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
        // Ridisegna solo il corpo della tabella
        this._tableBody.innerHTML = ""; // Pulisci corpo precedente

        if (!this._data || this._data.length === 0) {
            // Opzionale: Mostra un messaggio nel corpo se non ci sono dati
            // Potrebbe essere preferibile un messaggio fuori dalla tabella
            const placeholderRow = this._tableBody.insertRow();
            const placeholderCell = placeholderRow.insertCell();
            placeholderCell.colSpan = this._columns.length || 1;
            placeholderCell.textContent = "Nessun dato da visualizzare.";
            placeholderCell.style.textAlign = "center";
            placeholderCell.style.padding = "20px";
            placeholderCell.style.color = "var(--filter-input-placeholder)"; // Riutilizza colore placeholder
            return;
        }

        this._data.forEach((rowData, rowIndex) => {
            const dataRow = this._tableBody.insertRow();
            this._columns.forEach((col) => {
                const td = dataRow.insertCell();
                const cellValue = rowData[col.key];
                if (typeof col.render === "function") {
                    const content = col.render(cellValue, rowData, rowIndex);
                    td.innerHTML = "";
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

    _renderPaginator() {
        /* ... (come prima) ... */
        this._paginatorRoot.innerHTML = "";
        const totalP = this.totalPages;
        const currentP = this._currentPage;
        if (totalP <= 1) return;
        const createButton = (text, pageNum, isDisabled = false, isActive = false, isEllipsis = false) => {
            /* ... come prima ... */
            if (isEllipsis) {
                const span = document.createElement("span");
                span.className = "ellipsis";
                span.textContent = "...";
                return span;
            }
            const btn = document.createElement("button");
            btn.textContent = text;
            btn.disabled = isDisabled;
            if (isDisabled) btn.classList.add("disabled");
            if (isActive) btn.classList.add("active");
            if (!isDisabled && !isActive) {
                btn.onclick = () => this._goToPage(pageNum);
            }
            return btn;
        };
        this._paginatorRoot.appendChild(createButton("«", currentP - 1, currentP === 1));
        const maxButtons = 7;
        const buttonsAround = Math.floor((maxButtons - 3) / 2);
        if (totalP <= maxButtons) {
            for (let i = 1; i <= totalP; i++) {
                this._paginatorRoot.appendChild(createButton(i, i, false, i === currentP));
            }
        } else {
            this._paginatorRoot.appendChild(createButton(1, 1, false, currentP === 1));
            let startPage = Math.max(2, currentP - buttonsAround);
            let endPage = Math.min(totalP - 1, currentP + buttonsAround);
            if (currentP - buttonsAround <= 2) {
                endPage = maxButtons - 2;
            }
            if (currentP + buttonsAround >= totalP - 1) {
                startPage = totalP - (maxButtons - 3);
            }
            if (startPage > 2) {
                this._paginatorRoot.appendChild(createButton("...", 0, true, false, true));
            }
            for (let i = startPage; i <= endPage; i++) {
                this._paginatorRoot.appendChild(createButton(i, i, false, i === currentP));
            }
            if (endPage < totalP - 1) {
                this._paginatorRoot.appendChild(createButton("...", 0, true, false, true));
            }
            this._paginatorRoot.appendChild(createButton(totalP, totalP, false, currentP === totalP));
        }
        this._paginatorRoot.appendChild(createButton("»", currentP + 1, currentP === totalP));
    }

    // --- Gestione Eventi Interni ---

    _handleFilterInput(columnKey, value) {
        console.log(`Filter input: Colonna=${columnKey}, Valore=${value}`);
        const trimmedValue = value.trim();
        let filtersChanged = false;

        if (trimmedValue) {
            // Aggiungi o aggiorna filtro
            if (this._filters[columnKey] !== trimmedValue) {
                this._filters[columnKey] = trimmedValue;
                filtersChanged = true;
            }
        } else {
            // Rimuovi filtro se il valore è vuoto
            if (this._filters.hasOwnProperty(columnKey)) {
                delete this._filters[columnKey];
                filtersChanged = true;
            }
        }

        // Se i filtri sono effettivamente cambiati, emetti l'evento
        if (filtersChanged) {
            console.log("Filtri cambiati, emetto evento filter-change:", this._filters);
            this.dispatchEvent(
                new CustomEvent("filter-change", {
                    detail: { filters: { ...this._filters } }, // Invia copia
                    bubbles: true,
                    composed: true
                })
            );
            // Nota: Il reset della pagina a 1 avverrà nell'handler esterno
            // che riceve questo evento e ricarica i dati.
        }
    }

    // Metodo centrale per cambiare pagina (invocato da bottoni paginator)
    _goToPage(pageNumber, dispatchEvent = true) {
        const totalP = this.totalPages;
        const newPage = Math.max(1, Math.min(pageNumber, totalP > 0 ? totalP : 1));

        // Emetti l'evento SOLO se la pagina cambia effettivamente
        if (this._currentPage !== newPage && dispatchEvent) {
            this._currentPage = newPage; // Aggiorna stato interno *prima* di emettere
            this.setAttribute("current-page", newPage); // Rifletti attributo
            console.log(`Pagina cambiata a: ${newPage}. Emetto page-change.`);

            this.dispatchEvent(
                new CustomEvent("page-change", {
                    detail: {
                        page: this._currentPage,
                        itemsPerPage: this._itemsPerPage,
                        // Includi i filtri correnti nell'evento page-change!
                        filters: { ...this._filters }
                    },
                    bubbles: true,
                    composed: true
                })
            );
            // Aggiorna solo UI paginator, i dati arrivano dall'esterno
            this._renderPaginator();
        } else if (!dispatchEvent && this._currentPage !== newPage) {
            // Caso in cui si aggiorna pagina internamente senza evento (es. totalItems cambia)
            this._currentPage = newPage;
            this.setAttribute("current-page", newPage);
            this._renderPaginator();
        } else {
            // Pagina non cambiata o dispatchEvent=false, non fare nulla o solo UI
            this._renderPaginator();
        }
    }

    // Helper per triggerare ricaricamento (usato da itemsPerPage)
    _triggerFilterOrPageChange(page = 1) {
        // Simula un cambio di pagina o filtro per forzare ricarica esterna
        // Emette page-change perché ha un impatto sulla paginazione
        console.log(`Triggering data reload for page ${page} with current filters.`);
        this.dispatchEvent(
            new CustomEvent("page-change", {
                detail: {
                    page: page, // Forza pagina 1 o quella specificata
                    itemsPerPage: this._itemsPerPage,
                    filters: { ...this._filters }
                },
                bubbles: true,
                composed: true
            })
        );
        // Aggiorna stato interno se forziamo pagina 1
        if (page === 1) {
            this._currentPage = 1;
            this.setAttribute("current-page", 1);
        }
        this._renderPaginator(); // Assicura UI paginator aggiornata
    }

    // --- Lifecycle Callbacks ---
    connectedCallback() {
        /* ... come prima ... */
        console.log("CustomTable aggiunto al DOM!");
        // Render iniziale header/body vuoti e paginator
        this._renderHeader();
        this._renderTableBody();
        this._renderPaginator();
    }
    disconnectedCallback() {
        /* ... come prima ... */
    }
}

customElements.define("custom-table", CustomTable);
