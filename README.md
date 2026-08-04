# mpcustomerinvoice

Modulo per gestire i codici della fatturazione elettronica.

## Changelog

### 1.5.120

- **Stampe Modulo Dalavoro: Fattura (`FATTURA WEB/D`) & Nota di Vendita (`NOTA VENDITA WEB`)**:
    - Realizzazione completa dei template di stampa nativi TCPDF per **Fattura** in [`src/PrintPdf/Templates/Invoices/Dalavoro/`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/Templates/Invoices/Dalavoro/) e **Nota di Vendita** in [`src/PrintPdf/Templates/Deliveries/Dalavoro/`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/Templates/Deliveries/Dalavoro/).
    - **Risoluzione Pagina Bianca Iniziale**: Eliminazione della chiamata ridondante ad `AddPage()` all'inizio di `drawDocument()`, rendendo il documento perfettamente centrato a partire da Pagina 1.
    - **Gestione Commissioni Pagamento (`mp_payment_fee_order`)**: Lettura di `total_order`, `fee_amount` e `tax_included` dalla tabella `mp_payment_fee_order`. Nella tabella ordini del backoffice, l'importo base dell'ordine viene estratto da `total_order` poichè `orders` contiene già il totale finale con commissione. Se `tax_included = 1`, viene eseguito prima lo scorporo IVA (`fee_excl = fee_amount / (1 + vat_rate / 100)`), sommando `fee_excl` all'imponibile senza IVA per poi procedere al calcolo IVA ed arrotondamento sul totale pagato dal cliente.
    - **Esenzione IVA & Clienti Esteri**: Gestione automatica esenzione per fatture estere/NI7 con dicitura **`Cessioni CEE art.41 DL.331/93`** nel riquadro IVA e colonna IVA `NI7`.

### 1.4.119

