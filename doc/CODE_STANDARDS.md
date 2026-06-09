# IMS — Code Standards & Conventions / 代码规范

> Generated: 2026-06-09
> Based on: actual scan of existing codebase patterns, issues found in CODE_DUPLICATION_REPORT_EN.md
> Applies to: all custom `.php`, `.js`, `.css` files (not `header/` third-party libs)
> Related: [COMMIT_CHECKLIST.md](COMMIT_CHECKLIST.md) (enforced at commit time)

---

## Quick reference / 速查表

| Rule | Standard |
|---|---|
| PHP file layout | PHP block → DOCTYPE → `<head>` → `<body>` → `<script>` → `</html>` |
| CSS | In `<head>` only. Page-specific overrides in `<head><style>`. Common styles in `css/main.css` |
| JS | At end of `<body>`, before `</html>`. Never inside `<head>` |
| PHP variables | `$camelCase` |
| PHP functions | `camelCase()` |
| SQL | Escape all inputs with `mysqli_real_escape_string()`. Never raw `$_POST` in query |
| JS variables | `const` first, `let` if reassigned, never `var` |
| JS functions | `camelCase()` |
| Shared PHP | `include/common.php` |
| Shared JS | `js/common.fun.js` |
| Duplication rule | Same logic in 3+ places → must extract to shared file |

---

## 1. PHP File Layout / PHP 文件结构顺序

Every page follows this **exact order**. This is the most important rule — it determines where you put everything.

```
┌─────────────────────────────────────┐
│  1. PHP block (ALL server logic)    │  <?php ... ?>
│     - config flags ($isFinance etc) │
│     - includes                      │
│     - permission check              │
│     - data fetching                 │
│     - form POST handling (save/del) │
│     - audit log                     │
├─────────────────────────────────────┤
│  2. <!DOCTYPE html>                 │
│  3. <html>                          │
├─────────────────────────────────────┤
│  4. <head>                          │
│     - <meta> charset/viewport       │
│     - <title>                       │
│     - CSS links (main.css first)    │
│     - Page-specific <style> block   │
│     NO <script> here                │
│  </head>                            │
├─────────────────────────────────────┤
│  5. <body>                          │
│     - preloader div                 │
│     - page-load-cover div           │
│     - main HTML content             │
│     - modals / hidden elements      │
│  </body>                            │
├─────────────────────────────────────┤
│  6. <script> blocks                 │  ← AFTER </body>, before </html>
│     - third-party lib init          │
│     - shared: common.fun.js         │
│     - page-specific JS              │
│     NO <script> inside <head>       │
├─────────────────────────────────────┤
│  </html>                            │
└─────────────────────────────────────┘
```

### ❌ Current issues to fix
```php
// BAD — <script> placed after </body> and outside <html>
</body>
<script>               // ← wrong: outside <body>, logic mixed with markup
  ...
</html>

// BAD — <style> placed after <head> closes
</head>
<style>                // ← wrong: should be inside <head>
  .btn { ... }
</style>
<body>

// BAD — <script> init code inside <head>
<head>
  <script>preloader(300);</script>   // ← wrong: blocks page parse
</head>
```

### ✅ Correct template
```html
<?php
// --- 1. ALL PHP LOGIC HERE ---
$currentPagePin = 9;
$pageTitle = "Brand";
include 'menuHeader.php';
include 'checkCurrentPagePin.php';
// ... data fetch, POST handling ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> — IMS</title>

    <!-- 2. CSS: shared first, then page-specific -->
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">

    <!-- 3. Page-specific styles only (if truly page-only) -->
    <style>
        .my-local-override { color: red; }
    </style>
    <!-- NO <script> in <head> -->
</head>
<body>
    <!-- preloader -->
    <div class="pre-load-center"><div class="preloader"></div></div>
    <div class="page-load-cover">

    <!-- main content -->

    </div><!-- /page-load-cover -->
</body>

<!-- 4. ALL SCRIPTS AT THE BOTTOM -->
<script src="<?= $SITEURL ?>/js/common.fun.js"></script>
<script src="<?= $SITEURL ?>/js/brand.js"></script>
<script>
    // inline page-init only — no function definitions here
    $(document).ready(() => {
        createSortingTable('table');
    });
</script>
</html>
```

