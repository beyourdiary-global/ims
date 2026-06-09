# IMS — Developer Action Plan

> Generated: 2026-06-09
> This document tells each developer **exactly what to do**, in order, with clear acceptance criteria.
> Reference docs: [CODE_STANDARDS.md](CODE_STANDARDS.md) · [CODE_DUPLICATION_REPORT_EN.md](CODE_DUPLICATION_REPORT_EN.md) · [FILE_STRUCTURE.md](FILE_STRUCTURE.md) · [COMMIT_CHECKLIST.md](COMMIT_CHECKLIST.md)
> Pre-commit hook auto-checks: A1–A3 (block) · B1–B3, C1–C2, D1–D2 (warn). See `.githooks/pre-commit`.

---

## How to use this document
1. Work top to bottom — earlier items unblock later ones.
2. Each item has: **What to do → How to do it → Acceptance criteria → Commit type**.
3. One logical change per commit. Never bundle unrelated fixes.
4. After every item: run `powershell -ExecutionPolicy Bypass -File scripts\precommit_check.ps1` before committing.

---

## First-time setup (every developer, on every fresh clone)

```bash
git config core.hooksPath .githooks
```
This enables the pre-commit hook. Without this command, the hook does nothing.

---

# PART 1 — Code Standards (CODE_STANDARDS.md §1–§9)
*Fix existing code quality issues. Assign one section per developer or per sprint.*

---

## [STD-01] Fix page structure order — PHP → HTML → CSS → JS
**What**: Every page must follow this layout order: PHP block → `<head>` (CSS only) → `<body>` → `<script>` after `</body>`.
**Scope**: ~165 pages have misplaced `<script>` tags; ~62 pages have `<style>` outside `<head>`.

**How**:
1. Find pages with `<script>` outside `<body>`:
   ```bash
   grep -rln "</body>" --include=*.php . --exclude-dir=header | while read f; do
     bc=$(grep -n "</body>" "$f" | tail -1 | cut -d: -f1)
     awk -v bc="$bc" 'NR>bc && /<script/' "$f" && echo "  → $f"
   done
   ```
2. Move `<script>` blocks to after `</body>`, before `</html>`.
3. Find `<style>` blocks outside `<head>`:
   ```bash
   grep -rln "<style" --include=*.php . --exclude-dir=header | while read f; do
     hc=$(grep -n "</head>" "$f" | head -1 | cut -d: -f1)
     [ -z "$hc" ] && continue
     awk -v hc="$hc" 'NR>hc && /^<style/' "$f" && echo "  → $f"
   done
   ```
4. Move those `<style>` blocks inside `<head>`.

**Required template** (see CODE_STANDARDS.md §1):
```
<?php ... ?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
    <style>/* page-level overrides only */</style>
</head>
<body>
    <!-- page content -->
</body>
<script src="...common.fun.js"></script>
<script>/* page initialisation only */</script>
</html>
```

**Acceptance criteria**:
- [ ] No `<script>` between `<head>` and `</head>` in any custom PHP file
- [ ] No `<style>` after `</head>` in any custom PHP file
- [ ] Hook checks B1 and B2 pass with no warnings

**Commit**: `style: fix script/style placement — move scripts after body, styles into head`

---

## [STD-02] Move repeated .btn style into main.css
**What**: 62 files each contain this identical inline `<style>` block that belongs in `css/main.css`:
```css
.btn { padding: 0.2rem 0.5rem; font-size: 0.75rem; margin: 3px; }
.btn-container { white-space: nowrap; }
```

**How**:
1. Confirm the rule is not already in `css/main.css`:
   ```bash
   grep -n "0.2rem 0.5rem" css/main.css
   ```
2. If missing, add it to `css/main.css`.
3. Find all 62 files containing the duplicate block:
   ```bash
   grep -rln "padding: 0.2rem 0.5rem" --include=*.php . --exclude-dir=header
   ```
4. Remove the inline `<style>` block from each of those files.
5. Visually test a few list pages to confirm button sizes are unchanged.

**Acceptance criteria**:
- [ ] `.btn` padding rule exists exactly once, in `css/main.css`
- [ ] The inline `<style>` block has been removed from all 62 files
- [ ] No visual regression on list pages

**Commit**: `dup-remove: move .btn style from 62 inline blocks to css/main.css`

