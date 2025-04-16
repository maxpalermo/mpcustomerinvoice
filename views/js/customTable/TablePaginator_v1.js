// TablePaginator.js
class TablePaginator extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: "open" });

        // Stato interno
        this._currentPage = 1;
        this._totalPages = 1;

        // Root UI
        this._container = document.createElement("div");
        this._container.classList.add("paginator-container");
        this.shadowRoot.appendChild(this._container);

        // Stili (adattati da CustomTable)
        this._applyStyles();
    }

    // Attributi osservati
    static get observedAttributes() {
        return ["current-page", "total-pages", "theme"];
    }

    // Callback per cambio attributi
    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;

        switch (name) {
            case "current-page":
                // Aggiorna lo stato interno SOLO se diverso da quello attuale
                // per evitare loop se impostato programmaticamente dopo un evento
                const newPage = parseInt(newValue, 10) || 1;
                if (this._currentPage !== newPage) {
                    this._currentPage = Math.max(1, Math.min(newPage, this._totalPages > 0 ? this._totalPages : 1));
                    this._render(); // Aggiorna UI
                }
                break;
            case "total-pages":
                this._totalPages = parseInt(newValue, 10) || 1;
                // Se la pagina corrente diventa > del nuovo totale, aggiusta
                if (this._currentPage > this._totalPages && this._totalPages > 0) {
                    this._currentPage = this._totalPages;
                    // Non emettere evento qui, l'aggiornamento è una conseguenza
                    // del cambio di totalPages gestito dall'esterno.
                }
                this._render(); // Aggiorna UI
                break;
            case "theme":
                // Il tema è gestito via CSS :host([theme="..."])
                // Potremmo voler forzare un re-render se necessario, ma di solito non serve
                break;
        }
    }

    // Proprietà JS
    get currentPage() {
        return this._currentPage;
    }
    set currentPage(value) {
        const page = parseInt(value, 10) || 1;
        const newPage = Math.max(1, Math.min(page, this._totalPages > 0 ? this._totalPages : 1));
        if (this._currentPage !== newPage) {
            this._currentPage = newPage;
            this.setAttribute("current-page", this._currentPage); // Rifletti attributo
            // Non emettere evento qui, il setter è per controllo esterno
            this._render(); // Aggiorna UI
        }
    }

    get totalPages() {
        return this._totalPages;
    }
    set totalPages(value) {
        const pages = parseInt(value, 10) || 1;
        if (this._totalPages !== pages) {
            this._totalPages = Math.max(1, pages); // Assicura almeno 1 pagina
            this.setAttribute("total-pages", this._totalPages); // Rifletti attributo
            // Aggiusta currentPage se necessario
            if (this._currentPage > this._totalPages) {
                this.currentPage = this._totalPages; // Usa il setter per aggiornare tutto
            } else {
                this._render(); // Aggiorna UI
            }
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

    // Metodi interni
    _applyStyles() {
        const style = document.createElement("style");
        style.textContent = `
            /* Stili specifici per il paginator e variabili CSS */
             :host {
                 display: block; /* o inline-block se si preferisce */
                 font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
                 font-size: 14px;

                /* Variabili Default (tema chiaro/moderno) */
                --paginator-bg-color: #f8f9fa; /* Sfondo container */
                --paginator-padding: 10px 15px;
                --paginator-border-top: 1px solid #e0e0e0;

                --button-bg: #ffffff;
                --button-text: #007bff;
                --button-border: 1px solid #dee2e6;
                --button-hover-bg: #e9ecef;
                --button-hover-border: #adb5bd;
                --button-active-bg: #007bff;
                --button-active-text: #ffffff;
                --button-active-border: #007bff;
                --button-disabled-bg: #f8f9fa;
                --button-disabled-text: #adb5bd;
                --button-disabled-border: #dee2e6;
                --button-size: 36px;
                --button-margin: 2px;
                --button-radius: 4px;
                --ellipsis-color: #6c757d;
            }

            .paginator-container {
                display: flex;
                justify-content: center;
                align-items: center;
                padding: var(--paginator-padding);
                background-color: var(--paginator-bg-color);
                border-top: var(--paginator-border-top);
             }

             /* Stili bottoni ed ellipsis (identici a prima) */
             button, span {
                 /* ... (stessi stili di prima per bottoni e span.ellipsis) ... */
                 display: inline-flex; justify-content: center; align-items: center;
                 margin: 0 var(--button-margin); min-width: var(--button-size); height: var(--button-size);
                 padding: 0 10px; border: var(--button-border); border-radius: var(--button-radius);
                 background-color: var(--button-bg); color: var(--button-text); font-size: inherit;
                 cursor: pointer; transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
                 box-shadow: 0 1px 2px rgba(0,0,0,0.05);
             }
             button:hover {
                 background-color: var(--button-hover-bg);
                 border-color: var(--button-hover-border);
             }
             button.active {
                 background-color: var(--button-active-bg);
                 color: var(--button-active-text);
                 border-color: var(--button-active-border);
                 font-weight: 600; cursor: default; box-shadow: none;
             }
             button:disabled, button.disabled {
                 background-color: var(--button-disabled-bg);
                 color: var(--button-disabled-text);
                 border-color: var(--button-disabled-border);
                 cursor: not-allowed; box-shadow: none; opacity: 0.7;
             }
             span.ellipsis {
                 border: none; background: none; box-shadow: none; cursor: default;
                 padding: 0 5px; min-width: auto; color: var(--ellipsis-color);
             }


             /* ----- Tema Scuro ----- */
             :host([theme="dark"]) {
                 --paginator-bg-color: #34495e;
                 --paginator-border-top: 1px solid #46627f;

                 --button-bg: #3a5068;
                 --button-text: #ecf0f1;
                 --button-border: 1px solid #46627f;
                 --button-hover-bg: #46627f;
                 --button-hover-border: #567a9a;
                 --button-active-bg: #3498db;
                 --button-active-text: #ffffff;
                 --button-active-border: #3498db;
                 --button-disabled-bg: #2c3e50;
                 --button-disabled-text: #566a7f;
                 --button-disabled-border: #34495e;
                 --ellipsis-color: #95a5a6;
             }

              /* ----- Tema Minimal ----- */
             :host([theme="minimal"]) {
                 --paginator-bg-color: transparent;
                 --paginator-border-top: 1px dashed #ccc;
                 --button-bg: transparent;
                 --button-text: #007bff;
                 --button-border: none;
                 --button-hover-bg: #efefef;
                 --button-active-bg: #007bff;
                 --button-active-text: #ffffff;
                 --button-disabled-text: #ccc;
                 --button-disabled-bg: transparent;
                 --button-radius: 50%; /* Cerchi */
                 box-shadow: none;
                 padding: 10px 0;
             }
        `;
        this.shadowRoot.appendChild(style);
    }

    _render() {
        this._container.innerHTML = ""; // Pulisci
        const totalP = this._totalPages;
        const currentP = this._currentPage;

        if (totalP <= 1) return; // Non mostrare nulla se <= 1 pagina

        const createButton = (text, pageNum, isDisabled = false, isActive = false, isEllipsis = false) => {
            // ... (logica createButton identica a prima) ...
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
                // Cambia pagina INTERNAMENTE e EMETTI EVENTO
                btn.onclick = () => this._goToPage(pageNum);
            }
            return btn;
        };

        // Costruzione UI paginator (identica a prima)
        this._container.appendChild(createButton("«", currentP - 1, currentP === 1));
        const maxButtons = 7;
        const buttonsAround = Math.floor((maxButtons - 3) / 2);
        if (totalP <= maxButtons) {
            /* ... mostra tutti ... */ for (let i = 1; i <= totalP; i++) {
                this._container.appendChild(createButton(i, i, false, i === currentP));
            }
        } else {
            /* ... mostra primo, ellipsis, centrali, ellipsis, ultimo ... */
            this._container.appendChild(createButton(1, 1, false, currentP === 1));
            let startPage = Math.max(2, currentP - buttonsAround);
            let endPage = Math.min(totalP - 1, currentP + buttonsAround);
            if (currentP - buttonsAround <= 2) {
                endPage = maxButtons - 2;
            }
            if (currentP + buttonsAround >= totalP - 1) {
                startPage = totalP - (maxButtons - 3);
            }
            if (startPage > 2) {
                this._container.appendChild(createButton("...", 0, true, false, true));
            }
            for (let i = startPage; i <= endPage; i++) {
                this._container.appendChild(createButton(i, i, false, i === currentP));
            }
            if (endPage < totalP - 1) {
                this._container.appendChild(createButton("...", 0, true, false, true));
            }
            this._container.appendChild(createButton(totalP, totalP, false, currentP === totalP));
        }
        this._container.appendChild(createButton("»", currentP + 1, currentP === totalP));
    }

    // Gestisce il click sui bottoni
    _goToPage(pageNumber) {
        const requestedPage = Math.max(1, Math.min(pageNumber, this._totalPages));

        // Aggiorna lo stato e l'UI *prima* di emettere l'evento
        // Questo dà un feedback visivo immediato all'utente
        if (this._currentPage !== requestedPage) {
            this._currentPage = requestedPage;
            this.setAttribute("current-page", this._currentPage); // Aggiorna anche l'attributo
            this._render(); // Ridisegna per mostrare la pagina attiva

            // Emetti l'evento per notificare l'esterno
            console.log(`Paginator: Emitting page-change for page ${this._currentPage}`);
            this.dispatchEvent(
                new CustomEvent("page-change", {
                    detail: { page: this._currentPage },
                    bubbles: true,
                    composed: true
                })
            );
        }
    }

    // Lifecycle
    connectedCallback() {
        this._render(); // Render iniziale
    }
}

customElements.define("table-paginator", TablePaginator);
