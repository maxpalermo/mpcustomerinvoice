# mpcustomerinvoice

Modulo per gestire i codici della fatturazione elettronica.

## Changelog

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
