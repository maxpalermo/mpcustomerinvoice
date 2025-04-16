class CustomTable extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: "open" });

        // Stato interno
        this._columns = [];
        this._data = []; // CONTERRÀ SOLO I DATI DELLA PAGINA CORRENTE
        this._itemsPerPage = 10; // Default
        this._currentPage = 1; // Default
        this._totalItems = 0; // Default - FONDAMENTALE per paginazione server-side

        // Elementi UI nel Shadow DOM
        this._container = document.createElement("div");
        this._container.classList.add("table-container");

        this._tableRoot = document.createElement("div"); // Dove va la tabella
        this._paginatorRoot = document.createElement("div"); // Dove va il paginator
        this._paginatorRoot.classList.add("paginator-container");

        this._container.appendChild(this._tableRoot);
        this._container.appendChild(this._paginatorRoot);
        this.shadowRoot.appendChild(this._container);

        this._applyStyles();
    }

    // --- Gestione Tema ---
    static get observedAttributes() {
        return ["theme", "items-per-page", "current-page", "total-items"]; // Aggiunti attributi paginazione
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return; // Non fare nulla se il valore non è cambiato

        switch (name) {
            case "theme":
                console.log(`Tema cambiato da ${oldValue} a ${newValue}`);
                // Lo stile è gestito da :host([theme="..."])
                break;
            case "items-per-page":
                this.itemsPerPage = parseInt(newValue, 10) || 10;
                break;
            case "current-page":
                // Non aggiornare da attributo se causa un loop con il setter
                // this.currentPage = parseInt(newValue, 10) || 1;
                // È meglio impostare currentPage solo via proprietà JS
                this._currentPage = parseInt(newValue, 10) || 1;
                this._renderPaginator(); // Aggiorna solo il paginator
                break;
            case "total-items":
                this.totalItems = parseInt(newValue, 10) || 0;
                break;
        }
    }

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

    // --- Gestione Dati e Colonne ---
    set columns(value) {
        if (!Array.isArray(value)) {
            throw new Error("Columns must be an array.");
        }
        this._columns = value;
        this._renderTable(); // Ridisegna solo la tabella
    }
    get columns() {
        return this._columns;
    }

    // IMPORTANTE: Ora 'data' si aspetta SOLO i dati per la pagina corrente
    set data(value) {
        if (!Array.isArray(value)) {
            throw new Error("Data must be an array.");
        }
        this._data = value;
        this._renderTable(); // Ridisegna solo la tabella con i nuovi dati
    }
    get data() {
        return this._data;
    }

    // --- Gestione Paginazione ---
    get itemsPerPage() {
        return this._itemsPerPage;
    }
    set itemsPerPage(value) {
        const num = parseInt(value, 10);
        if (isNaN(num) || num <= 0) {
            console.warn("itemsPerPage deve essere un numero positivo.");
            return;
        }
        if (this._itemsPerPage !== num) {
            this._itemsPerPage = num;
            this.setAttribute("items-per-page", num);
            // Se cambia itemsPerPage, torna alla prima pagina e ricarica
            this._goToPage(1, false); // Non emettere evento subito, lascia che totalItems lo faccia se serve
            this._renderPaginator();
        }
    }

    get currentPage() {
        return this._currentPage;
    }
    set currentPage(value) {
        const num = parseInt(value, 10);
        if (isNaN(num) || num < 1) {
            console.warn("currentPage deve essere un numero positivo >= 1.");
            return;
        }
        // Non impostare direttamente, usa _goToPage per validare e emettere evento
        this._goToPage(num);
    }

    get totalItems() {
        return this._totalItems;
    }
    set totalItems(value) {
        const num = parseInt(value, 10);
        if (isNaN(num) || num < 0) {
            console.warn("totalItems deve essere un numero positivo >= 0.");
            return;
        }
        if (this._totalItems !== num) {
            this._totalItems = num;
            this.setAttribute("total-items", num);
            // Aggiorna il paginator e potenzialmente la pagina corrente se era fuori range
            const totalP = this.totalPages;
            if (this._currentPage > totalP && totalP > 0) {
                // Se la pagina corrente è ora invalida, vai all'ultima valida
                this._goToPage(totalP);
            } else {
                // Altrimenti ridisegna solo il paginator
                this._renderPaginator();
            }
        }
    }

    get totalPages() {
        if (this._totalItems === 0 || this._itemsPerPage === 0) return 0;
        return Math.ceil(this._totalItems / this._itemsPerPage);
    }

    // --- Metodi Interni ---

    _applyStyles() {
        const style = document.createElement("style");
        style.textContent = `
            /* ----- Variabili CSS (come prima) ----- */
            :host {
                /* ... (variabili colori, font, etc. come nell'esempio precedente) ... */
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

                /* Nuove variabili per Paginator */
                --paginator-padding: 15px 0px; /* Padding sopra/sotto il paginator */
                --paginator-text-color: var(--table-text-color);
                --paginator-button-bg: #ffffff;
                --paginator-button-text: var(--button-bg-color);
                --paginator-button-border: 1px solid #dee2e6;
                --paginator-button-hover-bg: #e9ecef;
                --paginator-button-active-bg: var(--button-bg-color);
                --paginator-button-active-text: var(--button-text-color);
                --paginator-button-disabled-bg: #f8f9fa;
                --paginator-button-disabled-text: #adb5bd;
                --paginator-button-disabled-border: #dee2e6;
                --paginator-button-size: 36px; /* Altezza/larghezza bottoni quadrati */
                --paginator-button-margin: 2px; /* Spazio tra bottoni */
                --paginator-radius: var(--button-border-radius);

                /* Stile Host */
                display: block; font-family: var(--table-font-family); font-size: var(--table-font-size);
                color: var(--table-text-color); background-color: var(--table-bg-color);
                border-radius: var(--table-border-radius); overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            .table-container { /* Mantiene lo scroll orizzontale per la tabella */
                overflow-x: auto;
                padding: 5px; /* Padding per ombra */
            }
            table { /* ... (stili tabella come prima) ... */
                width: 100%; border-collapse: collapse; border-spacing: 0;
                background-color: var(--table-bg-color);
            }
            th, td { /* ... (stili celle come prima) ... */
                padding: var(--cell-padding); text-align: left; vertical-align: middle;
                border-top: none; border-left: none; border-right: none;
                border-bottom: var(--row-border-bottom);
            }
            th { /* ... (stili header come prima) ... */
                background-color: var(--header-bg-color); color: var(--header-text-color);
                font-weight: var(--header-font-weight); font-size: calc(var(--table-font-size) * 0.95);
                border-bottom-width: 2px;
            }
            tbody tr:nth-child(even) { background-color: var(--row-alternate-bg-color); }
            tbody tr:hover { background-color: var(--row-hover-bg-color); }
            button, .badge { /* ... (stili bottoni/badge come prima) ... */ }
            button {
                padding: var(--button-padding); cursor: pointer; border: 1px solid var(--button-border-color);
                background-color: var(--button-bg-color); color: var(--button-text-color);
                border-radius: var(--button-border-radius); font-size: inherit; transition: background-color 0.2s ease;
            }
            button:hover { background-color: var(--button-hover-bg-color); }
            button.danger { background-color: var(--badge-danger-bg); border-color: var(--badge-danger-text); color: var(--badge-danger-text); }
            button.danger:hover { background-color: #e4a0a6; }
             .badge { /* ... (stili badge come prima) ... */
                display: inline-block; padding: var(--badge-padding); font-size: var(--badge-font-size); font-weight: 700;
                line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline;
                border-radius: var(--badge-border-radius);
            }
             .badge-success { background-color: var(--badge-success-bg); color: var(--badge-success-text); }
             .badge-warning { background-color: var(--badge-warning-bg); color: var(--badge-warning-text); }
             .badge-danger  { background-color: var(--badge-danger-bg); color: var(--badge-danger-text); }
             .badge-info    { background-color: var(--badge-info-bg); color: var(--badge-info-text); }


            /* ----- Stili Paginator ----- */
            .paginator-container {
                display: flex;
                justify-content: center; /* Centra i bottoni */
                align-items: center;
                padding: var(--paginator-padding);
                background-color: var(--header-bg-color); /* Sfondo leggero come l'header */
                border-top: var(--table-border-style); /* Linea separatrice dalla tabella */
            }
            .paginator-container button, .paginator-container span {
                display: inline-flex; /* Usa flex per centrare testo/icona */
                justify-content: center;
                align-items: center;
                margin: 0 var(--paginator-button-margin);
                min-width: var(--paginator-button-size);
                height: var(--paginator-button-size);
                padding: 0 10px; /* Padding orizzontale per bottoni non numerici */
                border: var(--paginator-button-border);
                border-radius: var(--paginator-radius);
                background-color: var(--paginator-button-bg);
                color: var(--paginator-button-text);
                font-size: var(--table-font-size);
                cursor: pointer;
                transition: background-color 0.2s ease, color 0.2s ease;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            }
             .paginator-container button:hover {
                 background-color: var(--paginator-button-hover-bg);
                 border-color: #adb5bd;
             }
             .paginator-container button.active {
                 background-color: var(--paginator-button-active-bg);
                 color: var(--paginator-button-active-text);
                 border-color: var(--paginator-button-active-bg);
                 font-weight: 600;
                 cursor: default;
                 box-shadow: none;
             }
             .paginator-container button:disabled,
             .paginator-container button.disabled { /* Aggiunta classe per styling diretto */
                 background-color: var(--paginator-button-disabled-bg);
                 color: var(--paginator-button-disabled-text);
                 border-color: var(--paginator-button-disabled-border);
                 cursor: not-allowed;
                 box-shadow: none;
                 opacity: 0.7;
             }
            .paginator-container span.ellipsis {
                border: none;
                background: none;
                box-shadow: none;
                cursor: default;
                padding: 0 5px;
                min-width: auto;
            }

             /* ----- Tema Scuro (Paginator) ----- */
             :host([theme="dark"]) {
                /* ... (sovrascrittura variabili tema scuro come prima) ... */
                --table-bg-color: #2c3e50; --table-text-color: #ecf0f1; --table-border-color: #34495e;
                --header-bg-color: #34495e; --header-text-color: #ecf0f1; --row-hover-bg-color: #46627f;
                --row-alternate-bg-color: #3a5068;
                --button-bg-color: #3498db; --button-text-color: #ffffff; --button-border-color: #3498db;
                --button-hover-bg-color: #2980b9;
                --badge-success-bg: #27ae60; --badge-success-text: #ffffff; --badge-warning-bg: #f39c12; --badge-warning-text: #ffffff;
                --badge-danger-bg: #e74c3c; --badge-danger-text: #ffffff; --badge-info-bg: #3498db; --badge-info-text: #ffffff;

                /* Sovrascrittura variabili Paginator per tema scuro */
                --paginator-text-color: var(--table-text-color);
                --paginator-button-bg: #3a5068; /* Sfondo bottone */
                --paginator-button-text: var(--table-text-color);
                --paginator-button-border: 1px solid #46627f;
                --paginator-button-hover-bg: #46627f;
                --paginator-button-active-bg: var(--button-bg-color); /* Usa colore primario del tema */
                --paginator-button-active-text: var(--button-text-color);
                --paginator-button-disabled-bg: #2c3e50;
                --paginator-button-disabled-text: #566a7f;
                --paginator-button-disabled-border: #34495e;
            }
            /* Puoi aggiungere altri temi come :host([theme="minimal"]) sovrascrivendo le variabili */
        `;
        this.shadowRoot.appendChild(style);
    }

    _renderTable() {
        // Ridisegna solo la parte della tabella
        this._tableRoot.innerHTML = ""; // Pulisci tabella precedente

        if (!this._columns || this._columns.length === 0) {
            this._tableRoot.innerHTML = "<p>Configurazione colonne mancante.</p>";
            return;
        }
        if (!this._data || this._data.length === 0) {
            this._tableRoot.innerHTML = "<p>Nessun dato da visualizzare per questa pagina.</p>";
            return; // Mostra messaggio se non ci sono dati per la pagina
        }

        const table = document.createElement("table");
        const thead = table.createTHead();
        const headerRow = thead.insertRow();
        this._columns.forEach((col) => {
            const th = document.createElement("th");
            th.textContent = col.title || "";
            headerRow.appendChild(th);
        });

        const tbody = table.createTBody();
        this._data.forEach((rowData, rowIndex) => {
            // Usa this._data che ora ha solo i dati della pagina
            const dataRow = tbody.insertRow();
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

        this._tableRoot.appendChild(table);
    }

    _renderPaginator() {
        this._paginatorRoot.innerHTML = ""; // Pulisci paginator precedente
        const totalP = this.totalPages;
        const currentP = this._currentPage;

        if (totalP <= 1) return; // Non mostrare paginator se c'è solo 1 pagina o meno

        const createButton = (text, pageNum, isDisabled = false, isActive = false, isEllipsis = false) => {
            if (isEllipsis) {
                const span = document.createElement("span");
                span.className = "ellipsis";
                span.textContent = "...";
                return span;
            }

            const btn = document.createElement("button");
            btn.textContent = text;
            btn.disabled = isDisabled;
            if (isDisabled) btn.classList.add("disabled"); // Aggiungi classe per styling diretto
            if (isActive) btn.classList.add("active");

            if (!isDisabled && !isActive) {
                btn.onclick = () => this._goToPage(pageNum);
            }
            return btn;
        };

        // Bottone "Precedente"
        this._paginatorRoot.appendChild(createButton("«", currentP - 1, currentP === 1));

        // Logica per mostrare i numeri di pagina (esempio: max 7 bottoni numerici)
        const maxButtons = 7;
        const buttonsAround = Math.floor((maxButtons - 3) / 2); // Tolgo primo, ultimo, corrente

        if (totalP <= maxButtons) {
            // Mostra tutti i numeri se sono pochi
            for (let i = 1; i <= totalP; i++) {
                this._paginatorRoot.appendChild(createButton(i, i, false, i === currentP));
            }
        } else {
            // Mostra Primo
            this._paginatorRoot.appendChild(createButton(1, 1, false, currentP === 1));

            // Ellipsis iniziale?
            let startPage = Math.max(2, currentP - buttonsAround);
            let endPage = Math.min(totalP - 1, currentP + buttonsAround);

            // Aggiusta se siamo vicini ai bordi
            if (currentP - buttonsAround <= 2) {
                // Vicino all'inizio
                endPage = maxButtons - 2;
            }
            if (currentP + buttonsAround >= totalP - 1) {
                // Vicino alla fine
                startPage = totalP - (maxButtons - 3);
            }

            if (startPage > 2) {
                this._paginatorRoot.appendChild(createButton("...", 0, true, false, true)); // Ellipsis
            }

            // Numeri centrali
            for (let i = startPage; i <= endPage; i++) {
                this._paginatorRoot.appendChild(createButton(i, i, false, i === currentP));
            }

            // Ellipsis finale?
            if (endPage < totalP - 1) {
                this._paginatorRoot.appendChild(createButton("...", 0, true, false, true)); // Ellipsis
            }

            // Mostra Ultimo
            this._paginatorRoot.appendChild(createButton(totalP, totalP, false, currentP === totalP));
        }

        // Bottone "Successivo"
        this._paginatorRoot.appendChild(createButton("»", currentP + 1, currentP === totalP));
    }

    // Metodo centrale per cambiare pagina
    _goToPage(pageNumber, dispatchEvent = true) {
        const totalP = this.totalPages;
        // Valida il numero di pagina richiesto
        const newPage = Math.max(1, Math.min(pageNumber, totalP > 0 ? totalP : 1));

        if (this._currentPage !== newPage || !dispatchEvent) {
            // Cambia solo se la pagina è diversa o forzato
            this._currentPage = newPage;
            this.setAttribute("current-page", newPage); // Aggiorna attributo
            console.log(`Pagina cambiata internamente a: ${newPage}`);

            // Emetti l'evento per notificare l'esterno (fondamentale per AJAX)
            if (dispatchEvent) {
                this.dispatchEvent(
                    new CustomEvent("page-change", {
                        detail: {
                            page: this._currentPage,
                            itemsPerPage: this._itemsPerPage
                        },
                        bubbles: true, // Permette all'evento di "risalire" il DOM
                        composed: true // Permette all'evento di uscire dallo Shadow DOM
                    })
                );
                console.log(`Evento page-change emesso per pagina ${this._currentPage}`);
            }

            // Aggiorna solo l'UI del paginator. La tabella sarà aggiornata
            // quando l'applicazione esterna imposterà la nuova prop 'data'.
            this._renderPaginator();
        }
    }

    // --- Lifecycle Callbacks ---
    connectedCallback() {
        console.log("CustomTable aggiunto al DOM!");
        // Render iniziale se i dati/config sono già disponibili
        // (anche se di solito vengono impostati dopo il connection)
        this._renderTable();
        this._renderPaginator();
    }
    disconnectedCallback() {
        console.log("CustomTable rimosso dal DOM.");
    }
}

customElements.define("custom-table", CustomTable);
