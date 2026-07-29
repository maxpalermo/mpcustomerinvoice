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

## 3. Module Version Tracking
* **Latest Stable Version**: `1.4.91`
- Files updated with version bump:
    - `mpcustomerinvoice.php`
    - `composer.json`
    - `config_it.xml`
    - `README.md` (Changelog updated)

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
