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
        this._loadStyles();
    }

    // Metodo per caricare il CSS esterno
    _loadStyles() {
        const link = document.createElement("link");
        link.setAttribute("rel", "stylesheet");
        // Assicurati che il percorso sia corretto rispetto alla posizione dell'HTML
        link.setAttribute("href", "./css/custom-table.css");
        this.shadowRoot.appendChild(link); // Aggiungi al Shadow DOM
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
        console.log("CustomTable (CSS esterno) aggiunto al DOM!");
        // Render iniziale struttura, gli stili verranno applicati dal CSS caricato
        if (!this.shadowRoot.querySelector("table")) {
            // Evita re-render non necessari
            this._renderHeader();
            this._renderTableBody();
        }
    }

    disconnectedCallback() {
        console.log("CustomTable (CSS esterno) rimosso dal DOM.");
        // Qui potresti voler rimuovere eventuali event listener globali se ne avessi aggiunti
    }
}

customElements.define("custom-table", CustomTable);
