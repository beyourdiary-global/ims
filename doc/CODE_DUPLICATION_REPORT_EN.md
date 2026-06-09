## 0. Executive Summary

| #   | Issue                                                                                                      | Spread                       | Severity           | Move to                                             |
| --- | ---------------------------------------------------------------------------------------------------------- | ---------------------------- | ------------------ | --------------------------------------------------- |
| 1   | `deleteDir()` + `addDirToZip()` copied verbatim                                                            | **50 PHP files**             | 🔴 High            | `include/common.php`                                |
| 2   | JS `exportData` / `showExportNotification` / `setCookie` already exist in the shared lib but are redefined | **18 JS files**              | 🔴 High            | Delete local copies, use `js/common.fun.js`         |
| 3   | PDF / Airbill parsing functions inlined                                                                    | 3 large files (6,000+ lines) | 🔴 High            | `include/common.php` (already there, remove inline) |
| 4   | Import parsing functions (`extractTextFromPdfContent`, etc.)                                               | 3 import files               | 🟠 Medium          | New `include/import_pdf_common.php`                 |
| 5   | `openEstimatedReceivedDateModal` / `close...` modal                                                        | 10 files                     | 🟠 Medium          | New `include/estimated_date_modal.php`              |
| 6   | `updateCheckboxesOnOtherPages` pagination checkbox logic                                                   | 16 PHP + 18 JS               | 🟠 Medium          | `js/common.fun.js`                                  |
| 7   | `generateTableRow` finance table row builder                                                               | 10 finance files             | 🟠 Medium          | `include/common.php`                                |
| 8   | List page (`*_table.php`) top boilerplate                                                                  | 125+ files                   | 🟡 Med-Low         | New `include/list_page_header.php`                  |
| 9   | HTML boilerplate: preloader / `.btn` style / network-fail alert                                            | 62–165 files                 | 🟡 Med-Low         | Shared CSS + partial                                |
| 10  | Hardcoded DB password / URLs / CDN / domains                                                               | Scattered                    | 🔴 High (security) | `init.php` / `common_variable.php` / `.env`         |

> **Scale of impact**: Items 1 and 3 alone account for an estimated **5,000+ lines of duplicated code**. Any logic change in one place (e.g. changing zip behavior or PDF parsing rules) requires editing dozens of files by hand — this is currently the single biggest maintenance risk.

---

## 1. 🔴 `deleteDir()` + `addDirToZip()` — copied into 50 files

**Issue**: The two utility functions used for Excel/ZIP export are **copied verbatim into 50 PHP files**. The two functions together are ~40 lines × 50 = roughly 2,000 lines of pure duplication.

**Typical location**: `finance/atome_trans_backup_table.php:120-160` (the other 49 are identical).

**Full file list**:

```
finance/atome_trans_backup_table.php, atome_trans_backup_table_detail.php, atome_trans_backup_table_summary.php
finance/bank_trans_backup_table.php
finance/del_fees_claim_table.php (+_detail/_summary)
finance/downline_top_up_record_table.php (+_detail/_summary)
finance/fb_ads_topup_trans_table.php (+_detail/_summary)
finance/fb_order_req_income_table.php (+_detail/_summary)
finance/internal_consume_item_table.php (+_detail/_summary)
finance/internal_consume_ticket_credit_table.php (+_detail/_summary)
finance/j&t_trans_backup_table.php
finance/lazadaOrder_request_income.php
finance/merchant_comm_record_table.php (+_detail/_summary)
finance/stock_credit_top_up_request_table.php (+_detail/_summary)
finance/stripe_trans_backup_table.php (+_detail/_summary)
finance/website_order_request_income_table.php (+_detail/_summary)
lazada_order_req_income_table.php (+_detail/_summary)
shopee/shopeeOrder_request_income.php
shopee/shopee_ads_topup_trans_table.php (+_detail/_summary)
shopee/shopee_order_report_table.php
shopee/shopee_order_req_income_table.php (+_detail/_summary)
shopee/shopee_withdrawal_transactions_table.php (+_detail/_summary)
```

**Fix**:

