# Modulo `mpcustomerinvoice` - PrestaShop Document Export & Direct Print Engine

Il modulo **`mpcustomerinvoice`** (PrestaShop 8.2.7) gestisce l'esportazione dei documenti fiscali in formato XML per la fatturazione elettronica, le restrizioni di fatturazione in base ai dati del cliente, le tabelle ordini personalizzate con badge Shadcn in backoffice, e la stampa diretta/massiva dei documenti PDF ed etichette tramite il motore di stampa **QZ Tray (v2.2.6)**.

---

## 📌 Versione Attuale
* **Versione**: `1.6.0`

---

## ⚙️ Funzionalità Principali

1. **Esportazione XML Documenti Fiscai (`ExportManager.php`)**:
   - Generazione file XML con query native su DB PrestaShop (`Order`, `Customer`, `Address`, `Product`, `Carrier`).
   - Mappatura soggetti fiscali (`F` per privati, `G` per Partita IVA, `E` per enti).
   - Scorporo e gestione IVA per le spese di incasso / contrassegno (`mp_payment_fee_order`).
   - Arrotondamenti e abbuoni (`calcRound`).

2. **Restrizioni Generazione Documenti (`GenerateDocumentRestrictions.php`)**:
   - Clienti con Partita IVA / DNI $\rightarrow$ Genera **Fattura** ed impedisce Nota di vendita.
   - Clienti Privati $\rightarrow$ Genera **Nota di Vendita (Delivery Slip)** ed impedisce Fattura.
   - Supporto sia per trigger automatici su cambio stato ordine che per generazione manuale da scheda ordine.

3. **Interfaccia Backoffice Ordini (`AdminMpCustomerInvoice.php` / `adminTableOrders.js`)**:
   - Tabella ordini Shadcn con indicatori visivi dei documenti presenti (Fattura, Nota di vendita, Segnacollo BRT).
   - Badge "Richiede Fattura" e "Documento errato".
   - Popover note per ordini, clienti e ricami con timer hover configurabile.
   - Cambio stato a blocchi con progresso percentuale e cancellazione chiamata Fetch via `MpBatchProgressDialog.js`.

4. **Motore di Stampa PDF & Etichette Indirizzo (`src/PrintPdf/`)**:
   - Template di stampa TCPDF dedicati (Fattura WEB/D, Nota Vendita WEB, Ordine, Etichetta Indirizzo, Nota di Reso).
   - Etichetta indirizzo a copia singola rigida con barcode Code128, box contrassegno condizionale e icona telefono ☎.

5. **Stampa Diretta tramite QZ Tray v2.2.6 (`qz-tray.js`, `MpPrintDialog.js`)**:
   - Integrazione della libreria client ufficiale QZ Tray ([`qz-tray.js`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/qz-tray.js)) per la gestione dell'handshake WebSocket con il daemon locale (porte WSS/WS, fallback su localhost/127.0.0.1).
   - Pass-through inalterato del PDF originale BRT (100x65 mm) senza shift o tagli, mantenendo l'integrità del documento nativo.
   - Impostazione esplicita delle opzioni QZ Tray (`size: { width: 100, height: 65 }, units: 'mm', orientation: 'landscape', scaleContent: true`) per garantire la stampa 1:1 dei segnacolli BRT in orizzontale sul rotolo termico.
   - Layout scheda stampanti in stile **Shadcn UI compatto** con flexbox ad espansione fluida a 100% di larghezza (`width: 100% !important`), colori pastello tenui per le icone, sfondo neutro `#f8fafc` e controlli form da 34px senza gradienti.
   - Risolto il blocco del pulsante Salva rimuovendo la dichiarazione duplicata `selectEmp` ed attivato `jQuery.migrateMute = true` per azzerare i log di avviso in console.
   - Configurazione personalizzata per ciascuna delle 5 stampanti (Ordini, Fatture, Spedizione, Indirizzo, BRT) e per ciascun operatore (`id_employee`), con formati carta standard (A4, A5, Letter, Legal, 100x65, 100x100, 100x50, 100x80) e misure personalizzate in mm.
   - Controlli individuali per ciascuna stampante: Formato Carta, Orientamento (`auto`, `landscape`, `portrait`), Rotazione Gradi (`0°`, `90°`, `180°`, `270°`) e Rasterizzazione PDF (`0` vettoriale / `1` raster).
   - Rilevamento automatico stampanti collegate (`qz.printers.find()`).
   - Invio diretto dei PDF in Base64 (`type: 'pixel', format: 'pdf', flavor: 'base64'`) senza anteprima del browser.
   - Pulsanti di download installer per Windows (`qz-tray-2.2.6-x86_64.exe`) e Linux (`qz-tray-2.2.6-x86_64.run`).

---

## 📜 Cronologia Modifiche Recenti

### 1.6.0
- **Migrazione a QZ Tray v2.2.6**: Eliminazione del vecchio motore WebPrint.jar, introduzione di `qz-tray.js`, download installer Linux (.run) e Windows (.exe), integrazione invio PDF Base64 via WebSocket in `MpPrintDialog.js`.

### 1.5.127
- **Fix Layout Stampa Ordine**: Correzione dell'intestazione PDF ordine con posizionamento centrato del logo e blocco dettagli in alto a destra.

### 1.5.126
- **Icona Telefono Etichetta Indirizzo**: Inserimento icona ☎ Unicode DejaVuSans nell'etichetta di spedizione.

### 1.5.125
- **Intercezione Generazione Manuale Documento**: AJAX handling del form "Genera documento" in scheda ordine senza redirect.