---

## [STD-03] Fix SQL injection — escape all user inputs
**What**: All `$_POST`, `$_GET`, and `$_REQUEST` values used in SQL queries must be escaped.
**Scope**: Apply to every file you touch going forward. Legacy files should be audited file by file.

**How** (for each PHP file you touch):
```php
// Before (unsafe)
$query = "SELECT * FROM tbl WHERE name='" . $_POST['name'] . "'";

// After — use the existing post() helper + escape
$name = mysqli_real_escape_string($connect, trim((string) post('name')));
$query = "SELECT * FROM tbl WHERE name='" . $name . "'";
```

For new code, prefer prepared statements:
```php
$stmt = $connect->prepare("SELECT * FROM " . TABLE . " WHERE name=?");
$stmt->bind_param('s', $name);
$stmt->execute();
```

**Acceptance criteria**:
- [ ] No new `$_POST[...]` or `$_GET[...]` directly inside SQL strings
- [ ] Hook check A2 passes with no blocks

**Commit**: `fix: escape SQL inputs in [filename] — prevent SQL injection`

---

## [STD-04] Escape all HTML output (XSS prevention)
**What**: Every `echo $row['field']` or `<?= $row['field'] ?>` that outputs to HTML must use `htmlspecialchars()`.

**How**:
```php
// Before (unsafe — XSS risk)
<td><?= $row['name'] ?></td>
echo "<td>" . $row['name'] . "</td>";

// After (safe)
<td><?= htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') ?></td>
echo "<td>" . htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') . "</td>";
```

Exceptions — safe without wrapping: `intval()`, `number_format()`, `json_encode()` results.

**Acceptance criteria**:
- [ ] Hook check B3 passes with no warnings on all modified files

**Commit**: `fix: htmlspecialchars all row output in [filename]`

---

## [STD-05] Replace `var` with `const`/`let` in new JS code
**What**: New JavaScript must use `const` (default) or `let` (if the value is reassigned). No `var`.

**How**:
```js
// Before
var total = 0;
var name = obj('brandName').value;

// After
let total = 0;                        // reassigned later
const name = obj('brandName').value;  // not reassigned
```

> **Note**: `js/common.fun.js` has 325 legacy `var` declarations. Migrate them **gradually** when editing that file — do not do a mass replacement that risks breaking existing functionality.

**Acceptance criteria**:
- [ ] No new `var` in any JS file added or modified in this commit
- [ ] Hook check C1 passes with no warnings

**Commit**: `style: replace var with const/let in [filename].js`

---

## [STD-06] Replace `alert()` with `showNotification()`
**What**: `alert()` blocks the browser UI and looks unprofessional. Use the shared helper from `common.fun.js`.

**How**:
```js
// Before
alert('Saved successfully!');
alert('Please fill in required fields.');

// After
showNotification('Saved successfully', 'success');
showNotification('Please fill in required fields', 'error');
```

> **Exception**: `alert()` inside PHP-generated `<script>` blocks used for server-side error redirects (e.g. `echo "<script>alert(...)</script>"`) is acceptable for now. Focus on client-side `.js` files first.

**Acceptance criteria**:
- [ ] No `alert()` in `.js` files for user-facing messages
- [ ] Hook check C2 passes with no warnings

**Commit**: `style: replace alert() with showNotification() in [filename].js`

---

## [STD-07] Standardise PHP variable naming to camelCase
**What**: PHP variables must use `$camelCase`. Common inconsistencies found in the codebase:

