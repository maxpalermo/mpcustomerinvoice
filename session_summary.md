# PrestaShop Document Export Implementation Summary

This document summarizes the changes, logic, and configurations implemented for the custom XML document export system in the `mpcustomerinvoice` module (PrestaShop 8.2.7).

---

## 1. Context & Conversation Reference

- **Current Conversation ID**: `118a0a1e-cc00-49b0-b53a-13024f0b9e85`
- **App Data Directory**: `/home/massimiliano/.gemini/antigravity`
- **Docker Environment**: `ps82-workwear-site` (PrestaShop 8.2.7)

> [!NOTE] In any new Antigravity session, you can paste the **Conversation ID** or tell the assistant: _"Leggi il file `session_summary.md` nella cartella del modulo per avere il contesto completo del lavoro svolto finora."_

---

## 2. Key Accomplishments & Features

### A. Real Data Extraction (`src/Export/ExportManager.php`)

- Replaced all mock/static data arrays with real PrestaShop database queries using native models (`Order`, `Customer`, `Address`, `Product`, `Carrier`) and the module's custom table (`ModelCustomerInvoice`).
- Mapped customer custom billing parameters (`sdi`, `pec`, `is_foreign`) and set correct subject mapping.
- Optimized product combination and image retrieval logic:
    - Replaced deprecated/missing method `Product::getCustomizedDatas` with `Product::getAllCustomizedDatas`.
    - Replaced undefined method `Order::getCustomerOrdersCount` with `Order::getCustomerNbOrders`.

### B. Field Ordering & Structure

- Re-ordered the XML output keys inside `ExportManager::getData()` to strictly respect the required schema hierarchy:
    1. `document_type`
    2. `order_id` / `order_date` / `order_reference` / `current_status`
    3. `invoice_id` / `invoice_number` / `invoice_date`
    4. Totals (excl, incl, shipping, wrapping, discounts, taxes)
    5. `vat_code` / `rounds` / `nc` / `payment` / `carrier` / `shop_address` / `foreign` / `discount_note`
    6. `customer` (details + delivery and invoice addresses)
    7. `rows` (all products details: stock, positions, customs, attributes)
    8. `fees`

### C. Rounding & Abbuoni Calculation (`calcRound`)

- Implemented the rounding calculation logic in `ExportManager::calcRound()`: $$\text{Round} = \text{Total Paid (with Tax)} - \left[ (\text{Products} - \text{Discounts} + \text{Shipping} + \text{Wrapping} + \text{Fees}) \times (1 + \text{VAT Rate}) \right]$$
- Built a dynamic setting field in the module Configuration Panel (`MPCUSTOMERINVOICE_VAT_RATE`) to allow configuring the VAT rate used for this rounding calculation (defaults to `22.0%`).

### D. Payment Fees Integration (`calcFees`)

- Integrated payment fee calculation via the `mppaymentswithfees` module helper class:
    - Dynamically checks if the module `mppaymentswithfees` is enabled and if `MpSoft\MpPaymentsWithFees\Helpers\Fees` exists.
    - Calculates fees with/without VAT and fills `fee_tax_excl`, `fee_tax_rate`, and `fee_tax_incl` dynamically in the XML output.

### E. Subject Mapping (`$subjects`)

- Adjusted the customer subject logic in `ExportManager.php` to map `customer_invoice.type` directly to `$this->subjects` array values:
    - `PRIVATO` $\rightarrow$ `F`
    - `PARTITA_IVA` $\rightarrow$ `G`
    - `ENTE` $\rightarrow$ `E`
    - Default/Empty fallback $\rightarrow$ `--` (or auto-resolved to `G` if vat/company fields are present).

### G. Document Generation Restrictions (`GenerateDocumentRestrictions.php`)
- Implemented `GenerateDocumentRestrictions` listening on `actionOrderStatusUpdate` and `actionOrderStatusPostUpdate`.
- Checks `customer_invoice` table for `id_customer`:
  - If `vat_number` or `dni` is non-empty $\rightarrow$ Creates/forces **Invoice ONLY** (`number > 0`, `delivery_number = 0`).
  - Otherwise $\rightarrow$ Creates/forces **Delivery ONLY** (`delivery_number > 0`, `number = 0`).
- Enforces strict mutual exclusion ("mai tutti e due assieme").
- Created `upgrade/upgrade-1.3.63.php` to register `actionOrderStatusUpdate` and `actionOrderStatusPostUpdate` automatically during module update.

### H. Backoffice Menu Interception & PrestaShop Switches (v1.3.72)

- Added configuration switches `MPCUSTOMERINVOICE_OVERRIDE_ORDERS` and `MPCUSTOMERINVOICE_OVERRIDE_CUSTOMERS` in `configuration.html.twig`.
- Implemented `hookActionDispatcherBefore` in `mpcustomerinvoice.php` to intercept accesses to native `/sell/orders/` and `/sell/customers/` backoffice list pages when enabled, redirecting to `AdminMpCustomerInvoice` (`showOrdersPage` / `showCustomersPage`).

### I. AdminOrders Toolbar Button & Reusable Export Helper (v1.3.73)

- Implemented `hookActionGetAdminToolbarButtons` in `mpcustomerinvoice.php` to inject the **Esporta XML** (`btnExportDocuments`) button into the native PrestaShop order detail page toolbar.
- Created `AdminOrderExportHelper` (`views/assets/js/admin/adminOrderExportHelper.js`) to provide a single reusable JS export handler (`exportXML(idOrder)` / `AdminOrderExportHelper.exportDocument`) shared between the toolbar button and the orders data table.

### J. Automatic Document Creation & `invoice_requested` Field (v1.3.74 - v1.3.75)

- Added `invoice_requested` (TINYINT) column to `customer_invoice` DB table & `ModelCustomerInvoice` class, updated automatically on address submission (`want_invoice`).
- Converted target order state select (`MPCUSTOMERINVOICE_ORDER_STATE_TRIGGER`) to a multiple Chosen select, allowing users to choose multiple order states for automatic document creation.
- Enhanced hook logic in `mpcustomerinvoice.php` to prevent duplicate document creation (checks `$order->hasInvoice()` and `$order->delivery_number` before creation).
- Created `upgrade/upgrade-1.3.74.php` to handle database column addition and hook registrations during module update.