1. Move `deleteDir()` and `addDirToZip()` into `include/common.php` (already included by almost every page).
2. Delete the local definitions from the 50 files above.
3. ⚠️ Note: PHP does not allow duplicate function definitions — you **must remove all local versions first** before centralizing, otherwise you get a Fatal error. Wrapping with `function_exists()` after centralizing is safer.

---

## 2. 🔴 JS functions already exist in shared lib `js/common.fun.js` but are redefined

**Issue**: `js/common.fun.js` (84 functions, loaded by **101 pages**) **already defines** `setCookie`, `exportData`, and `showExportNotification`, yet the files below each redefine them, shadowing the shared version and forking the logic.

| Function                 | Already in shared lib?  | # of files redefining it |
| ------------------------ | ----------------------- | ------------------------ |
| `exportData`             | ✅ `common.fun.js:2728` | 18                       |
| `showExportNotification` | ✅ `common.fun.js:2737` | 18                       |
| `setCookie`              | ✅ `common.fun.js:2577` | 4                        |
| `captureAndExport`       | ❌ (should centralize)  | 7                        |
| `auditExport`            | ❌ (should centralize)  | 7                        |
| `getParameterByName`     | ❌ (should centralize)  | 9                        |

**Files redefining `exportData`**:

```
atome_trans_backup_table.js, bank_trans_backup_table.js, del_fees_claim_table.js,
dw_top_up_record_table.js, fb_ads_topup_table.js, internal_consume_item_table.js,
internal_consume_ticket_credit_table.js, j&t_trans_backup_table.js, merchant_comm_table.js,
order_req.js, package_table.js, product_table.js, shopee_ads_topup_trans_table.js,
shopee_withdrawal_transactions_table.js, stock_credit_topup_record_table.js,
stripe_trans_backup_table.js, warehouse_stock_in_table.js
```

**Files redefining `getParameterByName`**:

```
atome_trans_backup_table.js, dashboard.js, fb_ads_topup_table.js, fb_order_req.js,
lazada_order_req.js, order_req.js, shopee_order_req.js, stock_out.js, website_order_request.js
```

**Fix**:

1. Add `captureAndExport`, `auditExport`, `getParameterByName` to `js/common.fun.js`.
2. Delete the local copies in the files above (in JS, a later definition overrides an earlier one — confirm the shared version is the "latest correct version" before deleting, and merge any changes into the shared version if needed).
3. Make sure every page that uses them includes `<script src=".../js/common.fun.js">`.

---

## 3. 🔴 PDF / Airbill parsing functions — inlined copies (still duplicated despite being in common.php)

**Issue**: The following group of JS functions (embedded in PHP `<script>` blocks) have an identical copy in each of **`include/common.php`, `shopee/shopee_order_req.php`, and `shopee_order_import.php`**. `shopee_order_import.php` alone is 248 KB and `shopee_order_req.php` is 116 KB — the duplicated block is large.

**Functions involved**:

```
normalizePdfTextItem, getPdfTextItemX, getPdfTextItemY, groupPdfItemsIntoLines,
sortPdfItemsForReading, isLikelyAirbillCode, extractAirbillCodeFromPdfItems,
extractShopeeAirbillDataFromPdfItems, extractRecipientNameFromPdfItems,
extractRecipientAddressFromPdfItems, readFileAsArrayBuffer, dispatchInputEvent, bind
```

**Fix**:

- `include/common.php` already has this code. Extract it into a standalone JS file, e.g. `js/pdf_airbill_parser.js`, include it via `<script src>` in all three places, and delete the inline copies. Then one change to airbill parsing applies to all three.

---

## 4. 🟠 Import PDF parsing utilities — copied into 3 import files

**Issue**: `extractTextFromPdfContent`, `getPdfTextLines`, `cleanPdfTextOperand`, `normalizeImportText`, `normalizeImportLookup`, `getImportOptionList` each have a copy in three import files:

```
facebook_ads_topup_import.php, shopee_ads_topup_import.php, shopee_order_import.php
```

**Fix**: Create `include/import_pdf_common.php` (or merge into the `js/pdf_airbill_parser.js` from item 3) and include it in all three import files.