| Current (non-standard) | Standard |
|---|---|
| `$redirect_page` | `$redirectPage` |
| `$tblName` | `$tableName` |
| `$dataID` | `$dataId` |
| `$rst` | `$result` or `$queryResult` |
| `$act_1` | keep as-is (it's a constant defined in `init.php`) |

**How**: When **editing** a file, rename variables within that file. Do not do a mass rename across the entire codebase in one commit — that creates too much noise and review burden.

**Acceptance criteria**:
- [ ] New variables written in files you touch use `$camelCase`
- [ ] No mixed naming conventions within the same function

**Commit**: `style: camelCase variable names in [filename].php`

---

## [STD-08] Extract shared functions — the 3-file rule
**What**: Any function appearing in 3 or more files must be extracted to the shared library.
**Priority list** (from CODE_DUPLICATION_REPORT_EN.md):

| Priority | Function group | Move to |
|---|---|---|
| 🔴 1st | `deleteDir()` + `addDirToZip()` — **50 files** | `include/common.php` |
| 🔴 2nd | `exportData`, `showExportNotification`, `setCookie`, `captureAndExport`, `auditExport`, `getParameterByName` — **7–18 JS files** | `js/common.fun.js` |
| 🔴 3rd | PDF/airbill parsers — **3 large files** | `js/pdf_airbill_parser.js` (new file) |
| 🟠 4th | Import PDF helpers — **3 import files** | `include/import_pdf_common.php` (new file) |
| 🟠 5th | `openEstimatedReceivedDateModal` — **10 files** | `include/estimated_date_modal.php` (new file) |
| 🟠 6th | `updateCheckboxesOnOtherPages` — **16 PHP + 18 JS files** | `js/common.fun.js` |
| 🟠 7th | `generateTableRow` — **10 finance files** | `include/common.php` |

**How** (for PHP functions):
1. Copy the canonical version into `include/common.php`.
2. Wrap it with `if (!function_exists('functionName')) { ... }`.
3. Delete all local copies from the files in the list above.
4. Test affected pages on UAT.

**How** (for JS functions):
1. Confirm the version in `js/common.fun.js` is the latest, correct version (merge any differences from local copies).
2. Delete local copies from individual JS files.
3. Confirm `<script src=".../js/common.fun.js">` is loaded on every affected page.

**Acceptance criteria**:
- [ ] Function exists in the shared file only — no local copies remain
- [ ] All pages that previously used it still work correctly
- [ ] Hook checks D1 and D2 pass

**Commit**: `dup-remove: centralise [functionName] to [shared file] — removed from [N] files`

---

## [STD-09] Add `<meta charset>` and `<title>` to all pages
**What**: Every page must have proper `<head>` metadata for character encoding and page title.

**How**:
```html
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> — IMS</title>
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">
</head>
```

**Acceptance criteria**:
- [ ] All pages have `<meta charset="UTF-8">`
- [ ] All pages have a `<title>` tag using `$pageTitle`

**Commit**: `style: add charset meta and title tag to [module] pages`

---

# PART 2 — File Structure Phase 2 (safe moves)
*Low risk. Can be completed in 1–2 days. No page routing breaks.*

---

## [FS-01] Create `cron/` folder and move all cron jobs
**What**: Move 7 cron files out of the root directory into a dedicated `cron/` folder.

**Files to move**:
```
cron_customer_follow_up_due.php
cron_customer_follow_up_lost.php
cron_customer_follow_up_missed.php
cron_flow_daily_email.php
cron_flow_housekeeping.php
cron_stock_order_tracking_refresh.php
cron_system_alert_message.php
```

**How**:
1. Create the folder:
   ```bash
   mkdir cron
   ```
2. Check how each cron file includes `init.php` (some may need a path update):
   ```bash
   head -5 cron_*.php
   ```
3. Move files into `cron/` and update their `include` path from `'init.php'` to `'../init.php'` (one level deeper).
4. **On the server**: update all crontab entries from `/path/to/ims/cron_xxx.php` to `/path/to/ims/cron/cron_xxx.php`.
5. No PHP pages link to these files, so no redirect updates are needed.

**Acceptance criteria**:
- [ ] `cron/` folder exists and contains all 7 files
- [ ] Root directory has no `cron_*.php` files
- [ ] Each cron file's `include` paths updated for the new folder depth
- [ ] Server crontab updated with new paths
- [ ] Cron jobs run successfully (verify in server logs the following day)

**Commit**: `refactor: move cron jobs to cron/ folder`

---

## [FS-02] Integrate Lazada files into `finance/` module
**What**: Move Lazada-related files from root into `finance/` (a partner file `finance/lazadaOrder_request_income.php` already exists there).

**Files to move**:
```
lazada_order_req.php
lazada_order_req_table.php
lazada_order_req_income_table.php
lazada_order_req_income_table_detail.php
lazada_order_req_income_table_summary.php
lazada_order_request_info.php
lazada_cust_rcd.php
lazada_cust_rcd_table.php
```

**How**:
1. Add `$isFinance = 1;` at the top of each file (before any includes).
2. Move files to `finance/`.
3. Find all references to these files across the codebase:
   ```bash
   grep -rn "lazada_order_req\|lazada_cust_rcd\|lazada_order_request" --include=*.php . --exclude-dir=header | grep -v "^./finance/"
   ```
4. Update all occurrences: change `SITEURL . '/lazada_xxx.php'` → `SITEURL . '/finance/lazada_xxx.php'`.
5. Update `menuHeader.php` menu links if Lazada pages appear there.

**Acceptance criteria**:
- [ ] All files are in `finance/`, not root
- [ ] All internal links updated (`$redirect_page`, `$deleteRedirectPage`, menu links)
- [ ] Lazada order pages load correctly
- [ ] Lazada income/reconciliation pages load correctly

**Commit**: `refactor: move Lazada pages into finance/ module`

---

## [FS-03] Create `doc/` folder (already done ✅)
The documentation files were already organised into `doc/` in a previous session.
Verify with:
```bash
ls doc/
```
Expected files: `CODE_STANDARDS.md`, `CODE_DUPLICATION_REPORT_EN.md`, `CODE_DUPLICATION_REPORT.md`, `COMMIT_CHECKLIST.md`, `FILE_STRUCTURE.md`, `PRD.md`, `PRD_DETAILED.md`, `PRD_PAGE_CATALOG.md`, `DEVELOPER_ACTION_PLAN_EN.md`.

---

# PART 3 — File Structure Phase 3 (full modularisation)
*Requires a dedicated sprint. Safe if done carefully, one module at a time.*

---

## [FS-10] Fix `connection.php` depth detection (prerequisite for all Phase 3 moves)
**What**: Replace the `$isFinance`/`$isProcess` flag system with automatic path-based depth detection. This is the **technical prerequisite** that makes all other folder moves safe.

**Current problem** (`include/connection.php`):
```php
// Fragile — every new subfolder requires a new flag
if (isset($isFinance) && $isFinance == 1) {
    include '../init.php';
} else if (isset($isProcess) && $isProcess == 1) {
    include '../../init.php';
} else {
    include 'init.php';
}
```

**Fix** — replace the entire block above with:
```php
// Automatic — works correctly at any folder depth
$_ims_root = dirname(__DIR__);   // always resolves to the ims/ root
require_once $_ims_root . '/init.php';
```

**How**:
1. Update `include/connection.php` with the automatic path code above.
2. Remove all `$isFinance = 1` and `$isProcess = 1` declarations from every PHP file — they are no longer needed.
3. Test: open one page from each folder — root, `finance/`, `shopee/`, `task/` — all should load correctly.

**Acceptance criteria**:
- [ ] `connection.php` uses `dirname(__DIR__)` to locate `init.php`
- [ ] All existing pages (root, finance/, shopee/, task/) still load without errors
- [ ] No `$isFinance` or `$isProcess` flags remain in the codebase

**Commit**: `refactor: auto-detect root path in connection.php — remove isFinance/isProcess flags`

---

## [FS-11] Create module folders and move pages (after FS-10 is complete)
**What**: Reorganise root PHP files into logical subfolders.
**Do one module at a time, in this order** (lower-risk modules first):

| Step | Module | New folder | Approx. files |
|---|---|---|---|
| 1 | HR / payroll reference | `hr/` | `holiday`, `marital_status`, `race`, `identityType`, `em_type_status`, `employee_epf_rate`, `employer_epf_rate`, `socso_category` (~16) |
| 2 | System settings | `settings/` | `system_setting`, `theme_setting`, `token_setting`, `platform`, `currencies`, `currency_unit`, `weight_unit`, `bank`, `sql_account`, `message_shortcuts`, `goalTarget` (~22) |
| 3 | Users & permissions | `users/` | `user`, `user_group`, `pin`, `pin_group`, `user_profile`, `user_record_log`, `audit_log` (~14) |
| 4 | Customers | `customer/` | `customerInfo`, `cus_level`, `cus_repeat`, `cus_segmentation`, `website_customer_record`, `urb_cust_reg`, `label`, `tag`, `customer_follow_up_list`, `customer_daily_report` (~18) |
| 5 | Products | `product/` | `product`, `product_category`, `prod_status`, `brand`, `brand_series`, `package`, `barcode_generator` (~14) |
| 6 | Stock & Warehouse | `stock/` | `stockIn`, `stockOut`, `stock_list`, `stock_report`, `warehouse`, `warehouse_stock_in`, `purchase_order`, `update_shipment_info`, `rate_checking`, `courier` (~22) |

**How** (repeat these steps for each module):
1. `mkdir [module]`
2. Move the PHP files into the new folder.
3. Find all references to those pages across the codebase:
   ```bash
   grep -rn "SITEURL.*/[filename].php" --include=*.php --include=*.js . --exclude-dir=header
   ```
4. Update all `location.href`, `$redirect_page`, `$deleteRedirectPage`, and menu links.
5. Test each page: load the list view → open the add form → save a record → delete a record.

**Inside each moved file**, update the include paths one level deeper:
```php
// After FS-10, no flag needed — connection.php is automatic.
// Just update relative paths for shared includes:
include '../menuHeader.php';           // was: include 'menuHeader.php'
include '../checkCurrentPagePin.php';
```

**Acceptance criteria** (verify for each module):
- [ ] All pages in the new folder load correctly
- [ ] Add, Edit, Delete, and Export actions work on each page
- [ ] No broken links in the navigation menu or breadcrumbs
- [ ] Root folder PHP file count reduced by the expected number

**Commit** (one per module): `refactor: move [module] pages to [folder]/ — update all internal links`

---

## [FS-12] Target folder structure after Phase 3 is complete

```
ims/
├── customer/         (~18 PHP)
├── cron/             (7 PHP)       ← Phase 2
├── finance/          (125 PHP)     ← already exists
├── hr/               (~16 PHP)
├── product/          (~14 PHP)
├── settings/         (~22 PHP)
├── shopee/           (32 PHP)      ← already exists
├── stock/            (~22 PHP)
├── task/             (41 PHP)      ← already exists
├── users/            (~14 PHP)
├── include/          (13 PHP)      ← shared logic, stays here
├── js/               (97 JS)
├── css/              (14 CSS)
├── doc/              (documentation)
├── scripts/          (developer tooling)
├── .githooks/        (git hooks)
├── header/           (3rd-party libraries — do not edit)
│
├── index.php         ← auth landing page
├── login.php
├── logout.php
├── forgotPassword.php
├── changePassword.php
├── dashboard.php     ← entry point after login
├── init.php          ← environment config
├── menuHeader.php    ← shared navigation header
├── menu_bar.php
├── header.php
├── checkCurrentPagePin.php
├── recordDelete.php
├── searchData.php
├── insert_table.php
├── CLAUDE.md
└── README.md
```

The root directory ends up with only ~15 files: **auth pages**, **shared infrastructure**, and **entry points** — nothing else.

---

# Master Checklist

## Code Standards (assign per developer or per sprint)
- [ ] **STD-01** Fix page structure order (PHP → HTML → CSS → JS)
- [ ] **STD-02** Move `.btn` style to `css/main.css`
- [ ] **STD-03** Escape SQL inputs — apply to every file touched (ongoing)
- [ ] **STD-04** Escape HTML output with `htmlspecialchars` — apply to every file touched (ongoing)
- [ ] **STD-05** Replace `var` with `const`/`let` in JS (ongoing)
- [ ] **STD-06** Replace `alert()` with `showNotification()`
- [ ] **STD-07** Standardise PHP variable naming to `$camelCase` (ongoing)
- [ ] **STD-08** Extract shared functions (7 sub-tasks from the duplication priority list)
- [ ] **STD-09** Add `<meta charset>` and `<title>` to all pages

## Phase 2 — File structure (1–2 days, low risk)
- [ ] **FS-01** Move cron jobs to `cron/` and update server crontab
- [ ] **FS-02** Move Lazada pages into `finance/`
- [ ] **FS-03** `doc/` folder ✅ already complete

## Phase 3 — Full modularisation (dedicated sprint)
- [ ] **FS-10** Fix `connection.php` auto-depth detection ← **do this first**
- [ ] **FS-11** Move modules one at a time (hr → settings → users → customer → product → stock)
- [ ] **FS-12** Verify target structure is achieved

---

*Every task above includes an acceptance criteria checklist and a commit message template. Complete all acceptance criteria before committing. The pre-commit hook automatically checks A1–A3, B1–B3, C1–C2, and D1–D2 on every commit.*