### K. Default Admin Controller Tab (v1.3.76)

- Set `renderSetupPage()` as default action in `AdminMpCustomerInvoice.php` (`initContent()`), ensuring the **Configurazione** tab opens by default when accessing the module's admin controller.

### L. PrestaShop Notice Message for ID Eurosolution (v1.3.77)

- Replaced browser `alert()` popups with PrestaShop's native `showNoticeMessage()` (and fallback `showSuccessMessage()` / `showErrorMessage()`) in `id_eurosolution.html.twig`.

### M. Customer Invoice Requested Badge on Order Detail (v1.3.78 - v1.3.80)

- Enhanced ID extraction (matches `#108802` from header text) and automatic fallback URL resolution for admin AJAX controller.
- Dynamically appends native badge `<span class="badge badge-warning">Richiede Fattura</span>` inside `.customer-groups` in `#customerInfo` when `invoice_requested = 1` or fiscal data (VAT/DNI) is set for the customer.

### N. Clickable Customer Link & Invoice Badge in Orders Table (v1.3.81)

- Updated `ajaxProcessRenderOrdersData` query to include `o.id_customer`, `ci.invoice_requested`, `ci.vat_number`, `ci.dni`.
- Made customer name in `adminTableOrders.js` a clickable link (`target="_blank"`) pointing to PrestaShop `AdminCustomers` view.
- Added `<span class="badge badge-warning">Richiede Fattura</span>` under customer name in the table row when invoice is requested (`invoice_requested = 1` or fiscal info exists).

### P. Document Status Indicators in Orders Table (v1.3.83)

- Enhanced `ajaxProcessRenderOrdersData` in [AdminMpCustomerInvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/controllers/admin/AdminMpCustomerInvoice.php) to query document presence flags (`has_invoice`, `has_delivery`, `has_brt` via `_DB_PREFIX_brt_restapi_shipment_response`).
- Updated `formatCustomerColumn` in [adminTableOrders.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminTableOrders.js) to render modern Shadcn-style icon badges under customer name:
  - **Nessuno**: grey `do_not_disturb_on` icon badge (`title="Nessun documento creato"`).
  - **Fattura**: green `receipt_long` icon badge (`title="Fattura creata (N. X)"`).
  - **Nota di Vendita**: blue `article` icon badge (`title="Nota di vendita creata (N. Y)"`).
  - **Segnacollo BRT**: red `local_shipping` icon badge (`title="Segnacollo BRT creato"`).

### S. Fix Layout Intestazione Stampa Ordine (v1.5.127)