---

## 5. 🟠 "Estimated received date" modal — copied into 10 files

**Issue**: `openEstimatedReceivedDateModal()` / `closeEstimatedReceivedDateModal()` and the modal HTML are duplicated across 10 files:

```
finance/arrival_management.php, finance/fb_order_req_income_table_detail.php,
finance/fb_order_req_table.php, finance/website_order_request_income_table_detail.php,
finance/website_order_request_table.php, lazada_order_req_income_table_detail.php,
lazada_order_req_table.php, shopee/shopee_order_req_table.php,
shopee/shopee_processing_order.php, shopee/shopee_verify.php
```

**Fix**: Extract the modal HTML + JS into `include/estimated_date_modal.php` and `include` it on each page (this is exactly the "comment share / shared include" approach the user asked for).

---

## 6. 🟠 Pagination select-all `updateCheckboxesOnOtherPages` — 16 PHP + 18 JS

**Issue**: The logic that keeps DataTable checkboxes selected across pages is duplicated across 16 PHP files + 18 JS files (34 total).

**PHP files**:

```
finance/fb_order_req_income_table.php (+_summary), finance/lazadaOrder_request_income.php,
finance/website_order_request_income_table.php (+_detail/_summary),
lazada_order_req_income_table.php (+_detail/_summary),
shopee/shopeeOrder_request_income.php, shopee/shopee_order_report_table.php,
shopee/shopee_order_req_income_table.php (+_detail/_summary),
stock_report.php, stock_report_summary.php
```

**Fix**: Move to `js/common.fun.js` and delete all inline PHP versions.

---

## 7. 🟠 Finance table row builder `generateTableRow` — 10 files

**Files**:

```
finance/atome_trans_backup_table.php, finance/del_fees_claim_table.php,
finance/downline_top_up_record_table.php, finance/fb_ads_topup_trans_table.php,
finance/internal_consume_item_table.php, finance/internal_consume_ticket_credit_table.php,
finance/merchant_comm_record_table.php, finance/stock_credit_top_up_request_table.php,
finance/stripe_trans_backup_table.php, shopee/shopee_withdrawal_transactions_table.php
```

**Fix**: The column structures are similar — extract into a parameterized shared function in `include/common.php`, passing column differences via a config array.

**Other smaller duplicate functions (handle alongside)**:

- `toggleFilters` / `autoToggleSections` / `applyFilterOrGroup` → 3 shopee files
- `activatePlatformTab` / `getValidDataTableRowCount` → 3 finance files
- `showNewCustomerInlineError` / `clearNewCustomerInlineError` → 3 order files
- `togglePassword` → `changePassword.php`, `user.php`
- `setStatus` → `include/common.php` and 3 other places

---

## 8. 🟡 List page (`*_table.php`) top boilerplate — 125+ files

**Issue**: About 125 `_table.php` pages have nearly identical top sections (see `brand_table.php:1-40`):

```php
$pageTitle = "...";  $currentPagePin = N;
include 'menuHeader.php';            // present in 266 files
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById(...);
$pinAccess = checkCurrentPin(...);
$_SESSION['act']=''; $_SESSION['viewChk']=''; ...   // session reset repeated in 137 files
$num = 1;
$redirect_page = $SITEURL.'/xxx.php';
$deleteRedirectPage = $SITEURL.'/xxx_table.php';
$result = getData('*','','',$tblName,$connect);
// "currently network temporary fail" alert  → repeated in 87 files
```

**Fix**: Extract the session reset + network-fail check + common head into `include/list_page_header.php`, with each page passing only `$pageTitle / $currentPagePin / $tblName / base name`. This removes ~20–30 lines per page.

---

## 9. 🟡 HTML / CSS boilerplate duplication

| Boilerplate                                                          | # of files | Fix                                        |
| -------------------------------------------------------------------- | ---------- | ------------------------------------------ |
| `pre-load-center` / preloader HTML block                             | **165**    | Move into a shared header partial          |
| `.btn { padding:0.2rem 0.5rem; font-size:0.75rem }` inline `<style>` | **62**     | Move to a class in `css/main.css`          |
| `createSortingTable('table')` init script                            | **129**    | Put in shared JS, init DataTable centrally |
| "currently network temporary fail" alert block                       | **87**     | Extract into a helper (see item 8)         |

