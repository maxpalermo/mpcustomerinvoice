// TablePaginator.js (COMPLETO - con Select Items/Page e CSS esterno)

class TablePaginator extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: "open" });

        // Stato interno (con valori di default ragionevoli)
        this._currentPage = 1;
        this._totalPages = 1;
        this._itemsPerPage = 10; // Default items per pagina
        this._pageSizeOptions = [10, 25, 50, 100]; // Default opzioni
        this._selectElement = null; // Riferimento all'elemento select creato

        // Root UI nel Shadow DOM
        this._container = document.createElement("div");
        this._container.classList.add("paginator-container");
        this.shadowRoot.appendChild(this._container);

        // Carica gli stili CSS esterni
        this._loadStyles();
    }

    // Metodo per caricare il file CSS esterno
    _loadStyles() {
        const link = document.createElement("link");
        link.setAttribute("rel", "stylesheet");
        // Assicurati che questo percorso sia corretto rispetto alla posizione dell'HTML
        link.setAttribute("href", "./css/table-paginator.css");
        this.shadowRoot.appendChild(link); // Aggiungi al Shadow DOM
    }

    // Attributi HTML che il componente osserverà per cambiamenti
    static get observedAttributes() {
        return ["current-page", "total-pages", "theme", "items-per-page", "page-size-options"];
    }

    // Callback eseguita quando uno degli attributi osservati cambia
    attributeChangedCallback(name, oldValue, newValue) {
        // Evita aggiornamenti non necessari se il valore non è cambiato
        if (oldValue === newValue) return;

        let needsRender = false; // Flag per ridisegnare l'UI solo se necessario

        switch (name) {
            case "current-page":
                const newPage = parseInt(newValue, 10) || 1;
                // Valida la pagina rispetto al totale attuale
                const validatedPage = Math.max(1, Math.min(newPage, this._totalPages > 0 ? this._totalPages : 1));
                if (this._currentPage !== validatedPage) {
                    this._currentPage = validatedPage;
                    needsRender = true; // Bisogna aggiornare il bottone attivo
                }
                break;
            case "total-pages":
                const newTotalPages = parseInt(newValue, 10) || 1;
                if (this._totalPages !== newTotalPages) {
                    this._totalPages = Math.max(1, newTotalPages); // Assicura almeno 1
                    // Se la pagina corrente è ora fuori range, aggiustala
                    if (this._currentPage > this._totalPages) {
                        this._currentPage = this._totalPages;
                        // Non emettere evento page-change qui, è una conseguenza
                        // del cambio di totalPages gestito dall'esterno.
                    }
                    needsRender = true; // Ridisegna bottoni e stato disabled
                }
                break;
            case "theme":
                // Il tema è gestito via CSS, non serve re-render da JS di solito.
                // Potresti aggiungere logica qui se il tema richiedesse azioni JS.
                break;
            case "items-per-page":
                const newItems = parseInt(newValue, 10);
                // Aggiorna solo se è un numero valido E presente nelle opzioni
                if (!isNaN(newItems) && newItems > 0 && this._pageSizeOptions.includes(newItems)) {
                    if (this._itemsPerPage !== newItems) {
                        this._itemsPerPage = newItems;
                        needsRender = true; // Aggiorna la selezione nel <select>
                    }
                }
                break;
            case "page-size-options":
                try {
                    let optionsArray;
                    // Prova a parsare come JSON array o stringa CSV
                    if (newValue && newValue.startsWith("[")) {
                        optionsArray = JSON.parse(newValue);
                    } else if (newValue) {
                        optionsArray = newValue
                            .split(",")
                            .map((s) => parseInt(s.trim(), 10))
                            .filter((n) => !isNaN(n) && n > 0);
                    } else {
                        optionsArray = [10, 25, 50, 100]; // Default se attributo è vuoto/rimosso
                    }

                    // Aggiorna solo se l'array è valido e diverso da quello attuale
                    if (Array.isArray(optionsArray) && optionsArray.length > 0 && JSON.stringify(this._pageSizeOptions) !== JSON.stringify(optionsArray)) {
                        this._pageSizeOptions = optionsArray;
                        // Se itemsPerPage attuale non è più valido, usa la prima opzione
                        if (!this._pageSizeOptions.includes(this._itemsPerPage)) {
                            this._itemsPerPage = this._pageSizeOptions[0];
                            // Aggiorna l'attributo items-per-page per riflettere questo cambio automatico
                            this.setAttribute("items-per-page", this._itemsPerPage);
                            // Non emettere l'evento, questo è un aggiustamento interno
                        }
                        needsRender = true; // Ridisegna il <select> con nuove opzioni
                    }
                } catch (e) {
                    console.error("Formato page-size-options non valido. Usare array JSON '[10, 25]' o stringa '10,25'.", e);
                }
                break;
        }

        // Se uno dei case ha impostato needsRender a true, ridisegna l'UI
        if (needsRender) {
            this._render();
        }
    }

    // --- Proprietà JS (Getters/Setters) ---

    get currentPage() {
        return this._currentPage;
    }
    set currentPage(value) {
        const page = parseInt(value, 10) || 1;
        // Valida il valore rispetto alle pagine totali
        const newPage = Math.max(1, Math.min(page, this._totalPages > 0 ? this._totalPages : 1));
        // Imposta l'attributo solo se il valore cambia effettivamente
        // Questo triggererà attributeChangedCallback che aggiornerà _currentPage e farà il render
        if (this.getAttribute("current-page") !== String(newPage)) {
            this.setAttribute("current-page", newPage);
        }
    }

    get totalPages() {
        return this._totalPages;
    }
    set totalPages(value) {
        const pages = parseInt(value, 10) || 1;
        const newTotalPages = Math.max(1, pages); // Assicura almeno 1 pagina
        if (this.getAttribute("total-pages") !== String(newTotalPages)) {
            this.setAttribute("total-pages", newTotalPages);
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

    get itemsPerPage() {
        return this._itemsPerPage;
    }
    set itemsPerPage(value) {
        const num = parseInt(value, 10);
        // Controlla validità e presenza nelle opzioni
        if (!isNaN(num) && num > 0 && this._pageSizeOptions.includes(num)) {
            if (this.getAttribute("items-per-page") !== String(num)) {
                this.setAttribute("items-per-page", num);
            }
        } else {
            console.warn(`Valore itemsPerPage (${num}) non valido o non presente nelle opzioni [${this._pageSizeOptions.join(", ")}].`);
        }
    }

    get pageSizeOptions() {
        return this._pageSizeOptions;
    }
    set pageSizeOptions(value) {
        let optionsArray = [];
        // Cerca di normalizzare l'input in un array di numeri
        if (Array.isArray(value)) {
            optionsArray = value.map((v) => parseInt(v, 10)).filter((n) => !isNaN(n) && n > 0);
        } else if (typeof value === "string") {
            optionsArray = value
                .split(",")
                .map((s) => parseInt(s.trim(), 10))
                .filter((n) => !isNaN(n) && n > 0);
        }

        if (optionsArray.length > 0) {
            // Converte in stringa JSON per l'attributo per coerenza con attributeChangedCallback
            const attributeValue = JSON.stringify(optionsArray);
            if (this.getAttribute("page-size-options") !== attributeValue) {
                this.setAttribute("page-size-options", attributeValue);
            }
        } else {
            console.warn("pageSizeOptions deve essere un array di numeri positivi o una stringa CSV.");
        }
    }

    // --- Metodi Interni ---

    // Metodo principale per disegnare/aggiornare l'interfaccia del paginator
    _render() {
        this._container.innerHTML = ""; // Pulisci il contenuto precedente

        // --- 1. Crea e Aggiungi il Selettore Items Per Pagina ---
        const selectorContainer = document.createElement("div");
        selectorContainer.classList.add("items-per-page-selector");

        const label = document.createElement("label");
        // Usa slot per permettere personalizzazione etichetta? Per ora testo fisso.
        label.textContent = "Mostra:";
        label.htmlFor = "itemsPerPageSelect"; // Accessibilità

        this._selectElement = document.createElement("select");
        this._selectElement.id = "itemsPerPageSelect";

        // Popola le opzioni dal nostro stato interno
        this._pageSizeOptions.forEach((optionValue) => {
            const option = document.createElement("option");
            option.value = optionValue;
            option.textContent = `${optionValue} elementi`; // Testo opzione
            // Seleziona l'opzione che corrisponde allo stato corrente
            if (optionValue === this._itemsPerPage) {
                option.selected = true;
            }
            this._selectElement.appendChild(option);
        });

        // Rimuovi vecchio listener se esiste prima di aggiungerne uno nuovo
        // (anche se con innerHTML='' non dovrebbe servire, è una buona pratica)
        // this._selectElement.removeEventListener('change', this._handleItemsPerPageChange); // Non serve con innerHTML
        this._selectElement.addEventListener("change", this._handleItemsPerPageChange.bind(this));

        selectorContainer.appendChild(label);
        selectorContainer.appendChild(this._selectElement);
        this._container.appendChild(selectorContainer); // Aggiungi al container principale

        // --- 2. Crea e Aggiungi i Bottoni di Paginazione ---
        const totalP = this._totalPages;
        const currentP = this._currentPage;

        // Non mostrare i bottoni se c'è solo una pagina o meno
        if (totalP <= 1) return;

        // Contenitore separato per i bottoni
        const buttonContainer = document.createElement("div");
        buttonContainer.classList.add("page-buttons");

        // Funzione helper interna per creare bottoni/ellipsis
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
            if (isDisabled) btn.classList.add("disabled"); // Classe per styling disabilitato
            if (isActive) btn.classList.add("active"); // Classe per styling attivo
            // Aggiungi handler solo se non disabilitato e non attivo
            if (!isDisabled && !isActive) {
                btn.onclick = () => this._goToPage(pageNum);
            }
            return btn;
        };

        // Crea bottone "Precedente" (<<)
        buttonContainer.appendChild(
            createButton("«", currentP - 1, currentP === 1) // Disabilitato se siamo alla prima pagina
        );

        // Logica per visualizzare i numeri di pagina (con ellipsis)
        const maxButtons = 7; // Numero massimo di bottoni pagina da mostrare (inclusi primo/ultimo)
        const buttonsAround = Math.floor((maxButtons - 3) / 2); // Bottoni intorno a quello corrente

        if (totalP <= maxButtons) {
            // Mostra tutti i numeri se sono pochi
            for (let i = 1; i <= totalP; i++) {
                buttonContainer.appendChild(createButton(String(i), i, false, i === currentP));
            }
        } else {
            // Logica complessa per primo, ..., numeri centrali, ..., ultimo
            // Mostra Primo
            buttonContainer.appendChild(createButton(String(1), 1, false, currentP === 1));

            // Calcola range di pagine centrali da mostrare
            let startPage = Math.max(2, currentP - buttonsAround);
            let endPage = Math.min(totalP - 1, currentP + buttonsAround);

            // Aggiusta il range se siamo troppo vicini ai bordi
            const pagesInBetween = endPage - startPage + 1;
            const neededInBetween = maxButtons - 3; // Spazio per 1, ..., N

            if (currentP - buttonsAround <= 2) {
                // Vicino all'inizio
                endPage = Math.min(totalP - 1, 1 + neededInBetween);
            }
            if (currentP + buttonsAround >= totalP - 1) {
                // Vicino alla fine
                startPage = Math.max(2, totalP - neededInBetween);
            }

            // Mostra ellipsis iniziale se necessario
            if (startPage > 2) {
                buttonContainer.appendChild(createButton("...", 0, true, false, true)); // Ellipsis
            }

            // Mostra numeri centrali
            for (let i = startPage; i <= endPage; i++) {
                buttonContainer.appendChild(createButton(String(i), i, false, i === currentP));
            }

            // Mostra ellipsis finale se necessario
            if (endPage < totalP - 1) {
                buttonContainer.appendChild(createButton("...", 0, true, false, true)); // Ellipsis
            }

            // Mostra Ultimo
            buttonContainer.appendChild(createButton(String(totalP), totalP, false, currentP === totalP));
        }

        // Crea bottone "Successivo" (>>)
        buttonContainer.appendChild(
            createButton("»", currentP + 1, currentP === totalP) // Disabilitato se siamo all'ultima pagina
        );

        // Aggiungi il contenitore dei bottoni al container principale
        this._container.appendChild(buttonContainer);
    }

    // Gestisce il click sui bottoni di pagina numerici, prev, next
    _goToPage(pageNumber) {
        // Valida la pagina richiesta
        const requestedPage = Math.max(1, Math.min(pageNumber, this._totalPages > 0 ? this._totalPages : 1));

        // Prosegui solo se la pagina è effettivamente cambiata
        if (this._currentPage !== requestedPage) {
            // Aggiorna lo stato interno PRIMA di emettere l'evento
            // L'UI si aggiornerà grazie alla chiamata a _render() qui sotto
            this._currentPage = requestedPage;
            // Aggiorna anche l'attributo per coerenza e osservabilità esterna
            this.setAttribute("current-page", this._currentPage);

            // Ridisegna l'UI per riflettere il nuovo stato attivo/disabilitato
            // NOTA: Questo re-render potrebbe essere ottimizzato aggiornando solo
            // le classi active/disabled sui bottoni esistenti invece di fare innerHTML = ''
            this._render();

            // Emetti l'evento per notificare l'applicazione esterna
            console.log(`Paginator: Emitting page-change for page ${this._currentPage}`);
            this.dispatchEvent(
                new CustomEvent("page-change", {
                    detail: { page: this._currentPage }, // Invia il numero di pagina richiesto
                    bubbles: true, // Permette all'evento di "risalire" il DOM
                    composed: true // Permette all'evento di uscire dallo Shadow DOM
                })
            );
        }
    }

    // Gestisce il cambio di selezione nel <select> items per pagina
    _handleItemsPerPageChange(event) {
        const newSize = parseInt(event.target.value, 10);

        // Prosegui solo se il valore è valido e diverso da quello attuale
        if (!isNaN(newSize) && newSize > 0 && this._itemsPerPage !== newSize) {
            // Aggiorna lo stato interno e l'attributo
            this._itemsPerPage = newSize;
            this.setAttribute("items-per-page", newSize);

            // Non è necessario chiamare _render() qui perché l'attributo
            // items-per-page è osservato e attributeChangedCallback chiamerà _render().
            // Tuttavia, per sicurezza e immediatezza potremmo chiamarlo:
            // this._render(); // Assicura che il select mostri il valore corretto

            // Emetti l'evento per notificare l'applicazione esterna
            console.log(`Paginator: Emitting items-per-page-change with value ${newSize}`);
            this.dispatchEvent(
                new CustomEvent("items-per-page-change", {
                    detail: { itemsPerPage: newSize },
                    bubbles: true,
                    composed: true
                })
            );

            // NOTA IMPORTANTE: Cambiare gli items per pagina implica quasi sempre
            // che l'applicazione esterna debba tornare alla pagina 1.
            // Il paginator NON forza questo cambio di pagina, solo notifica
            // il cambio di dimensione. Sarà l'handler dell'evento nell'applicazione
            // principale a gestire il reset della pagina e il caricamento dei dati.
        }
    }

    // --- Lifecycle Callback ---
    connectedCallback() {
        console.log("TablePaginator (CSS esterno + Select) aggiunto al DOM!");
        // Esegui il render iniziale quando l'elemento è connesso al DOM
        // Verifica se è già stato fatto per evitare render multipli se l'elemento
        // viene spostato nel DOM invece che aggiunto per la prima volta.
        if (!this.shadowRoot.querySelector(".paginator-container").hasChildNodes()) {
            this._render();
        }
    }
}

// Registra il custom element nel browser
customElements.define("table-paginator", TablePaginator);