- In [PdfOrder.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/Templates/Orders/Dalavoro/Pdf/PdfOrder.php#L443), rimossa la funzione `writeHeaderOrderNum` a coordinate Y=2mm che creava una duplicazione ed uno spazio bianco vuoto in testa alla pagina.
- Riposizionato il logo in alto a sinistra ($X=10, Y=10$) e il titolo **Ordine: NNNNN** in grassetto 18pt in alto a destra ($X=90, Y=10$) con tutti i dettagli dell'ordine (`del DD/MM/YYYY`, `Stato corrente`, `Data di stampa`, `Tipo di Pagamento`) posizionati compattamente al di sotto.

### R. Icona Cornetta Telefonica Etichetta Indirizzo (v1.5.126)

- In [PrintPdfAddress.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfAddress.php#L210), inserita l'icona della cornetta telefonica (`☎`) a sinistra del numero di telefono nel riquadro dell'etichetta indirizzo utilizzando il font Unicode `dejavusans` per un'identificazione visiva immediata.

### Q. Intercezione Generazione Manuale Documento & Cambio Dicitura (v1.5.125)

- **Ristrutturazione `GenerateDocumentRestrictions.php`:**
  - Mantenuta intatta la firma ed il funzionamento di `handleAutomaticDocumentGeneration(array $params)` per le chiamate da hook di cambio stato ordine (`hookActionOrderStatusPostUpdate`).
  - Estratta la logica comune di creazione/restrizione documento nel metodo helper `processDocumentGenerationForOrder(int $idOrder): bool`.
  - Creato il metodo dedicato `handleManualDocumentGeneration(int $idOrder): bool` per la generazione manuale (bypassa i trigger di stato ordine).
- **Intercezione via Frontend JS & Backend Fallback:**
  - In [adminOrderExportHelper.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminOrderExportHelper.js#L173), implementato `bindGenerateDocumentFormSubmit()` che intercetta la submit del form "Genera documento" lato browser, invia la richiesta tramite chiamata AJAX a `AdminMpCustomerInvoice::ajaxProcessHandleGenerateInvoice` ed effettua il `reload()` della pagina senza mai reindirizzare alla dashboard.
  - In [mpcustomerinvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/mpcustomerinvoice.php#L236), mantenuta l'intercezione backend di fallback in `hookActionDispatcherBefore` usando `HTTP_REFERER` ed interruzione dell'esecuzione (`exit;`). Rimosso il blocco ridondante da `hookActionDispatcherAfter`.
  - Registrati ed implementati gli hook `hookActionOrderInvoiceAdd` e `hookActionObjectOrderInvoiceAddAfter` per catturare ogni creazione di `OrderInvoice`.
- **Cambio Dicitura Pulsante a "Genera documento":**
  - Inserito in [adminOrderExportHelper.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminOrderExportHelper.js) il metodo `updateGenerateInvoiceButtonLabel()` per aggiornare automaticamente l'etichetta del pulsante della scheda ordine da **"Genera fattura"** a **"Genera documento"**.

### O. PrintPdf Architecture & Admin Toolbar Print Button (v1.3.82)

- Created abstract parent class [PrintManager.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintManager.php) in `src/PrintPdf/` namespace `MpSoft\MpCustomerInvoice\PrintPdf`.
- Created 5 specialized print classes extending `PrintManager`:
  - `PrintPdfOrder.php`
  - `PrintPdfInvoice.php`
  - `PrintPdfDelivery.php`
  - `PrintPdfReturn.php`
  - `PrintPdfAddress.php`
- Added **"Stampe"** button in `AdminOrders` back-office toolbar via `hookActionGetAdminToolbarButtons`.
- Reused `<dialog id="order-action-dialog">` component with options (Ordine, Fattura, Spedizione, Nota di Reso, Etichetta Indirizzo).
- Added `copies` input selector when Etichetta Indirizzo is chosen.
- Implemented `ajaxProcessRenderPdfDocument` endpoint in `AdminMpCustomerInvoice.php` calling `renderPdf()` to verify execution.

### P. Order Page PDF Generation via OrderPage Architecture (v1.3.83)

- Connected [PrintPdfOrder.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfOrder.php) to `src/v16/pdf/OrderPage/PdfOrderPage.php`.
- Added safety checks in `PDFOrderDocument.php`, `PdfOrderBody.php`, and `PdfPageCustomizations.php` for `CustomProducts` and `OrderTools`.
- Added automatic fallback data resolution via `\MpSoft\MpCustomerInvoice\Export\ExportOrder`.
- Verified generation of TCPDF documents.

### Q. Refactoring PrintPdf Architecture to src/PrintPdf/ (v1.3.84)

- Created `PrintManager` in `src/PrintPdf/PrintManager.php` (`MpSoft\MpCustomerInvoice\PrintPdf\PrintManager`) extending `TCPDF` with full document setup, margin handling, logo resolving, order data loading, and component orchestration.
- Created modular PSR-4 components in `src/PrintPdf/Components/`:
  - `PdfOrderHeader.php`
  - `PdfOrderBody.php`
  - `PdfOrderFooter.php`
  - `PdfHeaderRight.php`
  - `PdfOrderAddresses.php`
  - `PdfOrderInfo.php`
- Updated concrete print classes: `PrintPdfOrder`, `PrintPdfInvoice`, `PrintPdfDelivery`, `PrintPdfReturn`, and `PrintPdfAddress`.
- Removed temporary reference folder `src/v16/`.
- Verified execution of all 5 PDF renderers in Docker container.

### R. Label Dimensions in Configuration Page & PrintPdfAddress (v1.3.85)

- Added "Dimensioni Etichetta (Stampa PDF)" fieldset in [configuration.html.twig](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/twig/admin/configuration.html.twig) with `MPCUSTOMERINVOICE_LABEL_WIDTH` and `MPCUSTOMERINVOICE_LABEL_HEIGHT` inputs (mm).
- Updated `renderSetupPage()` and `saveSetupConfiguration()` in [AdminMpCustomerInvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/controllers/admin/AdminMpCustomerInvoice.php) for read/write of both keys (defaults: 100×50mm).
- Rewrote [PrintPdfAddress.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfAddress.php) to read label dimensions from Configuration, set custom page format and orientation, render delivery address with `PdfOrderAddresses`, and generate N copies.
- Extended `PrintManager` constructor to accept `$orientation` and `$pageFormat` parameters.

---

### S. AJAX Save Configuration with Modal Feedback (v1.3.86)

- Changed "Salva" button from `type="submit"` to `type="button"` in [configuration.html.twig](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/twig/admin/configuration.html.twig).
- Save now uses `fetch` POST `x-www-form-urlencoded` instead of form submit + page reload.
- Overlay modal shows spinner during save, then switches to success (✓ green) or error (✗ red) with message from server and a "Chiudi" button.
- Added `ajaxProcessSaveConfiguration()` in [AdminMpCustomerInvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/controllers/admin/AdminMpCustomerInvoice.php) that reuses `saveSetupConfiguration()` and returns JSON.

### T. Full Address Label Printing Implementation (v1.3.87)

- Rewrote [PrintPdfAddress.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfAddress.php) with complete label rendering:
  - Each copy is a separate page with progressive numbering `<id_order>-<N>` (e.g. 23638-1, 23638-2, 23638-3, 23638-4).
  - Dark header bar with order number + date, white address box with rounded corners.
  - Address block shows company/name, street, postcode/city/province, country, phone.
  - Auto-scaling font size to fit available label space.
  - TCPDF page format set from Configuration (LABEL_WIDTH × LABEL_HEIGHT).
- Tested: 4 copies of order 23638 → 107KB PDF generated successfully.

### U. Fix Configuration Save & Reload Persistence (v1.3.88)

- Fixed bug in `saveSetupConfiguration()` and `getSetupConfig()` in [AdminMpCustomerInvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/controllers/admin/AdminMpCustomerInvoice.php) where falsy values (`0`, `"0"`, `""`, `false`, `[]`) were rejected by `$value ?: $default` and replaced with defaults upon page reload.
- Updated `getSetupConfig()` to check `$value === false || $value === null` before returning default, preserving explicit zero and empty string values saved in `ps_configuration`.
- Refactored `URLSearchParams(new FormData(form))` in [configuration.html.twig](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/twig/admin/configuration.html.twig) to properly encode chosen multiselect array fields and radio inputs.
- Verified persistence via CLI test script.

### V. Major Release 1.4.89: Full Print Engine & Label System

- Released version **1.4.89** containing the complete implementation of the PDF printing engine and address label printing:
  - **PSR-4 Print Engine (`src/PrintPdf/`)**: `PrintManager` abstract class, `PrintPdfOrder`, `PrintPdfInvoice`, `PrintPdfDelivery`, `PrintPdfReturn`, and `PrintPdfAddress`.
  - **Address Label Printing (`PrintPdfAddress`)**: Multi-copy generation with progressive order numbering (`<id_order>-<N>`), auto-scaling font size, rounded address box, custom dimensions read from module setup.
  - **Config Panel & AJAX**: Save configuration reusing the native `Tools::isSubmit('submitConfiguration')` in `renderSetupPage()`, outputting JSON response when `ajax=1` without redundant methods or duplicated logic. Full-screen overlay spinner with dynamic success/error feedback.

### W. Fix PDF Render AJAX Response & Auto-Open Tab (v1.4.90)

- Fixed `SyntaxError: Unexpected token '%'` in `adminOrderExportHelper.js` caused by TCPDF inline output stream.
- Updated `ajaxProcessRenderPdfDocument` in [AdminMpCustomerInvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/controllers/admin/AdminMpCustomerInvoice.php) to render PDF with `$outputMode = 'S'` and include `base64` encoded PDF inside the JSON response.
- Updated `executePrint` in [adminOrderExportHelper.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminOrderExportHelper.js) to convert base64 to Blob and auto-open PDF in a new browser tab (`window.open`).

### X. Bypass Browser Popup Blocker for PDF Tab Opening (v1.4.91)

- Fixed silent popup blocking by Chrome/Firefox/Safari when calling `window.open()` after async `await fetch()` in [adminOrderExportHelper.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminOrderExportHelper.js).
- `executePrint` now opens a target tab (`about:blank`) immediately upon user click/submit, and updates its `location.href` to the PDF `Blob` URL once `fetch` completes, cleanly bypassing popup blockers.

### Y. Unify Order List Label Action Button (`.js-order-action-label`) (v1.4.92)

- Updated `.js-order-action-label` click handler in [adminTableOrders.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminTableOrders.js) to trigger `AdminOrderExportHelper.openPrintDialog(orderId)`.
- The label button in the order list now shares the exact same print dialog (Order, Invoice, Delivery, Address Label + copies input) as the "Stampe" button on the order detail page.

### Z. Add BRT Label Option & Robust Dialog Trigger (v1.4.93)

- Added "Segnacollo Bartolini" (`brt`) radio option to `openPrintDialog` in [adminOrderExportHelper.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminOrderExportHelper.js) (showing copies input when either `address` or `brt` is selected).
- Strengthened `window.AdminOrderExportHelper` binding in [adminTableOrders.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminTableOrders.js) to ensure `.js-order-action-label` in order list opens the unified print dialog reliably.

### AA. Complete Unification of "Stampa documento" Modal (v1.4.94)

- Replaced old dialog template in [adminTableOrders.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminTableOrders.js) with the exact unified layout (2nd image).
- Unified title to **"Stampa documento"** with options: Ordine, Fattura, Spedizione, Etichetta Indirizzo, Segnacollo Bartolini.
- Dynamic toggle for copies selector container when either Address Label or Bartolini is selected.

### BB. Code Refactoring & Centralized Helper (v1.4.95)

- Completely removed duplicated dialog logic (`getDialog`, `openActionDialog`, `buildDialogBody`, `handleDialogSubmit`) from [adminTableOrders.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminTableOrders.js).
- Centralized all print and export dialog rendering and execution in the standalone, reusable helper class `AdminOrderExportHelper` in [adminOrderExportHelper.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminOrderExportHelper.js).

### CC. Standalone Component MpPrintDialog.js (v1.4.96)

- Extracted the print modal rendering, event handling, popup blocker bypass, and AJAX fetch logic into an independent JS class [MpPrintDialog.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/MpPrintDialog.js).
- Added `MpPrintDialog.js` inclusion in `hookActionAdminControllerSetMedia` of [mpcustomerinvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/mpcustomerinvoice.php).
- Delegated print triggers in [adminOrderExportHelper.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminOrderExportHelper.js) and [adminTableOrders.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminTableOrders.js) to `MpPrintDialog.open(idOrder)`.

### DD. Fix Asset Inclusion & Global Label Click Listener (v1.4.97)

- Added `AdminMpCustomerInvoice` controller to the JS asset loading rule in [mpcustomerinvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/mpcustomerinvoice.php).
- Added automatic global delegated click listener on `document` inside [MpPrintDialog.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/MpPrintDialog.js) for `.js-order-action-label` and `.js-order-action-print`.
- Enhanced safe `orderId` extraction (`data-order-id`, `dataset.orderId`, jQuery `.data()`) in [adminTableOrders.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminTableOrders.js).

### EE. Order PDF Render & Data Fix (v1.4.98)

- Normalized `orderData` array structure in [PrintManager.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintManager.php) so that all child components receive populated data (`invoice`, `invoices`, etc.).
- Fixed data mapping in [PdfHeaderRight.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/Components/PdfHeaderRight.php), [PdfOrderAddresses.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/Components/PdfOrderAddresses.php), and [PdfOrderInfo.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/Components/PdfOrderInfo.php) (including red **`V`** badge for requested invoices).
- Implemented full products table rendering in [PdfOrderBody.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/Components/PdfOrderBody.php) with product image, reference, red warehouse location, blue attributes, uppercase name, blue QTY, red MAG stock, verification status, and unit price.

### FF. Product Location Under Attributes in Red (v1.4.99)

- Implemented dynamic location extraction from `StockAvailable` and `ps_stock_available` database table in [ExportManager.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/Export/ExportManager.php).
- Updated RIFERIMENTO column rendering order in [PdfOrderBody.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/Components/PdfOrderBody.php) to print the reference (bold black), attribute combinations (bold blue), and the location (bold red) placed directly below the combinations.

### GG. Shipping Address Label Exact PDF Restyle (v1.4.100)

- Completely redesigned [PrintPdfAddress.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfAddress.php) to match the exact physical label layout from the user's photo.
- Layout elements:
  - Top centered logo.
  - Bordered box for Order ID (left) and Copy number (right).
  - Centered recipient line (`COMPANY` + `FIRSTNAME LASTNAME`, deduped if identical).
  - Upper-case address, city line (`CAP - CITY - PROVINCE`), country and phone.
  - Bottom left: Bordered box for **TOTALE DA PAGARE** with amount.
  - Bottom right: **1D Barcode (Code 128)** with progressive order label text (`<id_order>-<copy>`).

### HH. Address Label Spacing, Font Size & COD Conditional Fix (v1.4.101)

- In [PrintPdfAddress.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfAddress.php):
  - Fixed logo overlapping: dynamically calculated exact proportional height of the logo image (`$logoH`) so that Order ID & Copy boxes are placed cleanly below the logo.
  - Increased address font size to `11.5pt` (and phone number to `12pt`) for improved legibility.
  - Implemented `isCashOnDelivery()` check: **TOTALE DA PAGARE** box now appears **only** when the payment method is Cash on Delivery / Contrassegno.

### II. Strict Single Page Per Label Copy Fix (v1.4.102)

- In [PrintPdfAddress.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfAddress.php):
  - Completely disabled TCPDF automatic page breaks (`$this->SetAutoPageBreak(false, 0)`).
  - Dynamically budgeted vertical space (`$maxCenterH`) between top boxes and fixed bottom footer to auto-scale cell line height (`$lineH`) and font sizes (`$recipientFontSize`, `$addressFontSize`).
  - Ensured that each copy of an address label fits strictly onto 1 single physical page.

### JJ. Centered Address, Larger Font & Phone Box Above Barcode (v1.4.103)

- In [PrintPdfAddress.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfAddress.php):
  - Centered all address lines (Address 1/2, CAP - City - Province, Country) and increased font size to `13pt`.
  - Moved the phone number into a dedicated bordered rectangle box positioned directly above the Code128 barcode on the right.

### KK. Shift 1cm Down for Order Boxes & Address Data (v1.4.104)

- In [PrintPdfAddress.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfAddress.php):
  - Shifted down by `10mm` (1 cm) the Order ID box, Copy progress box, and all address data.
  - Kept fixed in their original bottom positions: Phone number box, Code128 Barcode, and Contrassegno box.

### LL. Robust Cash-On-Delivery Detection Fix (v1.4.105)

- In [PrintPdfAddress.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/src/PrintPdf/PrintPdfAddress.php):
  - Enhanced `isCashOnDelivery()` to inspect both `$this->order` and `$this->orderData['invoice']`.
  - Added checks for extra COD fee amounts (`fees`), additional modules (`mppaymentswithfees`, `cashondeliverywithfee`), and fuzzy keyword matching (`contrassegno`, `cash on delivery`, `cod`, `alla consegna`, `contanti alla consegna`).

### MM. Remove Redundant Label Action Button & Consolidate Print Action (v1.4.106)

- In [adminTableOrders.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/adminTableOrders.js):
  - Removed `.js-order-action-label` button from the table action formatter.
  - Re-arranged the action buttons into a clean 3-item row layout: **Vedi ordine** (`visibility`), **Stampa** (`.js-order-action-print`), and **Esporta** (`.js-order-action-export`).
  - Clicking **Stampa** (`.js-order-action-print`) triggers `MpPrintDialog.open(orderId)` which manages all document and label print options.

### NN. Hide Native Order View Print Button (`.js-print-order-view-page`) (v1.4.107)

- Added CSS override in [theme-override.css](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/css/theme-override.css) to hide `.js-print-order-view-page` with `display: none !important`.
- Added dynamic DOM enforcement in [MpPrintDialog.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/MpPrintDialog.js) to ensure `.js-print-order-view-page` remains hidden.

### OO. Hide Native View Invoice Button (`[data-role="view-invoice"]`) (v1.4.108)

- Updated [theme-override.css](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/css/theme-override.css) and [MpPrintDialog.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/MpPrintDialog.js) to hide `[data-role="view-invoice"]` alongside `.js-print-order-view-page`.

### QQ. Dalavoro Print Templates (Fattura & Nota di Vendita) (v1.5.120)

- Implemented native TCPDF PDF print templates for **Fattura** (`FATTURA WEB/D`) in `src/PrintPdf/Templates/Invoices/Dalavoro/` and **Nota di Vendita** (`NOTA VENDITA WEB`) in `src/PrintPdf/Templates/Deliveries/Dalavoro/`.
- **Risoluzione Pagina Bianca Iniziale**: Eliminata la chiamata ridondante a `AddPage()` in `drawDocument()` quando la pagina 1 è già aperta da `PrintManager`, garantendo che il documento inizi direttamente a pagina 1 senza pagine bianche preliminari.
- **Gestione Commissioni Pagamento (`mp_payment_fee_order`)**:
  - Intercettazione della commissione di pagamento per l'ordine interrogando la tabella `ps_mp_payment_fee_order` dove `id_order = <id_order>`.
  - Lettura dei campi `total_order`, `fee_amount` e `tax_included`. Nella tabella ordini del backoffice, l'importo base dell'ordine viene estratto direttamente da `mp_payment_fee_order.total_order` poiché il totale in `orders` comprende già la commissione.
  - Se `tax_included = 1`, viene eseguito prima lo **scorporo dell'IVA** dalla commissione: `fee_excl = round(fee_amount / (1 + vat_rate / 100), 6)`.
  - La commissione al netto d'IVA (`fee_excl`) viene sommata al totale imponibile senza IVA (`imponibile` prodotti + spedizione/imballaggio).
  - Successivamente si procede al calcolo dell'IVA sull'imponibile totale ed alla determinazione dell'eventuale `ARROTONDAMENTO` rispetto all'importo totale pagato dal cliente (`total_paid_tax_incl`).
- **Esenzione IVA & Clienti Esteri**: Gestione automatica esenzione per fatture estere/NI7 con dicitura **`Cessioni CEE art.41 DL.331/93`** nel riquadro IVA e colonna IVA `NI7`.
- Updated `PrintTemplateFactory.php` singular type mapping for `Deliveries` -> `Delivery`.

### RR. Fees Fix, Shadcn Notes Popover, Document Selection & Wrong Document Warning (v1.5.121)

- **Fix Sezione `<fees>` Esportazione Data (`ExportManager.php`, `PdfInvoice.php`, `PdfDelivery.php`)**:
  - Risolto bug nell'esportazione dati XML e PDF dove `fee_tax_excl` e `fee_tax_incl` venivano estratti uguali anche in presenza di IVA al 22%.
  - Lettura prioritaria da `ps_mp_payment_fee_order` ed applicazione dello scorporo IVA (`fee_tax_excl = fee_amount / (1 + vat_rate / 100)`).
- **Popup Ultimi Messaggi Note (Shadcn/UI)**:
  - Implementato l'endpoint AJAX `ajaxProcessGetOrderLatestNotes` per recuperare l'ultimo messaggio per ciascuna tipologia (`order`, `customer`, `embroidery`).
  - Creata l'integrazione frontend in `adminTableOrders.js` con barra di avanzamento e popup fissa al viewport.
  - Aggiunta la configurazione `MPCUSTOMERINVOICE_NOTES_HOVER_DELAY` per consentire all'amministratore di scegliere il ritardo dell'hover (default: 1 secondo).
- **ID Cliente nel Title Colonna Cliente**:
  - Aggiornato `formatCustomerColumn` in `adminTableOrders.js` per includere l'ID nel `title` (`title="(<id>) Vedi scheda cliente"`).
- **Fix Preselezione Documento (Fattura vs Nota di vendita)**:
  - Corretto `ajaxProcessGetOrderPrintInfo` per dare priorità a `GenerateDocumentRestrictions::isInvoiceRequired($idCustomer)` quando `invoice_number = 0`.
- **Avviso "Documento errato" in Elenco Ordini**:
  - Calcolo del flag `is_invalid_document` in `ajaxProcessRenderOrdersData` ed inserimento del badge rosso **`Documento errato`** nella colonna Cliente dell'elenco ordini quando l'ordine ha generato un documento non conforme alla scelta del cliente (`invoice_requested`).

### SS. Skip Missing Documents in Batch Print (v1.5.122)

- **Filtro Ordini Senza Documento nella Stampa Massiva**:
  - In `ajaxProcessRenderBatchPdfDocuments()` ([AdminMpCustomerInvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/controllers/admin/AdminMpCustomerInvoice.php#L687-L730)), la scansione degli ordini controlla l'effettiva presenza del documento richiesto (`invoice` o `delivery`).
  - Se un ordine della selezione non possiede il documento scelto (es. cliente senza fattura o nota di vendita non generata), l'ordine viene saltato senza bloccare la stampa massiva per gli altri ordini validi.

### TT. Order Invoice Direct Search in Batch Print (v1.5.123)

- **Ricerca Diretta Tabelle `ps_order_invoice` per Stampa Massiva**:
  - In `ajaxProcessRenderBatchPdfDocuments()` ([AdminMpCustomerInvoice.php](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/controllers/admin/AdminMpCustomerInvoice.php#L697-L745)):
  - **Fatture**: interroga `ps_order_invoice` dove `id_order = <id_order>` e `number > 0`. Salta se non ce ne sono, oppure cicla e stampati tutti i documenti di fattura associati a quel numero d'ordine.
  - **Note di vendita**: interroga `ps_order_invoice` dove `id_order = <id_order>` e `delivery_number > 0`. Salta se non ce ne sono, oppure cicla e stampa tutti i documenti di spedizione associati a quel numero d'ordine.

### UU. Direct Print Engine Migration to QZ Tray v2.2.6 (v1.6.0)

- **Migrazione Completa da WebPrint.jar a QZ Tray v2.2.6**:
  - Rimosso `WebPrint.jar` e la libreria `webprint.js`.
  - Inclusa la libreria client ufficiale QZ Tray v2.2 [`qz-tray.js`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/qz-tray.js) per la gestione dell'handshake WebSocket con il daemon locale (gestione automatica porte secure WSS `8181, 8282, 8383, 8484` ed insecure WS `8182, 8283, 8384, 8485`, fallback hosts `127.0.0.1`, `localhost`, `localhost.qz.io`).
  - Aggiornata la scheda di configurazione del modulo ([configuration.html.twig](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/twig/admin/configuration.html.twig)) fornendo i link diretti ai due eseguibili di installazione:
    - **Windows (.exe)**: `qz-tray-2.2.6-x86_64.exe`
    - **Linux (.run)**: `qz-tray-2.2.6-x86_64.run`
  - Implementato il rilevamento automatico delle stampanti collegate (`qz.printers.find()`) con aggiornamento dinamico delle select nel pannello di configurazione.
  - Aggiornati `executePrint()` ed `executeBatchPrint()` in [MpPrintDialog.js](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/MpPrintDialog.js) per inviare direttamente i PDF in formato Base64 (`type: 'pixel', format: 'pdf', flavor: 'base64'`) con configurazione per il numero di copie (`qz.configs.create(printer, { copies })`) a QZ Tray senza anteprima o popup del browser.
  - Mantenute le configurazioni personalizzate per ciascun operatore (`id_employee_qztray`).

### VV. Fix Label 90 Degree Rotation Bug in QZ Tray (v1.6.1)

- **Risoluzione Bug Rotazione Stampa Etichette PDF**:
  - **Allineamento Dimensioni Carta / Etichetta**: In `MpPrintDialog.js`, le stampe di etichette indirizzo (`address`) e segnacolli BRT (`brt`) inviano a QZ Tray le dimensioni esatte della pagina (`size: { width: labelW, height: labelH }, units: 'mm'`, `margins: 0`).
  - **Rilevamento Automatico Orientamento**: Calcolato l'orientamento corretto (`orientation: 'landscape'` quando `labelWidth >= labelHeight`) per impedire che la libreria Java PDFBox di QZ Tray ruoti i PDF orizzontali di 90° su foglio verticale.
  - **Impostazioni Operatore Avanzate (Orientamento, Rotazione, Rasterizzazione)**: Aggiunte in `AdminMpCustomerInvoice.php` e `configuration.html.twig` tre nuove opzioni di stampa per operatore:
    - **Orientamento**: `auto` (Automatico), `landscape` (Orizzontale), `portrait` (Verticale).
    - **Rotazione**: `0°`, `90°`, `180°`, `270°`.
    - **Rasterizzazione PDF**: `0` (Disattivata - Vettoriale) / `1` (Attivata - Rasterizza PDF in immagini bitmap per bypassare bug dei driver).
  - **Rimozione Trasparenza (`ignoreTransparency: true`)**: Attivata la rimozione dei livelli di trasparenza consigliata da QZ Tray per stampanti termiche.

### WW. Per-Printer QZ Tray Advanced Settings & Paper Formats (v1.6.2)

- **Configurazione Indipendente per Ciascuna Stampante**:
  - Ciascuna delle 5 stampanti (Ordini, Fatture, Spedizione/Consegne, Indirizzo, Segnacollo BRT) è racchiusa in una scheda card dedicata con i seguenti controlli:
    1. **Formato Carta**: Dropdown con formati standard (*A4*, *A5*, *Letter*, *Legal*, *Etichetta BRT 100x65mm*, *Etichetta Indirizzo 100x100mm*, *100x50mm*, *100x80mm*) ed opzione **Misura Personalizzata (mm)**.
    2. **Dimensioni mm (L x H)**: Input numerici per specificare larghezza ed altezza personalizzate (es. 130x200 mm) quando viene selezionato "Misura Personalizzata".
    3. **Orientamento**: Automatico, Orizzontale (Landscape), Verticale (Portrait).
    4. **Rotazione Gradi**: 0°, 90°, 180°, 270°.
    5. **Modalità PDF**: Vettoriale (`rasterize: false`) / Rasterizza PDF (`rasterize: true`).
- **Integrazione JS & Multi-Operatore**:
  - [`AdminMpCustomerInvoice.php`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/controllers/admin/AdminMpCustomerInvoice.php) salva e carica la mappa completa delle impostazioni per ciascun operatore.
  - [`MpPrintDialog.js`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/MpPrintDialog.js) applica le dimensioni, l'orientamento, la rotazione e la rasterizzazione specifiche della stampante corrispondente al documento che si sta stampando.

### XX. Shadcn UI Compact Restyling for Printer Cards (v1.6.3)

- **Restyling Grafico Scheda Stampanti in Stile Shadcn UI**:
  - Applicato uno stile pulito e moderno senza gradienti per le schede delle 5 stampanti.
  - Sfondo neutro chiaro `#f8fafc` con bordo sottile `#e2e8f0` ed effetto subtle hover.
  - Badge pastello tenui dedicati per le icone di ogni categoria:
    - Ordini: Soft Blue (`#eff6ff`, text `#2563eb`)
    - Fatture: Soft Emerald (`#ecfdf5`, text `#059669`)
    - Spedizione: Soft Amber (`#fffbeb`, text `#d97706`)
    - Indirizzo: Soft Purple (`#faf5ff`, text `#7c3aed`)
    - Segnacollo BRT: Soft Rose (`#fff1f2`, text `#e11d48`)
  - Form controls compatti con altezza `34px`, border-radius `6px`, etichette uppercase in grigio slate (`#64748b`) e focus ring `3px rgba(59, 130, 246, 0.12)`.

### YY. Full-Width Flexbox Controls Restyling for QZ Tray Cards (v1.6.4)

- **Espansione 100% per i Menu a Tendina e Campi Stampa**:
  - Sostituita la griglia fissa Bootstrap con un layout flexbox fluido `.shadcn-options-row` (`display: flex; width: 100%; gap: 12px;`).
  - Applicata la regola `width: 100% !important; max-width: 100% !important; display: block !important;` a tutti i controlli `select` ed `input` delle schede stampante.
  - Ciascuna colonna di controllo (`Formato Carta`, `Orientamento`, `Rotazione`, `Modalità PDF`) si espande proporzionalmente per riempire tutto lo spazio orizzontale disponibile, eliminando ogni troncatura delle scritte delle opzioni.

### ZZ. BRT Label A4 Canvas Cropping to Native 100x65mm Landscape Page (v1.6.5)

- **Riconoscimento e Ritaglio Canvas A4 Segnacollo BRT**:
  - In [`BrtPdfMerger.php`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpbrtrestapishipments/src/Helpers/BrtPdfMerger.php), rilevata la presenza di PDF segnacollo BRT generati da BRT Rest API su formato A4 (210 x 297 mm) con etichetta posizionata al centro.
  - Implementato il ritaglio ed estrazione automatica tramite FPDI dell'area di 100x65 mm al centro dell'A4, inserendola su una pagina PDF nativa di formato **100 x 65 mm (Landscape)**.
  - Rimosso l'invio del formato A4 a QZ Tray per la stampa del segnacollo BRT: la stampante termica riceve un PDF nativo 100x65 mm che riempie il 100% della superficie dell'etichetta senza margini A4 e senza rotazione a 90°.

### AAA. Fix Save Configuration Button & jQuery Migrate Logging Mute (v1.6.6)

- **Risoluzione Errore JS Bloccante `Identifier 'selectEmp' has already been declared`**:
  - In [`configuration.html.twig`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/twig/admin/configuration.html.twig), rimossa la dichiarazione duplicata `const selectEmp` a riga 780. L'errore di sintassi bloccava l'esecuzione dello script JS ed impediva l'aggancio del gestore del submit/click `#btnSaveConfig`.
- **Silenziamento Notifiche jQuery Migrate**:
  - Inserito `window.jQuery.migrateMute = true;` in testa allo script della pagina per disabilitare i log di deprecazione di jQuery Migrate in console (`JQMIGRATE: Migrate is installed with logging active`).

### BBB. BRT Label Template 90 Degree Rotation to Native 100x65mm Landscape Page (v1.6.7)

- **Rotazione Matrice TCPDF di 90° e Ritaglio Segnacollo BRT**:
  - Rilevato che BRT REST API disegna i contenuti del segnacollo ruotati di 90° verticalmente al centro di una pagina A4 (210 x 297 mm).
  - In [`BrtPdfMerger.php`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpbrtrestapishipments/src/Helpers/BrtPdfMerger.php), applicata la trasformazione `$pdf->StartTransform(); $pdf->Rotate(90, $targetCx, $targetCy); ... $pdf->StopTransform();` durante l'importazione del modello su pagina nativa **100x65 mm Landscape**.
  - **Risultato**: Il PDF generato è nativamente di 100x65 mm con testo dell'etichetta orientato in orizzontale (da sinistra a destra), occupando la totalità dell'etichetta termica senza margini A4 e senza ruotare sulla stampante.

### CCC. QZ Tray Explicit Landscape Orientation & Size Configuration Fix (v1.6.8)

- **Forzatura Esplicita dell'Opzione `orientation: 'landscape'` per QZ Tray**:
  - In [`MpPrintDialog.js`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpcustomerinvoice/views/assets/js/admin/MpPrintDialog.js), allineata la configurazione `qz.configs.create` con le opzioni richieste:
    ```javascript
    configOpts.size = { width: paperW, height: paperH };
    configOpts.units = 'mm';
    configOpts.margins = 0;
    configOpts.orientation = (paperW >= paperH) ? 'landscape' : 'portrait';
    configOpts.scaleContent = true;
    ```
  - Quando l'orientamento è `auto` ed il foglio è 100x65 mm, l'opzione `orientation: 'landscape'` viene inviata esplicitamente a QZ Tray, impedendo al driver di stampa e a PDFBox di usare l'orientamento predefinito Portrait dell'A4.
- **Preservazione PDF Nativo 100x65mm**: Ripristinata in [`BrtPdfMerger.php`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpbrtrestapishipments/src/Helpers/BrtPdfMerger.php) la gestione del PDF sorgente in formato nativo 100x65mm senza trasformazioni di rotazione forzate.

### DDD. Direct Pass-through of Native 100x65mm BRT Label Fix (v1.6.9)

- **Eliminazione Shift di Ritaglio Errato**:
  - In [`BrtPdfMerger.php`](file:///home/massimiliano/docker/apache/ps_workwear/prestashop/modules/mpbrtrestapishipments/src/Helpers/BrtPdfMerger.php), corretto l'algoritmo FPDI che interpretava le dimensioni in punti (283.46 pt x 184.25 pt = 100x65 mm) applicando uno spostamento fuori margine ed oscurando la prima parte del barcode (`015` e `15-`).
  - Ripristinato il pass-through diretto del PDF nativo `validBins[0]` quando si gestisce un singolo segnacollo BRT, e l'unione a coordinate `(0, 0)` per segnacolli multipli.
  - **Risultato**: Il PDF nativo 100x65 mm viene inviato integro al 100% a QZ Tray senza parti tagliate o nascoste.

## 3. Module Version Tracking
* **Latest Stable Version**: `1.6.9`
- Files updated with version bump:
    - `mpcustomerinvoice.php`
    - `composer.json`
    - `config_it.xml`
    - `README.md` (Changelog updated)
    - `summary.md` (Updated)
    - `session_summary.md` (Updated)

---

## 4. How to Continue in the New IDE

When starting your next session in the new Antigravity IDE:

1. Open the project workspace.
2. Type or paste this prompt:
    > _"Ciao Antigravity, ho aperto il workspace. Si prega di leggere il file `session_summary.md` situato nella directory del modulo `prestashop/modules/mpcustomerinvoice/` per riprendere il lavoro da dove lo abbiamo lasciato."_
3. The assistant will parse this file and immediately have 100% of the context of your codebase, the docker paths, and the exact business logic implemented.

---

## 5. Developer Guidelines & Best Practices (Best Practice dello Sviluppatore)

In any future session, always follow these rules:

- **PrestaShop Version**: 8.2.7 (Docker container: `ps82-workwear-site`).
- **PrestaShop Switch Component HTML (Legacy)**: Usare ESCLUSIVAMENTE la seguente struttura HTML nativa per creare gli switch PrestaShop nei pannelli di configurazione/admin:
  ```html
  <div class="form-group">
      <label>LABEL_TESTO</label>
      <span class="prestashop-switch">
          <input type="radio" name="CAMPO_NAME" id="CAMPO_NAME_on" value="1"{% if VALORE %} checked="checked"{% endif %}>
          <label for="CAMPO_NAME_on">Sì</label>
          <input type="radio" name="CAMPO_NAME" id="CAMPO_NAME_off" value="0"{% if not VALORE %} checked="checked"{% endif %}>
          <label for="CAMPO_NAME_off">No</label>
          <a class="slide-button btn"></a>
      </span>
  </div>
  ```
- **Git Usage**: **NEVER run `git` commands** (e.g. `git status`, `git diff`, `git log`, etc.). The user manages git directly.
- **Composer**: The module uses `composer.json` for dependency management.
- **Template Engine**: Use Twig templates (PS 8.0+). Keep CSS, JS, and HTML separate.
- **JS & Libraries**: Prefer modern Vanilla JS (ES6+). Use jQuery ONLY for components requiring it (e.g. Chosen, BootstrapTable). Use `<template>` tags for dynamic HTML components.
- **Admin & AJAX Controllers**: All AJAX requests must use `fetch()` with `form-urlencoded` format (NEVER JSON). Use Bootstrap Table exclusively for admin lists.
- **Database**: Use PrestaShop `Db::getInstance()`. NEVER use `LIMIT` in queries when calling `getRow()` or `getValue()`. Use prepared/parameterized queries where possible.
- **Versioning & Docs**: Any modification must increment the module version and update `README.md` with a detailed changelog entry.
- **Code Structure**: Extract reusable procedures into Helpers (e.g., classes in `src/` or `classes/Helper/`). Keep JS classes self-contained and modular.
- **Code Reusability & Bug Resolution**: **NEVER rewrite procedures from scratch** when a dedicated helper or method (e.g. `calcFees` or `Fees::calculate`) already exists. If a function contains a bug or unexpected behavior, the **absolute priority is to fix the bug in the existing function**, preserving reusability and avoiding redundant code.
- **UI Components Reusability**: **ALWAYS reuse the `<dialog id="order-action-dialog">` component** (gestito tramite `AdminOrderExportHelper`) per tutti i modali di esportazione/azione sugli ordini. MAI usare `prompt()` o modali nativi del browser.
- **Pop-up & Notifiche JS PrestaShop**: **MAI usare `alert()` o `prompt()` nativi del browser per la notifica di operazioni o esiti**. Usare SEMPRE le funzioni JS native di PrestaShop: `showNoticeMessage()` per avvisi/notifiche, `showErrorMessage()` per errori, `showSuccessMessage()` per conferme di successo.
- **Collaboration**: Be critical and proactive; suggest better technical paths instead of simply complying with everything. Discuss technical choices before writing code.