---

## 10. 🔴 Hardcoded URLs / variables / secrets (incl. security risks)

### 10.1 Plaintext DB credentials in code (security risk)

`init.php:66-71`:

```php
define('dbpwd', $siteOrlocalMode ? 'Byd1234@Global' : '');
define('dbhost', $siteOrlocalMode ? '127.0.0.1:3306' : 'localhost');
```

⚠️ The DB password is committed in plaintext to the git repo. **Move it to an environment variable / `.env` (excluded from version control)** and rotate the password.

> Side note: The environment detection in `init.php:50-57` looks suspect — when `$siteOrlocalMode` is false (local), `$siteUrl` is set to `http://localhost/cms` but placed in the "not local" branch, and `$dbUser` is set to `'root'`. Developers should review this environment branching.

### 10.2 Repeated hardcoded URLs / CDNs

| Value                                                                                          | Occurrences | Fix                                                            |
| ---------------------------------------------------------------------------------------------- | ----------- | -------------------------------------------------------------- |
| `https://api.telegram.org/bot`                                                                 | 8           | Define `define('TELEGRAM_API', ...)`                           |
| `https://code.jquery.com/jquery-3.6.4.min.js`                                                  | 3           | Centralize in a shared head partial                            |
| `https://cdn.jsdelivr.net/npm/chart.js`, etc.                                                  | Multiple    | Same as above                                                  |
| `https://api.qrserver.com/v1/create-qr-code/`                                                  | 2           | Make it a constant                                             |
| `https://ipapi.co/`, `https://icons.duckduckgo.com/ip3/`, `https://www.google.com/s2/favicons` | Scattered   | Make them constants                                            |
| easyparcel demo/live domains                                                                   | Multiple    | Partially uses constants `EASYPARCEL_DOMAIN_*`; unify the rest |

### 10.3 Domains scattered across files (should centralize to `SITEURL` in `init.php`)

`cms.beyourdiary.com` / `uatcms.beyourdiary.com` / `uat.cms.beyourdiary` / `http://localhost/cms` / `https://cms.beyourdiary.com/blog/preview.php`, etc. are written directly into multiple files.
**Fix**: Always use the existing `SITEURL` constant, or add constants in `common_variable.php`. Note `uat.cms.beyourdiary` (missing `.com`) looks like a typo and should be verified.

### 10.4 `common_variable.php` has constants that are underused

This file already defines `FB_LINK`, `COMPANY_LINK`, `INSTA_LINK`, image path constants `img/img_server/ATCH`, etc., but code still hardcodes `https://www.facebook.com/beyourdiary/` and similar.
**Fix**: Globally replace with the constant references.

---

## 11. Recommended Order of Work

1. **Security first** (item 10.1): Move the DB password out of the repo + rotate it.
2. **High value, low risk** (items 1 & 2): Centralize `deleteDir/addDirToZip` and the duplicate JS functions — large volume, self-contained logic, easiest to verify.
3. **PDF/Import parsing** (items 3 & 4): Extract into `js/pdf_airbill_parser.js`, regression-test shopee import.
4. **UI partialization** (items 5, 8, 9): Modal, list-page header, preloader, `.btn` styles.
5. **Table logic** (items 6 & 7): Pagination checkboxes + parameterized `generateTableRow`.
6. **URL/constant consolidation** (items 10.2–10.4).

### General notes

- **PHP**: Functions cannot be redefined — remove all local copies before centralizing; wrapping with `if (!function_exists('xxx'))` is recommended.
- **JS**: A later-loaded definition overrides an earlier one — before deleting a local copy, confirm `common.fun.js` has the latest correct version, and merge differences up if needed.
- For each group extracted, test the affected pages on UAT before going live.

---

_This report was generated by static scanning; file lists are actual scan results, and line numbers are reference anchors — verify against the actual files before refactoring._