- **Configurazione Personalizzata WebPrint per Operatore (Employee)**:
    - Le impostazioni del relè di stampa WebPrint (Abilitazione switch, Host, Porta ed associazione stampanti per Ordine, Fattura, Spedizione, Indirizzo e Segnacollo BRT) sono ora salvate e caricate **per ciascun operatore** (`id_employee`).
    - Di default la stampa diretta WebPrint è **disattivata** (`ENABLE = 0`) per tutti gli operatori.
    - Aggiunto il selettore dell'operatore con menu a tendina nella pagina di configurazione (`configuration.html.twig`) ed il caricamento dinamico dei valori via AJAX.
    - Aggiornati `executePrint()` ed `executeBatchPrint()` in `MpPrintDialog.js`: se l'operatore attiva la stampa diretta WebPrint ma avvia la stampa di un documento per cui non ha specificato una stampante, l'operazione viene bloccata mostrando un avviso d'errore esplicito.
    - [link](https://github.com/micwallace/WebPrint)

### 1.4.118

- **Integrazione Stampa Diretta WebPrint JS (`micwallace/WebPrint`)**:
    - Inclusa la libreria client [`webprint.js`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/webprint.js) per la comunicazione diretta HTTP POST / WebSocket con il relè di stampa locale.
    - Incluso il file eseguibile [`WebPrint.jar`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/download/WebPrint.jar) nel modulo con il pulsante **"Scarica Servizio WebPrint (.jar)"** nella pagina di configurazione.
    - Aggiunte le configurazioni `MPCUSTOMERINVOICE_WEBPRINT_*` (switch attivo, host, porta, stampanti per Ordine, Fattura, Spedizione, Indirizzo e Segnacollo BRT) in [`AdminMpCustomerInvoice.php`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/controllers/admin/AdminMpCustomerInvoice.php).
    - Aggiunta la card di configurazione WebPrint ed il pulsante _"Rileva Stampanti Collegate"_ in [`configuration.html.twig`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/twig/admin/configuration.html.twig).
    - Aggiornati `executePrint()` ed `executeBatchPrint()` in [`MpPrintDialog.js`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/MpPrintDialog.js) per inviare direttamente i PDF alle stampanti selezionate senza anteprima del browser.
- **Bottoni di Stampa Separati & Fix Copie / Rilevamento Documenti**:
    - Inserito lo switch di configurazione per i bottoni di stampa separati nel container `.order-actions` della pagina ordine.
    - Bloccata la propagazione degli eventi sul campo del numero di copie (`#mp-print-copies-input-address`) ed invio corretto del parametro `copies`.
    - Risolta l'eccezione 500 fatale sostituendo la chiamata al metodo inesistente `hasDeliveryNumber()` in `AdminMpCustomerInvoice.php`.

### 1.4.117

- **Validazione & Correzione Indirizzi Corrotti Ordine (`OrderAddressValidator.php`, `fix_order_address.html.twig`)**:
    - Intercettati i tentativi di apertura della scheda ordine (`/sell/orders/{id}/view` ed `AdminOrders`) per verificare la presenza ed integrità degli indirizzi di spedizione e fatturazione nel database (prevenendo il crash 500 `Warning: Trying to access array offset on value of type null` in `GetOrderForViewingHandler.php`).
    - Creata la nuova pagina di correzione guidata `showFixOrderAddressPage`: mostra il riepilogo dell'ordine, evidenzia gli indirizzi non trovati o non validi, permette la selezione rapida di un indirizzo valido del cliente o il reindirizzamento per la creazione di un nuovo indirizzo.

### 1.4.116

- **Selezione Multipla Ordini & Cambio Stato a Blocchi (`MpBatchProgressDialog.js` & `adminTableOrders.js`)**:
    - Inserita la colonna di selezione con checkbox multipli come prima colonna nella tabella ordini (con spunta "Seleziona Tutti" nell'intestazione).
    - Aggiunta nella toolbar della tabella la select degli stati ordine ed il pulsante **"Cambia Stato ordine"**.
    - Creata la classe componente JS riutilizzabile ed indipendente `MpBatchProgressDialog.js` con interfaccia moderna, statistiche in tempo reale, barra di progresso percentuale, log dettagliato dell'elaborazione e pulsante **STOP (Interrompi)** per l'annullamento immediato delle chiamate Fetch/AJAX.
    - Implementato l'endpoint AJAX `ajaxProcessChangeOrderStatus` in `AdminMpCustomerInvoice.php` tramite `OrderHistory`.

### 1.4.115

- **Pulsante "Borderò" nella Toolbar Tabella Ordini (`AdminMpCustomerInvoice.php` & `adminTableOrders.js`)**:
    - Verificato l'abbinamento ed attivazione del modulo `mpbrtrestapishipments` (`Module::isInstalled` && `Module::isEnabled`).
    - Inserito il pulsante primario **Borderò** nella toolbar delle azioni della tabella ordini per accedere direttamente alla scheda spedizioni del modulo BRT (`AdminMpBrtRestApiShipments&tab=shipments`).

### 1.4.114

- **Styling Dinamico Badge Colonna "Stato" (`adminTableOrders.js` & `AdminMpCustomerInvoice.php`)**:
    - Colorato lo sfondo del badge della colonna **Stato** utilizzando il colore della tabella `order_state.color`.
    - Calcolato dinamicamente il colore del testo (bianco o scuro) tramite algoritmo di luminosità relativa YIQ, garantendo la massima leggibilità su qualsiasi colore di sfondo.

### 1.4.113

- **Restyling Modale di Stampa con Schede Moderne (Card UI) & Componente Indipendente (`MpPrintDialog.js`)**:
    - Sostituzione completa dei radio button tradizionali con schede interattive moderne dotate di icone Material, badge colorati e spunte di selezione `check_circle`.
    - Inserimento dell'azione AJAX `ajaxProcessGetOrderPrintInfo` per determinare la visibilità dinamica:
        - **Fattura** (verde) per ordini con fattura vs **Nota di vendita** (viola) per ordini senza fattura.
        - **Etichetta Indirizzo** (ambra) con campo per il numero di copie integrato con pulsanti stepper (`+` e `-`).
        - **Segnacollo Bartolini** (rosso BRT) visibile esclusivamente se il modulo BRT è attivo e l'etichetta PDF è già stata generata per l'ordine.
    - Piena compatibilità e riutilizzo autonomo sia dal pulsante della tabella ordini che dal pulsante **Stampe** della barra strumenti dell'ordine (`AdminOrders`).

### 1.4.112

- **Integrazione Condizionale "Segnacollo Bartolini" (`MpPrintDialog.js`) & Fix Fallback Backend (`AdminMpCustomerInvoice.php`)**:
    - Mostrata l'opzione "Segnacollo Bartolini" nel modale di stampa solo se il modulo `mpbrtrestapishipments` è installato ed attivo (`isBrtModuleActive`).
    - Mostrata la casella del numero di copie esclusivamente quando viene selezionata l'opzione "Etichetta Indirizzo".
    - Aggiunto il gestore esplicito per `document_type = 'brt'` nel controller `AdminMpCustomerInvoice.php` (`ajaxProcessRenderPdfDocument`) per eliminare l'errore "Tipo documento non valido per la stampa" e consentire il recupero diretto del segnacollo PDF dal database BRT.

### 1.4.111

- **Fix Recupero Posizione Prodotto Magazzino & Logica Generazione Documenti**:
    - **Ubicazione Prodotto (`ExportManager.php` e `PdfOrderBody.php`)**: Risolto il problema per cui `$product['location']` risultava vuoto quando la posizione in magazzino è registrata sulla tabella principale `ps_product.location` invece che su `ps_stock_available`. Aggiunto il fallback dinamico di query SQL su `ps_product`.
    - **Generazione Automatica Documenti (`GenerateDocumentRestrictions.php`)**: Perfezionata la gestione della creazione e rimozione automatica di Fattura e Nota di Consegna (DDT) nei cambi stato ordine in base alle preferenze del modulo ed ai dati fiscali del cliente.

### 1.4.110

- **Aggiornamento Override Db (`override/classes/db/Db.php`) e Ottimizzazione Restrizioni Generazione Documenti (`GenerateDocumentRestrictions.php`)**:
    - Aggiornato l'override della classe `Db` per la gestione avanzata e sicura delle query al database (inclusa validazione e supporto prefissi tabelle).
    - Affinata la verifica dei requisiti di fatturazione in `GenerateDocumentRestrictions::isInvoiceRequired()` e `checkCustomerInvoiceFields()`, aggiungendo il controllo obbligatorio dell'indirizzo di fatturazione (`id_address_invoice`) e correggendo la sintassi della query SQL.

### 1.4.109

- **Occultamento Elementi Nativi Nota di Consegna (`[data-role="view-delivery-slip"]`)**:
    - Aggiunto il selettore `[data-role="view-delivery-slip"]` in `theme-override.css` ed in `MpPrintDialog.js` per nascondere i pulsanti nativi della nota di consegna nella pagina dell'ordine.

### 1.4.108

- **Occultamento Elementi Nativi Fattura Ordine (`[data-role="view-invoice"]`)**:
    - Estesa la regola CSS in `theme-override.css` ed il listener JS in `MpPrintDialog.js` per nascondere sia `.js-print-order-view-page` che l'elemento nativo `[data-role="view-invoice"]` nella pagina dell'ordine.

### 1.4.107

- **Occultamento Pulsante Stampa Nativo Ordine (`.js-print-order-view-page`)**:
    - Inserita la regola CSS `display: none !important;` in `theme-override.css` per nascondere il pulsante `.js-print-order-view-page` nella pagina del dettaglio ordine.
    - Aggiunta la funzione JS `hideNativePrintButtons()` in `MpPrintDialog.js` per garantire l'occultamento dinamico anche in caso di caricamento differito.

### 1.4.106

- **Rimozione Pulsante Etichette e Unificazione Azioni Stampa (`adminTableOrders.js`)**:
    - Rimosso il pulsante ridondante etichette (`.js-order-action-label`) dalla tabella ordini.
    - Tutta la logica di apertura del dialog unificato di stampa e scelta documenti/etichette è ora associata esclusivamente al pulsante **Stampa** (`.js-order-action-print`).
    - Riorganizzata la griglia delle azioni della tabella con layout orizzontale compatto a 3 pulsanti (Vedi ordine, Stampa, Esporta).

### 1.4.105

- **Riconoscimento Avanzato Contrassegno (`PrintPdfAddress.php`)**:
    - Estesa la funzione `isCashOnDelivery()` per analizzare sia l'oggetto `Order` che i dati restituiti da `$orderData` / `$invoice`.
    - Aggiunti controlli sulle spese contrassegno (`fees`), moduli aggiuntivi (`mppaymentswithfees`, `cashondeliverywithfee`) e parole chiave dinamiche (`contrassegno`, `cash on delivery`, `cod`, `alla consegna`, `contanti alla consegna`).

### 1.4.104

- **Abbassamento 1 cm Sezione Ordine e Indirizzo (`PrintPdfAddress.php`)**:
    - Traslati verso il basso di 10 mm (1 cm) i box Numero Ordine, Progressivo Copia ed i Dati Indirizzo, lasciando invariata la posizione in basso di Telefono, Codice a Barre e Contrassegno.

### 1.4.103

- **Centratura Indirizzo, Font Ingrandito e Box Telefono sopra Barcode (`PrintPdfAddress.php`)**:
    - Centrate tutte le righe dell'indirizzo (Via, Cap - Città - Provincia, Nazione) con font ingrandito a 13pt.
    - Inserito il numero di telefono in un box rettangolare dedicato con bordo posizionato direttamente sopra il codice a barre a destra.

### 1.4.102

- **Garantita Singola Pagina Rigida per Copia Etichetta (`PrintPdfAddress.php`)**:
    - Disabilitata l'AutoPageBreak di TCPDF (`SetAutoPageBreak(false, 0)`) ed introdotto un calcolo dinamico dell'altezza delle celle e dei font size in base allo spazio utile rimanente tra i box dell'ordine ed il footer.
    - Ciascuna copia dell'etichetta viene stampata rigorosamente su una sola ed unica pagina PDF fisica.

### 1.4.101

- **Fix Spaziatura Logo, Font Indirizzo e Condizionale Contrassegno (`PrintPdfAddress.php`)**:
    - Calcolata dinamicamente l'altezza reale del logo per posizionare i box Ordine e Copia completamente al di sotto, evitando qualsiasi sovrapposizione.
    - Ingranditi i caratteri dell'indirizzo di spedizione a 11.5pt (e 12pt per il telefono) per una migliore visibilità.
    - Aggiunta la funzione `isCashOnDelivery()` per fare in modo che il box **TOTALE DA PAGARE** compaia esclusivamente sugli ordini pagati in contrassegno.

### 1.4.100

- **Restyling Completo Stampa Etichetta Spedizione (`PrintPdfAddress.php`)**:
    - Riscritto il layout PDF dell'etichetta indirizzo per rispecchiare fedelmente l'immagine fornita.
    - Inseriti logo centrato in alto, box bordati per ID Ordine e Numero Copia, intestatario centrato (`COMPANY` + `NOME E COGNOME`), indirizzo completo in maiuscolo, nazione e telefono.
    - Inserito il footer con box bordato **TOTALE DA PAGARE** a sinistra e **Codice a barre (Code128)** con numerazione progressiva a destra.

### 1.4.99

- **Posizionamento Campo Location/Ubicazione in Rosso Sotto la Combinazione**:
    - Aggiunto il recupero automatico del campo `location` (ubicazione del prodotto/combinazione) da `StockAvailable` e DB in `ExportManager.php`.
    - Aggiornato `PdfOrderBody.php` per posizionare il campo `location` in **rosso** sotto la combinazione/varianti (stampata in **blu**) nella colonna RIFERIMENTO della tabella prodotti del PDF.

### 1.4.98

- **Correzione Completa Logica di Stampa PDF Documento Ordine**:
    - Risolto un bug nell'estrazione dei dati dove `orderData['invoice']` valutava a vuoto. Normalizzati i dati in `PrintManager.php` per garantire il popolamento completo di Header, Indirizzi, Info Cliente e Totali.
    - Implementato in `PdfOrderBody.php` il rendering completo della tabella prodotti (Immagine, Riferimento, Ubicazione Magazzino in rosso, Attributi in blu, Nome Prodotto, Qta in blu, Magazzino in rosso, Stato Verifica ARR e Prezzo unitario).
    - Aggiunto il badge rosso **`V`** di fianco al codice cliente (`DL...`) quando la fattura è richiesta.

### 1.4.97

- **Fix Caricamento Assets & Listener Globale Etichette Ordini (`.js-order-action-label`)**:
    - Risolto un problema per cui `MpPrintDialog.js` non veniva caricato nelle pagine admin del controller del modulo `AdminMpCustomerInvoice`.
    - Inserito in `MpPrintDialog.js` un event listener delegato sul `document` per intercettare automaticamente tutti i click sui pulsanti `.js-order-action-label` e `.js-order-action-print` ovunque si trovino.
    - Perfezionata l'estrazione sicura di `data-order-id` sia via jQuery che via DOM dataset.

### 1.4.96

- **Estrapolazione Componente Standalone `MpPrintDialog` (`MpPrintDialog.js`)**:
    - Estrapolata la gestione del modale di stampa e dell'esecuzione delle chiamate AJAX in una classe JS autonoma e riusabile `MpPrintDialog` in `views/assets/js/admin/MpPrintDialog.js`.
    - Inclusione automatica del componente tramite `hookActionAdminControllerSetMedia` in `mpcustomerinvoice.php`.
    - Aggiornati `adminOrderExportHelper.js` e `adminTableOrders.js` per delegare l'apertura e la stampa a `MpPrintDialog`.

### 1.4.95

- **Eliminazione Codice Ridondante & Centralizzazione Modale Stampa**:
    - Rimossi tutti i metodi duplicati relativi ai dialog (`getDialog`, `openActionDialog`, `buildDialogBody`, `handleDialogSubmit`) da `adminTableOrders.js`.
    - Centralizzata l'intera gestione del modale unificato `openPrintDialog` e dell'esecuzione delle chiamate di stampa in `AdminOrderExportHelper` (`adminOrderExportHelper.js`), riutilizzabile ovunque nel backoffice.

### 1.4.94

- **Allineamento Totale Modale Stampa Documenti ("Stampa documento")**:
    - Modificato sia `adminOrderExportHelper.js` che `adminTableOrders.js` per mostrare unicamente il modale unificato intitolato "Stampa documento".
    - Opzioni disponibili: Ordine, Fattura, Spedizione, Etichetta Indirizzo e Segnacollo Bartolini.
    - Visualizzazione dinamica del selettore Copie per le opzioni etichetta (Indirizzi / Segnacollo BRT).

### 1.4.93

- **Integrazione "Segnacollo Bartolini" & Modale Stampa Unificato**:
    - Aggiunta la voce "Segnacollo Bartolini" (`brt`) nel modale di stampa unificato `openPrintDialog` in `adminOrderExportHelper.js` (con visualizzazione dinamica del selettore Copie per etichette indirizzi e segnacollo BRT).
    - Rafforzato l'aggancio di `window.AdminOrderExportHelper` in `adminTableOrders.js` per garantire l'apertura del modale unificato al click sul pulsante `.js-order-action-label` dell'elenco ordini.

### 1.4.92

- **Uniformazione Azione Pulsante Etichetta `.js-order-action-label`**:
    - Il pulsante etichetta nell'elenco ordini (`adminTableOrders.js`) invoca ora `AdminOrderExportHelper.openPrintDialog(orderId)`, condividendo esattamente lo stesso modale di stampa dinamico (con selezione Ordine, Fattura, Spedizione, Etichetta Indirizzo e numero copie) utilizzato dal pulsante "Stampe" nella pagina di dettaglio ordine.

### 1.4.91

- **Fix Blocco Popup del Browser nella Stampa PDF (`executePrint`)**:
    - Risolto il blocco di `window.open` causato dal sistema di Popup Blocker dei browser moderni sulle chiamate `fetch` asincrone.
    - Aggiornato `adminOrderExportHelper.js`: apre preventivamente la nuova scheda in risposta al gesto dell'utente e ne aggiorna la `location.href` col Blob PDF una volta completata la generazione asincrona.

### 1.4.90

- **Correzione Risposta AJAX per Stampa Documenti PDF (`renderPdfDocument`)**:
    - Risolto errore `SyntaxError: Unexpected token '%', "%PDF-1.7..."` scatenato dall'output HTTP inline di TCPDF.
    - `ajaxProcessRenderPdfDocument` in `AdminMpCustomerInvoice.php` genera ora il PDF in modalità stringa e restituisce la codifica `base64` dentro il JSON di risposta.
    - `executePrint` in `adminOrderExportHelper.js` decodifica la stringa `base64` in `Blob` e apre automaticamente il PDF in una nuova scheda del browser (`window.open`).

### 1.4.89

- **Implementazione Sistema di Stampa PDF Documenti ed Etichette (PSR-4)**:
    - Nuova architettura modulare in `src/PrintPdf/` basata su `PrintManager` (classe base astratta estendente TCPDF) e componenti dedicati (`PdfOrderHeader`, `PdfOrderBody`, `PdfOrderFooter`, `PdfHeaderRight`, `PdfOrderAddresses`, `PdfOrderInfo`).
    - Classi di stampa concrete: `PrintPdfOrder`, `PrintPdfInvoice`, `PrintPdfDelivery`, `PrintPdfReturn` e `PrintPdfAddress`.
    - **Stampa Etichetta Indirizzo (`PrintPdfAddress`)**:
        - Generazione multi-copia con numerazione progressiva ordine per etichetta (`<id_order>-<N>`, es. 23638-1, 23638-2, ...).
        - Dimensioni fisiche etichetta configurabili dall'admin in millimetri (Larghezza × Altezza).
        - Layout ad alto contrasto con header scuro e box indirizzo con bordi arrotondati, inclusivo di nome/azienda, via, CAP/città/provincia, paese e recapito telefonico.
        - Ridimensionamento dinamico del font size per l'adattamento perfetto allo spazio utile.
    - **Pannello di Configurazione & AJAX**:
        - Sezione "Dimensioni Etichetta (Stampa PDF)" nella pagina di setup del modulo.
        - Salvataggio integrato tramite l'handler nativo `Tools::isSubmit('submitConfiguration')` in `renderSetupPage()`, adattato alla risposta JSON asincrona via AJAX senza codice ridondante.
        - Modale overlay spinner a schermo intero con risposta dinamica dell'esito operazione.

### 1.3.88

- **Correzione persistenza salvataggio e ricaricamento configurazione via AJAX**:
    - Risolto problema per cui il ternario `$value ?: $default` e `getSetupConfig()` scartavano i valori falsy (`0`, `"0"`, `""`, `false`, `[]`) ripristinando impropriamente i valori di default alla riapertura della pagina.
    - Aggiornato `saveSetupConfiguration()` e `getSetupConfig()` per verificare con precisione se una chiave esiste o è impostata prima di restituire il default.
    - Aggiornata la serializzazione FormData nel JavaScript per includere in `URLSearchParams` tutti i campi multiselect e radio button.

### 1.3.87

- **Implementazione stampa etichetta indirizzo di spedizione in `PrintPdfAddress`**:
    - Ogni copia è una pagina PDF con numerazione progressiva `<id_order>-<N>` (es. 23638-1, 23638-2, …).
    - Layout: header scuro con numero ordine e data, box indirizzo con bordi arrotondati e titolo "SPEDIRE A:".
    - L'indirizzo mostra: azienda/nome, via, CAP/città/provincia, paese, telefono.
    - Font size auto-adattivo allo spazio disponibile sull'etichetta.
    - Dimensioni etichetta lette dalla configurazione del modulo (default 100×50mm).
    - Testato con 4 copie dell'ordine 23638 → PDF 107KB generato correttamente.

### 1.3.86

- **Salvataggio configurazione via AJAX con modale di attesa**:
    - Il pulsante "Salva" non ricarica più la pagina: invia i dati via `fetch` POST `x-www-form-urlencoded`.
    - Modale overlay con spinner durante il salvataggio, poi mostra il risultato (✓ successo / ✗ errore) con messaggio dal server.
    - Nuovo metodo `ajaxProcessSaveConfiguration()` in `AdminMpCustomerInvoice.php`.

### 1.3.85

- **Dimensioni Etichetta configurabili nella pagina Setup**:
    - Aggiunta sezione "Dimensioni Etichetta (Stampa PDF)" in `views/twig/admin/configuration.html.twig` con due campi numerici: Larghezza (mm) e Altezza (mm).
    - Salvataggio e lettura delle chiavi `MPCUSTOMERINVOICE_LABEL_WIDTH` e `MPCUSTOMERINVOICE_LABEL_HEIGHT` in `AdminMpCustomerInvoice.php`.
    - `PrintPdfAddress` legge le dimensioni dalla configurazione e genera la pagina PDF con formato personalizzato (orientamento automatico L/P).
    - Aggiornato il costruttore di `PrintManager` per supportare `$orientation` e `$pageFormat` personalizzabili.

### 1.3.84

- **Refactoring PSR-4 Architettura Stampa PDF in `src/PrintPdf/`**:
    - Implementata la classe astratta `PrintManager` ([src/PrintPdf/PrintManager.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintManager.php)) estendendo TCPDF e integrando il caricamento ordini, margini e componenti.
    - Creata la cartella `src/PrintPdf/Components/` (`MpSoft\MpCustomerInvoice\PrintPdf\Components`) per le componenti modulari: `PdfOrderHeader`, `PdfOrderBody`, `PdfOrderFooter`, `PdfHeaderRight`, `PdfOrderAddresses` e `PdfOrderInfo`.
    - Aggiornate le classi concrete `PrintPdfOrder`, `PrintPdfInvoice`, `PrintPdfDelivery`, `PrintPdfReturn`, e `PrintPdfAddress`.
    - Rimossa la cartella temporanea `src/v16` dopo la migrazione ed eseguito `composer dump-autoload`.

### 1.3.83

- **Integrazione della Stampa Ordine tramite OrderPage (PdfOrderPage.php)**:
    - Collegata la classe `PrintPdfOrder` ([src/PrintPdf/PrintPdfOrder.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfOrder.php)) all'architettura `PdfOrderPage` disponibile in `src/v16/pdf/OrderPage/PdfOrderPage.php`.
    - Aggiunti controlli di sicurezza (`file_exists`, `class_exists` e fallback dei dati tramite `ExportOrder`) per garantire la corretta esecuzione senza dipendenze bloccanti.

### 1.3.82

- **Struttura Classi PrintPdf & Pulsante Stampe Toolbar Admin**:
    - Creata la cartella `src/PrintPdf/` con la classe astratta `PrintManager.php` e le sottoclassi concrete: `PrintPdfOrder`, `PrintPdfInvoice`, `PrintPdfDelivery`, `PrintPdfReturn`, e `PrintPdfAddress`.
    - Aggiunto il pulsante **"Stampe"** nella toolbar di amministrazione degli ordini (`AdminOrders`).
    - Riutilizzato il modale `<dialog id="order-action-dialog">` per consentire la selezione tra Ordine, Fattura, Spedizione, Nota di Reso ed Etichetta Indirizzo (con selettore del numero di copie per l'etichetta).
    - Implementato l'endpoint AJAX `ajaxProcessRenderPdfDocument` che richiama il metodo `renderPdf()` delle relative classi per la notifica e test di stampa.

### 1.3.81

- **Miglioramento Colonna Cliente nella Tabella Ordini**:
    - Il nome del cliente nella tabella ordini (`adminTableOrders.js`) è ora un link cliccabile che apre la scheda cliente di PrestaShop in una nuova scheda.
    - Aggiunta la visualizzazione del badge `<span class="badge badge-warning">Richiede Fattura</span>` sotto il nome del cliente se l'ordine richiede la fattura (`invoice_requested = 1` o dati fiscali presenti).

### 1.3.80

- **Stile Nativo Badge "Richiede Fattura"**:
    - Applicata la classe nativa `<span class="badge badge-warning">Richiede Fattura</span>` (identica agli altri gruppi cliente del dettaglio ordine).

### 1.3.79

- **Risoluzione & Inserimento Robusto del Badge "Richiede Fattura"**:
    - Implementata l'estrazione automatica del codice cliente direttamente dal testo dell'header (`#108802`) e la risoluzione automatica del token di sicurezza per la chiamata AJAX.
    - Inserito il badge verde **"Richiede Fattura"** direttamente nel blocco gruppi del cliente (`.customer-groups`) in `#customerInfo`.

### 1.3.78

- **Badge "Richiede Fattura" nel Dettaglio Ordine**:
    - Aggiunto l'inserimento dinamico del badge verde **"Richiede Fattura"** accanto al link `#viewFullDetails` ("Guarda tutti i dettagli") nel blocco cliente della pagina dettaglio ordine, attivo esclusivamente se `invoice_requested = 1`.

### 1.3.77

- **Notifiche PrestaShop per Salvataggio ID Eurosolution**:
    - Sostituito il finestra pop-up/alert JS nativo del browser con la notifica standard PrestaShop `showNoticeMessage()` nel widget [id_eurosolution.html.twig](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/twig/admin/id_eurosolution.html.twig).

### 1.3.76

- **Scheda Predefinita AdminController**:
    - Modificata l'azione predefinita in `AdminMpCustomerInvoice.php` (`initContent`) per aprire direttamente la scheda **Configurazione** (`renderSetupPage`) all'accesso al controller.

### 1.3.75

- **Selezione Multipla Stati Ordine Trigger**:
    - Trasformata la select di selezione dello stato ordine `MPCUSTOMERINVOICE_ORDER_STATE_TRIGGER` in una select multipla Chosen (`multiple`).
    - Aggiornati il controller `AdminMpCustomerInvoice.php` e la logica degli hook in `mpcustomerinvoice.php` per memorizzare ed interpretare un array di ID stati ordine.
    - Aggiunto il controllo idoneità documento per garantire che se una Fattura (`hasInvoice()`) o una Nota di Consegna (`delivery_number > 0`) è già stata creata per l'ordine, non venga mai rigenerata duplicandola nei passaggi di stato successivi.

### 1.3.74

- **Generazione Automatica Documenti al Cambio Stato & Campo `invoice_requested`**:
    - Aggiunto il campo `invoice_requested` (TINYINT) nella tabella `customer_invoice` ed integrato nel modello `ModelCustomerInvoice.php`.
    - Valorizzazione automatica di `invoice_requested` durante il salvataggio degli indirizzi dal form (#want_invoice).
    - Aggiunte nella pagina di configurazione del modulo la select `chosen` per la scelta dello stato ordine target (`MPCUSTOMERINVOICE_ORDER_STATE_TRIGGER`) e lo switch legacy per scegliere se creare sia Fattura che Nota di Consegna oppure uno solo dei due (`MPCUSTOMERINVOICE_CREATE_BOTH`).
    - Implementata l'intercettazione degli hook `actionOrderStatusUpdate` e `actionOrderStatusPostUpdate` per generare automaticamente Fattura (`setInvoice`) o Nota di Consegna (`setDeliverySlip`) in base alle preferenze ed alla richiesta fattura del cliente.

### 1.3.73

- **Toolbar Button Ordine & JS Export Helper**:
    - Implementato l'hook `actionGetAdminToolbarButtons` in `mpcustomerinvoice.php` per aggiungere il pulsante **Esporta XML** (`btnExportDocuments`) nella barra delle azioni in alto a destra della pagina dettaglio ordine nativa di PrestaShop (`AdminOrders`).
    - Creata la classe helper JS riutilizzabile `AdminOrderExportHelper` (`views/assets/js/admin/adminOrderExportHelper.js`) per unificare la procedura di esportazione dei documenti XML (Ordine, Fattura, Nota di Consegna) sia dal pulsante toolbar che dalla tabella ordini.

### 1.3.72

- **Intercettazione Menu Ordini e Clienti (Backoffice Override Switches)**:
    - Aggiunti due switch PrestaShop (`MPCUSTOMERINVOICE_OVERRIDE_ORDERS` e `MPCUSTOMERINVOICE_OVERRIDE_CUSTOMERS`) nella pagina di configurazione del modulo (`views/twig/admin/configuration.html.twig`).
    - Implementata l'intercettazione in `hookActionDispatcherBefore` (`mpcustomerinvoice.php`): se attivi, le chiamate al menu ed alle pagine nativi dei clienti (`/sell/customers/` o `AdminCustomers`) e degli ordini (`/sell/orders/` o `AdminOrders`) vengono reindirizzate automaticamente alle rispettive viste di `AdminMpCustomerInvoice`.
    - Salvataggio e lettura delle preferenze integrati nel controller `AdminMpCustomerInvoice.php`.

### 1.3.71

- **Integrazione Gestione e Selezione Indirizzo di Fatturazione**:
    - Aggiornata la preparazione dati in [CustomerInvoiceCardRenderer.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/Helpers/CustomerInvoiceCardRenderer.php): recupera l'elenco completo degli indirizzi del cliente e la stringa formattata dell'indirizzo selezionato come fatturazione.
    - Aggiornato il form [customerInvoiceCard.html.twig](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/twig/admin/customerInvoiceCard.html.twig):
        - **Modalità Lettura**: mostra il badge verde **Fatturazione** con l'indirizzo formattato se presente, oppure l'avviso di avvertimento giallo **Nessun indirizzo di fatturazione selezionato**.
        - **Modalità Modifica**: aggiunta la select `<select name="id_address_invoice">` per scegliere l'indirizzo di fatturazione tra tutti quelli registrati per il cliente.
    - Gestito il salvataggio di `id_address_invoice` in [CustomerInvoiceFormHandler.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/Helpers/CustomerInvoiceFormHandler.php) con sincronizzazione immediata dei campi fiscali sull'indirizzo scelto.
    - Aggiornato [adminCustomerInfo.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminCustomerInfo.js): al salvataggio AJAX, aggiorna dinamicamente la vista e ricarica i badge **Fatturazione/Spedizione** nella tabella indirizzi del cliente.

### 1.3.70

- **Creazione ed Inizializzazione Automatica Record `customer_invoice`**:
    - Dichiarata esplicitamente la proprietà `public $force_id = true;` nella classe [ModelCustomerInvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/Models/ModelCustomerInvoice.php).
    - Aggiornato [CustomerInvoiceFormHandler.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/Helpers/CustomerInvoiceFormHandler.php): se un cliente non possiede ancora il record in `customer_invoice`, viene istanziato l'oggetto con `id = $idCustomer`, popolato dai dati inviati ed inserito via `$model->add(true, true)`.
    - Registrati gli hook `actionObjectCustomerAddAfter` ed `actionObjectCustomerUpdateAfter` in [HookManager.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/Helpers/HookManager.php): al salvataggio o creazione nativa di qualsiasi cliente in PrestaShop, viene verificata l'esistenza del record in `customer_invoice` creando automaticamente la riga corrispondente e recuperando pre-compilati eventuali campi fiscali (`company`, `vat_number`, `dni`) dagli indirizzi.

### 1.3.69

- **Fix Duplicazione Colonna ed Uniformazione Colori Badge Tipo Indirizzo**:
    - Aggiunto il blocco di concorrenza sincrono `table.dataset.enhancedTypeAddress` in [adminCustomerInfo.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminCustomerInfo.js) prima dell'esecuzione delle chiamate `fetch`, per eliminare qualsiasi rischio di raddoppio della colonna `Tipo Indirizzo`.
    - Distinti nettamente i colori dei badge: **Fatturazione** (Verde smeraldo `#28a745` con icona `receipt`) e **Spedizione** (Azzurro `#17a2b8` con icona `local_shipping`), per una distinzione visiva immediata.

### 1.3.68

- **Integrazione Colonna "Tipo Indirizzo" (Fatturazione / Spedizione) nella Tabella Indirizzi Admin**:
    - Implementata in [adminCustomerInfo.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminCustomerInfo.js) la funzione `enhanceAddressGridTable()` che intercetta la tabella `.customer-addresses-card table` (`customer_address_grid_table`).
    - Effettua una chiamata AJAX a `getCustomerInvoiceData` per leggere `id_address_invoice` dalla tabella `customer_invoice`.
    - Inserisce la nuova colonna `Tipo Indirizzo` subito dopo `Id`, identificando e contrassegnando con badge visivo **Fatturazione** (se corrisponde a `id_address_invoice`) oppure **Spedizione** negli altri casi.

### 1.3.67

- **Fix `UndefinedMethodError` in `syncCustomerAddresses`**:
    - Sostituito il metodo inesistente `Address::getAddressesByCustomer` con l'esecuzione diretta di una query SQL sulla tabella `address` per recuperare tutti gli `id_address` attivi del cliente ed aggiornarne i campi fiscali (`vat_number`, `dni`, `company`).

### 1.3.66

- **Fix URL invio AJAX e Sincronizzazione Indirizzo Fatturazione Cliente**:
    - Risolto il problema di sovrascrittura di `form.action` causato dal campo `<input name="action">`: aggiornata la risoluzione in `adminCustomerInfo.js` tramite `form.getAttribute('action')` e rimosso l'input nascosto duplicato nel template Twig.
    - Implementata la sincronizzazione automatica dei campi condivisi (`vat_number`, `dni`, `company`) su tutti gli oggetti `Address` del cliente in [CustomerInvoiceFormHandler.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/Helpers/CustomerInvoiceFormHandler.php).

### 1.3.65

- **Garantia Rendering Scheda Cliente in Admin (Multi-Hook Fallback & Auto-Registration)**:
    - Aggiornato `hookDisplayAdminCustomers` ed `hookDisplayAdminEndContent` in `HookManager.php`: l'estrazione dell'ID cliente legge da `params`, query string ed espressione regolare dall'URL (`/customers/(\d+)`), rendendo infallibile l'identificazione del cliente sia sotto rotte Symfony che legacy.
    - Implementata l'auto-registrazione dinamica degli hook `displayAdminCustomers` e `displayAdminEndContent` nel database PrestaShop (`ps_hook_module`) ad ogni caricamento delle pagine admin.
    - Perfezionato lo script [adminCustomerInfo.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminCustomerInfo.js) con ciclo di setup guidato da timer e rimozione automatica di eventuali container duplicati.

### 1.3.64

- **Scheda Dati Fatturazione Elettronica Cliente in Admin**:
    - Implementata la nuova scheda responsive per il form CRUD dei dati di fatturazione elettronica del cliente, posizionata dinamicamente sopra la card `.customer-private-note-card` nella pagina di dettaglio cliente Admin (`AdminCustomers`).
    - Creata la classe Helper [CustomerInvoiceCardRenderer.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/Helpers/CustomerInvoiceCardRenderer.php) per la preparazione dati ed il rendering disaccoppiato della card.
    - Creata la classe Helper [CustomerInvoiceFormHandler.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/Helpers/CustomerInvoiceFormHandler.php) per la validazione ed il salvataggio dei dati inviati via form.
    - Implementate le operazioni CRUD via **AJAX fetch** (`POST application/x-www-form-urlencoded`) e l'aggiornamento dinamico a cascata delle professioni in base al settore selezionato tramite la classe ES6 [adminCustomerInfo.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminCustomerInfo.js).

### 1.3.63

- Corretto un fatal error in `GenerateDocumentRestrictions.php`: sostituita la chiamata al metodo inesistente `Order::hasDeliverySlip()` con il metodo nativo `Order::hasDelivery()`.
- Creato lo script di upgrade `upgrade-1.3.63.php` per registrare automaticamente i nuovi hook `actionOrderStatusUpdate` e `actionOrderStatusPostUpdate` durante l'aggiornamento del modulo.

### 1.3.62

- Implementata la classe `GenerateDocumentRestrictions` registrando gli hook `actionOrderStatusUpdate` e `actionOrderStatusPostUpdate`: controlla la tabella `customer_invoice` per il cliente dell'ordine e, se `vat_number` o `dni` sono valorizzati, genera **esclusivamente la fattura**, altrimenti genera **esclusivamente la nota di consegna (DDT)**, garantendo la mutua esclusione tra i due documenti.

### 1.3.61

- Implementata l'estrazione dati per l'esportazione **Nota di Consegna (DDT)** (`ExportDelivery`): legge `delivery_number` e `delivery_date` dalla tabella `order_invoice` di PrestaShop.
- Aggiunto il controllo di coerenza ed esistenza dei documenti per `ExportInvoice` (`number` > 0) ed `ExportDelivery` (`delivery_number` > 0): se il documento richiesto non esiste o ha numero nullo/zero, viene generato un avviso d'errore explicito per l'utente.

### 1.3.60

- Perfezionata la colonna **Totale** nella tabella ordini: organizzato il layout in due colonne (Flexbox) con etichette a sinistra ("Prodotti", "Commissioni", "Totale ordine") e valori allineati a destra sulla stessa riga.

### 1.3.59

- Aggiornato il rendering della colonna **Totale** nella tabella amministrativa degli ordini: se un ordine presenta commissioni di pagamento, viene mostrato il totale base, l'importo delle commissioni ed in risalto il **Totale reale dell'ordine** (Totale + commissioni) allineato a destra.

### 1.3.58

- Corretto un bug in `calcRound` e nei totali di `ExportManager`: quando l'ordine ha una commissione di pagamento che non è ancora inclusa nel campo `total_paid_tax_incl` dell'oggetto `Order`, la commissione viene sommata al prezzo di riferimento pagato affinché l'arrotondamento risulti `0.00` anziché negativo (`-2.41`).

### 1.3.57

- Rifattorizzata la funzione `calcFees` in `ExportManager` usando direttamente `Fees::calculate` di `mppaymentswithfees` per calcolare le commissioni solo se il metodo di pagamento dell'ordine appartiene ai moduli attivi con commissione.
- Corretto il bug in `Fees::calculate` relativo alla validazione dell'aliquota IVA fornita in ingresso.

### 1.3.55

- Aggiornata la classe `ExportInvoice` per estrarre `invoice_id` (`id_order_invoice`), `invoice_number` (`number`) e `invoice_date` (`date_add`) interrogando direttamente la tabella `order_invoice` di PrestaShop.

### 1.3.54

- Corretto un bug in `calcRound` aggiungendo il valore delle commissioni (al netto dell'IVA) per ottenere un arrotondamento corretto.

### 1.3.53

- Utilizzato l'array `$this->subjects` per mappare correttamente il tipo soggetto (`subject`) del cliente nel tracciato XML.

### 1.3.52

- Aggiunta la funzione `calcFees()` in `ExportManager` per calcolare le commissioni di pagamento integrando il modulo `mppaymentswithfees` tramite la sua classe `Fees`.

### 1.3.51

- Aggiunto il calcolo dell'arrotondamento (`calcRound`) in `ExportManager` in base all'aliquota IVA configurabile nella pagina di configurazione del modulo.

### 1.3.50

- Modificata l'ordinazione dei campi nell'esportazione XML per rispettare rigorosamente l'ordine richiesto dallo schema.

### 1.3.49

- Corretto un bug richiamando il metodo corretto `Order::getCustomerNbOrders` invece di `Order::getCustomerOrdersCount` non esistente in PrestaShop.

### 1.3.48

- Corretto un bug richiamando il metodo corretto `Product::getAllCustomizedDatas` invece di `Product::getCustomizedDatas` non più esistente in PrestaShop 8.

### 1.3.47

- Implementazione dell'esportazione documenti con dati reali: aggiornato `ExportManager` e le classi associate per leggere i dati reali degli ordini, dei clienti, degli indirizzi di spedizione/fatturazione e delle linee di dettaglio dei prodotti (inclusi attributi e personalizzazioni) dal database di PrestaShop.

### 1.3.46

- Implementazione dell'esportazione documenti: creata l'architettura in `src/Export` con la classe base astratta `ExportManager` e le sottoclassi specializzate `ExportOrder`, `ExportInvoice` e `ExportDelivery` per gestire la struttura dei dati dei documenti in base all'ID dell'ordine ed al tipo. Implementata la classe `JsonToXml` per la conversione in formato XML conforme e l'invio delle intestazioni HTTP per il download forzato dal browser.

### 1.3.45

- Modificato il comportamento del modale di esportazione dei documenti nella tabella ordini: implementato il redirect alla nuova azione `showCustomExportPage` del controller `AdminMpCustomerInvoice` che mostra una pagina temporanea di test per l'esportazione del documento selezionato (ordine, fattura o nota di consegna).

### 1.3.44

- Modificato il comportamento del modale di stampa e di esportazione dei documenti nella tabella ordini: rimosso l'invio della chiamata AJAX simulata e implementato il redirect ai link ufficiali di generazione PDF nativi (ordine, fattura e nota di consegna/DDT) affinché vengano correttamente intercettati dal dispatcher del modulo.

### 1.3.43

- Aggiunta intercettazione nel dispatcher per i link di generazione PDF nativi (fatture, note di consegna e ordini) con reindirizzamento temporaneo a una pagina di test del modulo (`showCustomPdfPage`).

### 1.3.42

- Risolto il problema con la colonna **Consegna** e relativo filtro: ora vengono caricate tutte le nazioni (attive e non attive) e l'array viene correttamente indicizzato per `id_country` per evitare discrepanze nel mapping tra ID e nomi.

### 1.3.41

- Aggiunto filtro a select sulla colonna **Consegna** con l'elenco delle nazioni attive di PrestaShop; la ricerca avviene sull'`id_country` dell'indirizzo di spedizione dell'ordine.

### 1.3.40

- Aggiunta colonna **Consegna** nella tabella ordini, posizionata dopo **Semaforo**, che mostra la nazione di consegna dell'ordine.

### 1.3.39

- Rifattorizzata la colonna Azioni nella tabella ordini: i pulsanti sono ora ordinati VEDI, STAMPA, ESPORTA, ETICHETTE.
- Aggiunti modali `<dialog>` per STAMPA (Ordine, Fattura, Nota vendita), ESPORTA (Ordine, Fattura, Nota vendita) e ETICHETTE (Etichette indirizzi con numero di copie, Segnacollo Bartolini).
- Implementate le chiamate AJAX a `AdminMpCustomerInvoice` con azioni fittizie `printOrder`, `exportOrder` e `printLabel` da completare in un secondo step.

### 1.3.38

- Rinomina del modulo in "MP Gestione Fattura Elettronica".
- Aggiunto nel dettaglio ordine il widget per visualizzare, copiare e aggiornare l'ID Eurosolution del cliente.
- Inserito Override classes/db/Db.php per eseguirew query con "with" Common Table Expressions (CTE)

### 1.3.37

- Il checkout di un cliente anonimo reindirizza alla pagina di registrazione, conservando il carrello come pagina di ritorno.

### 1.3.36

- Corretto il nome minuscolo del controller AJAX `customer`, necessario su filesystem Linux case-sensitive.

### 1.3.35

- Aggiunte in registrazione le select opzionali Settore e Professione; le professioni sono caricate via AJAX in base al settore selezionato.
- Alla creazione dell'account viene sempre inserita la riga `customer_invoice`, con gli eventuali riferimenti a settore e professione.

### 1.3.34

- La visualizzazione dell'indirizzo di fatturazione mostra tutti i dati fiscali in sola lettura, mantenendo il messaggio informativo sul blocco delle modifiche.

### 1.3.33

- Intercettata anche la rotta PrestaShop localizzata `/indirizzi`, risolta dal controller `addresses`.

### 1.3.32

- Corretta la richiesta AJAX delle province: i parametri non vengono più codificati come entità HTML nell'URL.

### 1.3.31

- Il form indirizzo preseleziona la nazione corrente, mostra la provincia solo per nazioni con province attive e usa Chosen per entrambe le select.
- Aggiunto il pulsante "Indietro", che torna al carrello se il form è stato aperto dal checkout oppure alla lista indirizzi negli altri casi.

### 1.3.30

- La richiesta checkout `newAddress=invoice` apre la pagina indirizzi del modulo.
- Il carrello usa sempre l'indirizzo di fatturazione registrato dal cliente, anche quando viene selezionato un diverso indirizzo di consegna.

### 1.3.29

- L'indirizzo di fatturazione ora mostra solo l'azione "Vedi"; modifica ed eliminazione sono bloccate anche lato server e il relativo form è in sola lettura.

### 1.3.28

- Ridisegnata la pagina indirizzi con testata, card responsive separate, etichette spedizione/fatturazione e azioni evidenti.

### 1.3.27

- La fatturazione resta disponibile su ogni nuovo indirizzo finché il cliente non registra il proprio unico indirizzo di fatturazione.
- Rimossa l'assegnazione alla proprietà non definita `id_address_delivery` durante la registrazione cliente.

### 1.3.26

- Limitato a uno l'indirizzo di fatturazione per cliente; restano illimitati gli indirizzi di spedizione.
- Dopo il salvataggio dell'indirizzo di fatturazione viene mostrato l'avviso per aggiungere un eventuale indirizzo di spedizione differente.
- Il template non renderizza più la sezione fatturazione dopo la registrazione dell'unico indirizzo di fatturazione e la mostra come non modificabile sull'indirizzo di fatturazione esistente.

### 1.3.25

- Aggiunto il campo `company` a `customer_invoice`, con upgrade per le installazioni esistenti.
- La migrazione da PrestaShop 1.6 importa `company` dall'indirizzo di fatturazione sorgente.
- Aggiunto nel form indirizzo il campo "Intestazione fattura", salvato sia nell'indirizzo sia in `customer_invoice`.

### 1.3.24

- Corretto il rendering Smarty degli errori restituiti dalla validazione Fetch dei dati di fatturazione.

### 1.3.23

- Ridisegnato il form indirizzo con layout responsive, toggle "Desidero ricevere la fattura" e campi fiscali condizionali per privato, partita IVA ed ente.
- Aggiunta la validazione server-side dei dati fiscali via Fetch prima dell'invio e il salvataggio in `customer_invoice` dell'indirizzo di fatturazione selezionato.
- Convertito il caricamento degli stati in base al paese a Fetch.

### 1.3.22

- Corretto il rendering della pagina indirizzi: la variabile Smarty `customer` non viene più sovrascritta con un oggetto `Customer`.

### 1.3.21

- Corretto il nome minuscolo dei file dei controller frontend `address` e `customer`, richiesto dal dispatcher su filesystem case-sensitive.

### 1.3.20

- Aggiunto il controller frontend degli indirizzi cliente, con elenco, creazione, modifica ed eliminazione limitate agli indirizzi del cliente autenticato.
- Il dispatcher reindirizza i controller nativi `address` e `addresses` alla pagina del modulo senza alterare le altre pagine frontend.
- Aggiunto il link alla sezione indirizzi nell'area account e la selezione dinamica degli stati in base al paese.
- Rimosse le route Symfony e i controller/template frontend non utilizzati dal flusso del modulo.

### 1.3.19

- Aggiunto l'upgrade che reinstalla gli override del modulo, includendo `OrderPayment`, nelle installazioni esistenti.

### 1.3.18

- Aggiunto l'override di `OrderPayment` con il campo `order_reference` esteso a 32 caratteri.

### 1.3.17

- Aggiunte alla configurazione le lunghezze correnti di `orders.reference` e `order_payment.order_reference`.
- Aggiunto il comando amministrativo per portare entrambe le colonne a `VARCHAR(32)` e aggiornare immediatamente i valori mostrati.

### 1.3.16

- Aggiunta la configurazione `REFERENCE_RENUMBER` per definire il formato del riferimento ordine, con supporto a `{$year}`, `{$id_order}` e padding mediante `[$id_order|lunghezza]`.
- Implementato `actionGenerateDocumentReference` per applicare il formato configurato usando il prossimo ID ordine previsto; aggiunto l'upgrade che registra l'hook nelle installazioni esistenti.

### 1.3.15

- Convertito il template della sezione Setup da Smarty a Twig e spostato in `views/twig/admin/configuration.html.twig`.
- Aggiornato il controller amministrativo per renderizzare il template Twig; rimosso il template Smarty obsoleto.

### 1.3.14

- Aggiunto alla colonna Semaforo della tabella ordini un filtro select basato sui flag configurati in `mporderflag`.
- Applicato il filtro Semaforo alle query dati, conteggio e statistiche della tabella ordini.
- Spostata la pagina di configurazione dal metodo `getContent()` alla sezione Setup di `AdminMpCustomerInvoice`; `getContent()` ora reindirizza a tale sezione.

### 1.3.13

- Aggiunte alla tabella ordini le colonne Semaforo, con dati da `mporderflag`, e Note, con conteggi delle note cliente, ordine e ricamo da `mpnotes`.
- Aggiunte le azioni per stampare fattura, DDT ed etichette, oltre al collegamento di visualizzazione dell'ordine.
- Aggiornato il layout delle note e delle azioni per mantenere indicatori e pulsanti uniformi.
- Aggiunto l'override Twig della sezione Sources nel dettaglio ordine, attualmente disabilitata.

### 1.3.11

- Il filtro Data della tabella ordini supporta date singole e intervalli inclusivi nel formato `GG/MM/AAAA - GG/MM/AAAA`.

### 1.3.10

- Corretto il ripristino dei valori nei filtri colonna dopo il rendering di Bootstrap Table.

### 1.3.9

- Dopo una ricerca ordini, i valori dei filtri applicati restano visibili nelle rispettive colonne.

### 1.3.8

- Aggiunti i pulsanti Cerca e Reset nella toolbar della tabella ordini.
- La ricerca per colonna viene applicata solo tramite Cerca; Reset pulisce i filtri e ricarica l'archivio completo.

### 1.3.7

- Ripristinata la ricerca immediata nei filtri per colonna della tabella ordini, senza richiedere la pressione di Invio.

### 1.3.6

- Corretto il layout della pagina ordini: metriche in alto, controlli di esclusione sotto e tabella a larghezza piena.
- Sostituite le checkbox degli stati esclusi con una select multipla Chosen ricercabile.

### 1.3.5

- Aggiunta la colonna email e la ricerca per colonna nella tabella amministrativa degli ordini.
- Aggiunto il filtro Stato come select basata su `id_order_state`.
- Aggiunto il filtro Totale con importo esatto, operatori `>`, `<`, `>=`, `<=` e intervalli come `>200 <500`.
- Aggiunta una dashboard ordini con totali IVA inclusa e conteggi per archivio e risultati filtrati.
- Aggiunto il pannello per escludere gli stati ordine dalle statistiche; gli stati annullati sono esclusi inizialmente.
- Centralizzato il fix di Bootstrap Table in `fixBootstrapTable(tableId)` e uniformate le icone toolbar a Material Icons.
- Adeguato il markup delle tabelle amministrative al runtime Bootstrap 4 del back office PrestaShop 8.2.7.

### 1.3.4

- Rimossi gli hook che modificavano le griglie core di clienti, indirizzi e ordini.
- Aggiunta la pagina amministrativa dedicata agli ordini con Bootstrap Table e caricamento AJAX tramite `fetch`.
- Aggiornata la pagina amministrativa clienti per usare Bootstrap Table e caricamento AJAX tramite `fetch`.
- Aggiunta la visualizzazione del campo CUU nell'elenco clienti.
- Mantenuti telefono e cellulare nel dettaglio AJAX degli indirizzi del cliente.
- Aggiunto lo script di upgrade `1.3.3` che disregistra gli hook rimossi nelle installazioni esistenti.
