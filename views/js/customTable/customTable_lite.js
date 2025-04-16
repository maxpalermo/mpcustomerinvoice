class CustomTable extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: "open" });

        this._columns = [];
        this._data = [];

        // Container principale per stile e tabella
        this._container = document.createElement("div");
        this._container.classList.add("table-container"); // Aggiungi una classe al container
        this.shadowRoot.appendChild(this._container);

        // Applica gli stili
        this._applyStyles();
    }

    // --- Gestione Tema ---
    static get observedAttributes() {
        // Osserva cambiamenti all'attributo 'theme'
        return ["theme"];
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === "theme") {
            // Potresti fare cose qui se il cambio tema richiedesse logica JS,
            // ma per ora il CSS :host([theme="..."]) gestisce lo stile.
            console.log(`Tema cambiato da ${oldValue} a ${newValue}`);
        }
    }

    get theme() {
        return this.getAttribute("theme");
    }

    set theme(value) {
        if (value) {
            this.setAttribute("theme", value.toLowerCase());
        } else {
            // Rimuovi l'attributo se il valore è nullo o vuoto per tornare al default
            this.removeAttribute("theme");
        }
    }

    // --- Gestione Dati e Colonne ---
    set columns(value) {
        if (!Array.isArray(value)) {
            console.error("Le colonne devono essere un array di oggetti.");
            return;
        }
        this._columns = value;
        this._render();
    }
    get columns() {
        return this._columns;
    }

    set data(value) {
        if (!Array.isArray(value)) {
            console.error("I dati devono essere un array di oggetti.");
            return;
        }
        this._data = value;
        this._render();
    }
    get data() {
        return this._data;
    }

    // --- Metodi Interni ---

    _applyStyles() {
        const style = document.createElement("style");
        style.textContent = `
            /* ----- Variabili CSS per Tematizzazione (Tema Default Moderno) ----- */
            :host {
                /* Colori Base */
                --table-bg-color: #ffffff;
                --table-text-color: #333333;
                --table-border-color: #e0e0e0;
                --header-bg-color: #f8f9fa;
                --header-text-color: #212529;
                --row-hover-bg-color: #f1f3f5;
                --row-alternate-bg-color: #f8f9fa; /* Sfondo leggero per righe alternate */

                /* Spaziatura e Bordi */
                --cell-padding: 12px 15px; /* Più padding verticale e orizzontale */
                --table-border-radius: 8px; /* Bordi arrotondati per il contenitore */
                --table-border-style: 1px solid var(--table-border-color);
                --row-border-bottom: 1px solid var(--table-border-color); /* Solo bordi orizzontali */

                /* Font */
                --table-font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
                --table-font-size: 14px;
                --header-font-weight: 600;

                /* Stili Elementi Interni (Bottoni, Badge, etc.) */
                --button-bg-color: #007bff;
                --button-text-color: white;
                --button-border-color: #007bff;
                --button-hover-bg-color: #0056b3;
                --button-padding: 6px 12px;
                --button-border-radius: 4px;

                --badge-success-bg: #d4edda;
                --badge-success-text: #155724;
                --badge-warning-bg: #fff3cd;
                --badge-warning-text: #856404;
                --badge-danger-bg: #f8d7da;
                --badge-danger-text: #721c24;
                --badge-info-bg: #d1ecf1;
                --badge-info-text: #0c5460;
                --badge-padding: .3em .6em;
                --badge-font-size: 75%;
                --badge-border-radius: .25rem;

                /* Stile Generale Host */
                display: block;
                font-family: var(--table-font-family);
                font-size: var(--table-font-size);
                color: var(--table-text-color);
                background-color: var(--table-bg-color);
                border-radius: var(--table-border-radius);
                overflow: hidden; /* Nasconde contenuto che esce dai bordi arrotondati */
                box-shadow: 0 2px 8px rgba(0,0,0,0.05); /* Ombra leggera */
            }

            .table-container {
                 overflow-x: auto; /* Scroll orizzontale solo se necessario */
                 padding: 5px; /* Piccolo padding intorno alla tabella per l'ombra */
            }

            table {
                width: 100%;
                border-collapse: collapse;
                border-spacing: 0; /* Rimuove spazio tra bordi */
                background-color: var(--table-bg-color);
            }

            th, td {
                padding: var(--cell-padding);
                text-align: left;
                vertical-align: middle;
                /* Rimuovi bordi verticali, tieni solo quelli orizzontali sotto */
                border-top: none;
                border-left: none;
                border-right: none;
                border-bottom: var(--row-border-bottom);
            }

            th {
                background-color: var(--header-bg-color);
                color: var(--header-text-color);
                font-weight: var(--header-font-weight);
                /* text-transform: uppercase; */ /* Opzione per header maiuscolo */
                font-size: calc(var(--table-font-size) * 0.95); /* Leggermente più piccolo */
                border-bottom-width: 2px; /* Bordo inferiore più marcato per l'header */
            }

            /* Stile righe alternate nel corpo */
            tbody tr:nth-child(even) {
                background-color: var(--row-alternate-bg-color);
            }

            /* Effetto Hover sulle righe del corpo */
            tbody tr:hover {
                background-color: var(--row-hover-bg-color);
            }

            /* Stili per elementi comuni (Bottoni, Badge, ecc.) usando le variabili */
            button {
                padding: var(--button-padding);
                cursor: pointer;
                border: 1px solid var(--button-border-color);
                background-color: var(--button-bg-color);
                color: var(--button-text-color);
                border-radius: var(--button-border-radius);
                font-size: inherit; /* Eredita dimensione font */
                transition: background-color 0.2s ease; /* Transizione hover */
            }
            button:hover {
                background-color: var(--button-hover-bg-color);
            }
            /* Stile specifico per bottone danger (esempio) */
            button.danger {
                 background-color: var(--badge-danger-bg); /* Usa colori badge per consistenza */
                 border-color: var(--badge-danger-text);
                 color: var(--badge-danger-text);
            }
             button.danger:hover {
                 background-color: #e4a0a6; /* Scurisci leggermente al hover */
             }


            .badge {
                display: inline-block;
                padding: var(--badge-padding);
                font-size: var(--badge-font-size);
                font-weight: 700;
                line-height: 1;
                text-align: center;
                white-space: nowrap;
                vertical-align: baseline;
                border-radius: var(--badge-border-radius);
            }
            .badge-success { background-color: var(--badge-success-bg); color: var(--badge-success-text); }
            .badge-warning { background-color: var(--badge-warning-bg); color: var(--badge-warning-text); }
            .badge-danger  { background-color: var(--badge-danger-bg); color: var(--badge-danger-text); }
            .badge-info    { background-color: var(--badge-info-bg); color: var(--badge-info-text); }


            /* ----- Tema Scuro (Dark Theme) ----- */
            :host([theme="dark"]) {
                 /* Sovrascrivi le variabili per il tema scuro */
                --table-bg-color: #2c3e50; /* Blu scuro / grigio */
                --table-text-color: #ecf0f1; /* Grigio chiaro */
                --table-border-color: #34495e; /* Grigio più scuro */
                --header-bg-color: #34495e;
                --header-text-color: #ecf0f1;
                --row-hover-bg-color: #46627f; /* Blu leggermente più chiaro */
                --row-alternate-bg-color: #3a5068; /* Sfondo alternato scuro */

                 /* Bottoni scuri */
                --button-bg-color: #3498db; /* Blu più chiaro */
                --button-text-color: #ffffff;
                --button-border-color: #3498db;
                --button-hover-bg-color: #2980b9;

                 /* Badge scuri */
                --badge-success-bg: #27ae60; color: #ffffff;
                --badge-warning-bg: #f39c12; color: #ffffff;
                --badge-danger-bg: #e74c3c; color: #ffffff;
                --badge-info-bg: #3498db; color: #ffffff;
            }

             /* Esempio altro tema: "minimal" */
             :host([theme="minimal"]) {
                 --table-border-radius: 0px;
                 --table-border-style: none;
                 --row-border-bottom: 1px dashed #ccc;
                 --header-bg-color: transparent;
                 --header-font-weight: 700;
                 --row-alternate-bg-color: transparent;
                 --row-hover-bg-color: #efefef;
                 box-shadow: none;
                 padding: 0;
             }
        `;
        this.shadowRoot.appendChild(style);
    }

    _render() {
        if (!this._columns || this._columns.length === 0 || !this._data) {
            this._container.innerHTML = "<p>Configurazione colonne o dati mancanti.</p>";
            return;
        }

        // Pulisci il contenuto precedente
        this._container.innerHTML = ""; // Pulisce il container, non shadowRoot direttamente

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
            const dataRow = tbody.insertRow();
            this._columns.forEach((col) => {
                const td = dataRow.insertCell();
                const cellValue = rowData[col.key];
                if (typeof col.render === "function") {
                    const content = col.render(cellValue, rowData, rowIndex);
                    // Gestione migliorata del contenuto
                    td.innerHTML = ""; // Pulisci prima di appendere
                    if (content instanceof Node) {
                        td.appendChild(content);
                    } else if (content !== null && content !== undefined) {
                        // Se è stringa HTML o testo semplice, usa textContent per sicurezza
                        // Se serve *davvero* HTML, usare innerHTML con cautela
                        td.textContent = content;
                    }
                } else {
                    td.textContent = cellValue !== undefined && cellValue !== null ? cellValue : "";
                }
            });
        });

        this._container.appendChild(table); // Aggiungi la tabella al container
    }

    connectedCallback() {
        console.log("CustomTable aggiunto al DOM!");
    }
    disconnectedCallback() {
        console.log("CustomTable rimosso dal DOM.");
    }
}

customElements.define("custom-table", CustomTable);