---

## 2. PHP Coding Standards / PHP 代码规范

### 2.1 Variable & function naming
```php
// Variables: $camelCase
$pageTitle = "Brand";
$redirectPage = $SITEURL . '/brand_table.php';
$selectedCompanyId = '';

// Functions: camelCase()
function getPinGroupNameById($connect, $id) { ... }
function isActionAllowed($action, $pinAccess) { ... }

// Constants: UPPER_SNAKE_CASE (already consistent in codebase)
define('SITEURL', $siteUrl);
define('BRAND', 'tbl_brand');

// ❌ Mixed styles currently in codebase — standardise these:
$redirect_page   → $redirectPage
$tblName         → $tableName
$dataID          → $dataId
$rst             → $result  (or $queryResult for clarity)
```

### 2.2 SQL — always escape inputs
```php
// ❌ BAD — raw $_POST directly in query (SQL injection risk)
$query = "SELECT * FROM tbl_user WHERE email='" . $_POST['email'] . "'";

// ✅ GOOD — escape before use
$email = mysqli_real_escape_string($connect, trim((string) post('email')));
$query = "SELECT * FROM " . USR_USER . " WHERE email='" . $email . "' AND status='A'";

// ✅ BETTER (for new code) — prepared statements
$stmt = $connect->prepare("SELECT * FROM " . USR_USER . " WHERE email=? AND status='A'");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
```

### 2.3 Output escaping (XSS prevention)
```php
// ❌ BAD — raw variable in HTML output
echo "<td>" . $row['name'] . "</td>";

// ✅ GOOD
echo "<td>" . htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') . "</td>";

// In templates, use short form:
<td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
```

### 2.4 Include order inside the PHP block
```php
<?php
// 1. Module flags (must be FIRST, before any include)
$currentPagePin = 9;
$pageTitle      = "Brand";
$isFinance      = 1;    // only for finance/ pages

// 2. Bootstrap includes
include 'menuHeader.php';       // loads init.php + common.php
include 'checkCurrentPagePin.php';

// 3. Page-specific setup
$pageTitle = getPinGroupNameById($connect, $currentPagePin);
$tableName = BRAND;
$pinAccess = checkCurrentPin($connect, $pageTitle);

// 4. Read data
$result = getData('*', '', '', $tableName, $connect);

// 5. Handle POST (save / delete)
if (post('actionBtn')) {
    switch (post('actionBtn')) {
        case 'addData': ...
        case 'updData': ...
        case 'back':    ...
    }
}
?>
```

### 2.5 Form action codes — use the defined constants
```php
// ✅ Use constants from init.php, don't hard-code strings
$act_1 = 'I';  // Insert
$act_2 = 'E';  // Edit
$act_3 = 'D';  // Delete

// Button value pattern
$actionBtnValue = ($act === 'I') ? 'addData' : 'updData';
```

### 2.6 Soft delete — always, never hard delete
```php
// ❌ BAD
"DELETE FROM $tbl WHERE id='$id'"

// ✅ GOOD — soft delete via deleteRecord()
deleteRecord($tableName, '', $dataId, $row['name'], $connect, $connect, $cdate, $ctime, $pageTitle);
// Sets status='D', writes audit log
```

### 2.7 Error handling
```php
// ❌ BAD — no error handling
$result = mysqli_query($connect, $query);
echo $result->fetch_assoc()['name'];

// ✅ GOOD
$result = getData('*', "id='$safeId'", '', BRAND, $connect);
if (!$result || !($row = $result->fetch_assoc())) {
    echo "<script>alert('Record not found.'); location.href='$redirectPage';</script>";
    exit;
}
```

---

## 3. HTML Conventions / HTML 规范

### 3.1 Always declare charset and viewport
```html
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> — IMS</title>
</head>
```

