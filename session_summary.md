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

### F. Invoice & Delivery Export (`ExportInvoice.php` / `ExportDelivery.php`)
- Updated `ExportInvoice` to query `order_invoice` for `number` (`invoice_number`) and `date_add` (`invoice_date`), matching `invoice.json`.
- Implemented `ExportDelivery` to query `order_invoice` for `delivery_number` (`invoice_number`) and `delivery_date` (`invoice_date`), matching `delivery.json` (`document_type` = `78`).
- Added document existence validation: if `number` (for invoice) or `delivery_number` (for delivery) is missing or `<= 0`, an explicit exception is thrown to inform the user that the document does not exist for that order.

---

## 3. Module Version Tracking
* **Latest Stable Version**: `1.3.61`
- Files updated with version bump:
    - `mpcustomerinvoice.php`
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
- **Git Usage**: **NEVER run `git` commands** (e.g. `git status`, `git diff`, `git log`, etc.). The user manages git directly.
- **Composer**: The module uses `composer.json` for dependency management.
- **Template Engine**: Use Twig templates (PS 8.0+). Keep CSS, JS, and HTML separate.
- **JS & Libraries**: Prefer modern Vanilla JS (ES6+). Use jQuery ONLY for components requiring it (e.g. Chosen, BootstrapTable). Use `<template>` tags for dynamic HTML components.
- **Admin & AJAX Controllers**: All AJAX requests must use `fetch()` with `form-urlencoded` format (NEVER JSON). Use Bootstrap Table exclusively for admin lists.
- **Database**: Use PrestaShop `Db::getInstance()`. NEVER use `LIMIT` in queries when calling `getRow()` or `getValue()`. Use prepared/parameterized queries where possible.
- **Versioning & Docs**: Any modification must increment the module version and update `README.md` with a detailed changelog entry.
- **Code Structure**: Extract reusable procedures into Helpers (e.g., classes in `src/` or `classes/Helper/`). Keep JS classes self-contained and modular.
- **Code Reusability & Bug Resolution**: **NEVER rewrite procedures from scratch** when a dedicated helper or method (e.g. `calcFees` or `Fees::calculate`) already exists. If a function contains a bug or unexpected behavior, the **absolute priority is to fix the bug in the existing function**, preserving reusability and avoiding redundant code.
- **Collaboration**: Be critical and proactive; suggest better technical paths instead of simply complying with everything. Discuss technical choices before writing code.