### 3.2 CSS load order
```html
<head>
    <!-- 1. Shared styles — always first -->
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/main.css">

    <!-- 2. Page-specific CSS file (if the page has one) -->
    <link rel="stylesheet" href="<?= $SITEURL ?>/css/dashboard.css">

    <!-- 3. Inline <style> only for truly local overrides that don't belong in main.css -->
    <style>
        /* keep this under 20 lines; if it grows, move to a CSS file */
    </style>
</head>
```

### 3.3 Standard page wrapper structure
```html
<body>
    <!-- Preloader (shared pattern — do not change markup) -->
    <div class="pre-load-center">
        <div class="preloader"></div>
    </div>

    <div class="page-load-cover">
        <!-- Breadcrumb -->
        <p><a href="<?= $SITEURL ?>/dashboard.php">Dashboard</a>
           <i class="fa-solid fa-chevron-right fa-xs"></i>
           <?= htmlspecialchars($pageTitle) ?></p>

        <!-- Page content -->
        <div class="container-fluid">
            ...
        </div>
    </div><!-- /page-load-cover -->
</body>
```

### 3.4 Use semantic HTML
```html
<!-- ❌ BAD -->
<div class="table-header">Brand Name</div>
<div class="table-row">...</div>

<!-- ✅ GOOD -->
<table id="brandTable">
    <thead><tr><th>Brand Name</th></tr></thead>
    <tbody><tr><td>...</td></tr></tbody>
</table>
```

---

## 4. JavaScript Conventions / JS 规范

### 4.1 Use `const` / `let`, never `var`
```js
// ❌ BAD — var has function scope, causes bugs
var total = 0;
var name = obj('brandName').value;

// ✅ GOOD
const table = document.getElementById('brandTable');
let total = 0;        // let only if value will change
const name = obj('brandName').value;
```

> **Note**: `js/common.fun.js` still uses `var` (325 occurrences) — this is legacy. **New code** written today should use `const`/`let`. Migrate `common.fun.js` gradually.

### 4.2 Function definitions — in JS files, not inline PHP
```php
// ❌ BAD — function defined inside PHP <script> block
<script>
    function calculateTotal() { ... }   // duplicated across pages
</script>

// ✅ GOOD — function lives in js/common.fun.js or js/page.js
// PHP only calls it:
<script>
    $(document).ready(() => {
        calculateTotal();
    });
</script>
```

### 4.3 DOM selectors — use the shared helpers
```js
// ✅ Use existing helpers from common.fun.js
const el     = obj('elementId');         // document.getElementById
const value  = objValue('elementId');    // .value
const toggle = toggle('elementId');      // show/hide

// ❌ Don't duplicate what the helpers already do
document.getElementById('elementId')    // use obj() instead
```

### 4.4 AJAX — use `fetch` (new code), jQuery `$.ajax` for existing pages
```js
// ✅ New code: fetch with async/await
async function saveData(payload) {
    try {
        const res = await fetch('save_endpoint.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message);
    } catch (err) {
        showNotification('Error: ' + err.message, 'error');
    }
}

// Existing pages: keep $.ajax for consistency, don't mix
```

### 4.5 Error & notification feedback — use shared helpers
```js
// ✅ Use shared notification (already in common.fun.js)
showNotification('Saved successfully', 'success');
showNotification('Please fill in required fields', 'error');

// ❌ Don't use raw alert() for user feedback
alert('Saved!');   // blocks UI, bad UX
```

### 4.6 Export functions — use shared, don't redefine
```js
// ❌ BAD — redefining what common.fun.js already has
function exportData() { ... }           // already in common.fun.js:2728
function showExportNotification() { ... } // already in common.fun.js:2737
function setCookie(name, val, mins) { ... } // already in common.fun.js:2577

// ✅ GOOD — just call them; they're already loaded via common.fun.js
exportData();
```

---

## 5. CSS Conventions / CSS 规范

### 5.1 Where to put styles
| Type | Location |
|---|---|
| Global layout, buttons, utilities | `css/main.css` |
| Module-wide styles | `css/[module].css` (e.g. `task.css`, `shopeeOrderRequest.css`) |
| Page-specific tweaks (< ~20 lines) | `<style>` in `<head>` of that page |
| Component styles in a shared partial | Inside the partial's `<style>` tag |
| ❌ Inline `style="..."` on elements | Avoid except for dynamic values |

### 5.2 The `.btn` override problem
Currently **62 files** each have this identical inline block:
```css
/* ❌ Copy-pasted into 62 files */
<style>
    .btn { padding: 0.2rem 0.5rem; font-size: 0.75rem; margin: 3px; }
    .btn-container { white-space: nowrap; }
</style>
```
**Fix**: this already belongs in `css/main.css`. Add it once, remove from all 62 files.

### 5.3 Naming — BEM-lite (Block__Element--Modifier)
```css
/* ✅ */
.customer-card { }
.customer-card__title { }
.customer-card--inactive { }

/* ❌ Avoid generic class names that collide */
.title { }
.card { }
.active { }
```

---

## 6. Shared Code Rules / 共享代码规则（最重要）

These rules prevent the duplication issues found in the report:

| Rule | Detail |
|---|---|
| **Search before writing** | Before any new function: `grep -rn "function funcName" --include=*.php --include=*.js . \| grep -v header/` |
| **PHP tools → `include/common.php`** | Utility functions used by 2+ pages go here, not inline |
| **JS tools → `js/common.fun.js`** | JS utilities used by 2+ pages go here |
| **UI partials → `include/` or shared PHP** | Modals, shared HTML blocks → `include/estimated_date_modal.php` etc |
| **3-file rule** | Same logic in **3+ files** = mandatory extract |
| **No local shadow** | Never redefine a function that exists in `common.php` or `common.fun.js` |

---

## 7. Security Checklist / 安全检查

Every page that handles user input must pass all of these:

```
[ ] All $_POST / $_GET values passed through post() or input() helpers (not used raw)
[ ] All values in SQL queries escaped with mysqli_real_escape_string() or prepared statements
[ ] All output in HTML escaped with htmlspecialchars()
[ ] File uploads: validate extension + MIME type, store outside webroot or in controlled path
[ ] No secrets (passwords, tokens, API keys) hardcoded — use constants from init.php
[ ] Permission checked via isActionAllowed() before every destructive action
[ ] Redirects use $SITEURL constant, not hardcoded domain
```

---

## 8. Commit message types / Commit 类型

```
feat:          New feature
fix:           Bug fix
refactor:      Code restructure without behaviour change
dup-remove:    Remove duplicated code (move to shared)
hardcode-fix:  Replace hardcoded value with constant
style:         CSS/HTML layout change only
chore:         Config, tooling, deps
docs:          Documentation only
```

Example:
```
dup-remove: move deleteDir/addDirToZip to include/common.php

- removed local copies from 50 finance/_table.php files
- wrapped with function_exists() guard
- tested: atome, lazada, shopee export ZIP all working on UAT
```

---

## 9. File naming conventions / 文件命名规范

| Type | Convention | Example |
|---|---|---|
| PHP page (form) | `snake_case.php` | `brand.php`, `purchase_order.php` |
| PHP page (list) | `snake_case_table.php` | `brand_table.php` |
| PHP page (import) | `snake_case_import.php` | `product_import.php` |
| PHP include/shared | `snake_case.php` | `common.php`, `customer_tag.php` |
| JS (per-page) | matches PHP name | `brand.js`, `purchase_order.js` |
| JS (shared) | `name.fun.js` or `name.js` | `common.fun.js` |
| CSS (per-module) | `module_name.css` | `task.css`, `dashboard.css` |
| CSS (global) | `main.css` | — |

---

*Standards are based on the existing working patterns in this codebase, not imposed from outside. The goal is to make all pages consistent with the best-written pages already here (e.g. `brand.php`, `include/common.php`, `js/common.fun.js`).*
*Companion: [COMMIT_CHECKLIST.md](COMMIT_CHECKLIST.md) enforces the most critical rules automatically at commit time.*
