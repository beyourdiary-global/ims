# 代码重复 & 硬编码扫描报告 / Code Duplication & Hardcoded Values Report

> 生成日期 / Generated: 2026-06-09
> 扫描范围 / Scope: 本地全部自定义代码（`*.php`, `*.js`, `*.html`），**已排除** 第三方库目录 `header/`（bootstrap, dompdf, tinymce, fontawesome, fpdf, phpqrcode, MDB, DataTable 等）。
> 目的 / Purpose: 列出可以抽成「共享文件 / shared include / common function」的重复代码，以及到处重复的硬编码 URL / 变量，交给 developer 逐项处理。

---

## 0. 总览 / Executive Summary

| # | 问题 / Issue | 重复范围 / Spread | 严重度 / Severity | 建议归位 / Move to |
|---|---|---|---|---|
| 1 | `deleteDir()` + `addDirToZip()` 整段复制 | **50 个 PHP 文件** | 🔴 高 | `include/common.php` |
| 2 | JS `exportData` / `showExportNotification` / `setCookie` 等已存在于共享库却又被重复定义 | **18 个 JS 文件** | 🔴 高 | 删除本地副本，用 `js/common.fun.js` |
| 3 | PDF / Airbill 解析函数内联复制 | 3 个大文件（含 6,000+ 行） | 🔴 高 | `include/common.php`（已有，删内联） |
| 4 | Import 解析函数 (`extractTextFromPdfContent` 等) | 3 个 import 文件 | 🟠 中 | 新建 `include/import_pdf_common.php` |
| 5 | `openEstimatedReceivedDateModal` / `close...` 弹窗 | 10 个文件 | 🟠 中 | 新建 `include/estimated_date_modal.php` |
| 6 | `updateCheckboxesOnOtherPages` 分页勾选 | 16 PHP + 18 JS | 🟠 中 | `js/common.fun.js` |
| 7 | `generateTableRow` 财务表格行生成 | 10 个财务文件 | 🟠 中 | `include/common.php` |
| 8 | 列表页 (`*_table.php`) 顶部样板代码 | 125+ 个文件 | 🟡 中低 | 新建 `include/list_page_header.php` |
| 9 | HTML 样板：preloader / `.btn` style / 网络失败 alert | 62–165 个文件 | 🟡 中低 | 共享 CSS + partial |
| 10 | 硬编码 DB 密码 / URL / CDN / 域名 | 散落多处 | 🔴 高（安全） | `init.php` / `common_variable.php` / `.env` |

> **影响量级**：单是第 1、3 项，估计有 **5,000+ 行重复代码**。任何一处改逻辑（例如改 zip 行为、改 PDF 解析规则）都要手动改几十个文件，是目前最大的维护风险来源。

---

## 1. 🔴 `deleteDir()` + `addDirToZip()` —— 复制到 50 个文件

**问题**：导出 Excel/ZIP 用的两个工具函数，被**逐字复制到 50 个 PHP 文件**。两个函数加起来约 40 行 × 50 = 约 2,000 行纯重复。

**典型位置**：`finance/atome_trans_backup_table.php:120-160`（其余 49 个完全一样）。

**全部文件清单**：
```
finance/atome_trans_backup_table.php、atome_trans_backup_table_detail.php、atome_trans_backup_table_summary.php
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

**建议 / Fix**：
1. 把 `deleteDir()` 和 `addDirToZip()` 移到 `include/common.php`（该文件已被几乎所有页面 include）。
2. 删除上述 50 个文件里的本地定义。
3. ⚠️ 注意 PHP 不允许函数重复定义——必须**全部删除本地版本后**才能集中，否则会 Fatal error。集中后用 `function_exists()` 包一层更安全。

---

## 2. 🔴 JS 函数已存在共享库 `js/common.fun.js`，却又被重复定义

**问题**：`js/common.fun.js`（含 84 个函数，被 **101 个页面** 加载）里**已经定义**了 `setCookie`、`exportData`、`showExportNotification`，但下列 JS 文件又各自重新定义了一遍，导致共享版本被覆盖（shadow），逻辑分叉。

| 函数 | 共享库已有？ | 重复定义的文件数 |
|---|---|---|
| `exportData` | ✅ `common.fun.js:2728` | 18 |
| `showExportNotification` | ✅ `common.fun.js:2737` | 18 |
| `setCookie` | ✅ `common.fun.js:2577` | 4 |
| `captureAndExport` | ❌（应集中） | 7 |
| `auditExport` | ❌（应集中） | 7 |
| `getParameterByName` | ❌（应集中） | 9 |

**`exportData` 重复文件**：
```
atome_trans_backup_table.js, bank_trans_backup_table.js, del_fees_claim_table.js,
dw_top_up_record_table.js, fb_ads_topup_table.js, internal_consume_item_table.js,
internal_consume_ticket_credit_table.js, j&t_trans_backup_table.js, merchant_comm_table.js,
order_req.js, package_table.js, product_table.js, shopee_ads_topup_trans_table.js,
shopee_withdrawal_transactions_table.js, stock_credit_topup_record_table.js,
stripe_trans_backup_table.js, warehouse_stock_in_table.js
```

**`getParameterByName` 重复文件**：
```
atome_trans_backup_table.js, dashboard.js, fb_ads_topup_table.js, fb_order_req.js,
lazada_order_req.js, order_req.js, shopee_order_req.js, stock_out.js, website_order_request.js
```

**建议 / Fix**：
1. 把 `captureAndExport`、`auditExport`、`getParameterByName` 加进 `js/common.fun.js`。
2. 删除以上文件里的本地副本（JS 后定义会覆盖前面，先确认共享版本是「最新正确版」再删，必要时把改动合并进共享版）。
3. 确认每个用到的页面都有 `<script src=".../js/common.fun.js">`。

---

## 3. 🔴 PDF / Airbill 解析函数 —— 内联复制（已在 common.php 仍重复）

**问题**：以下一组 JS 函数（嵌在 PHP 的 `<script>` 里），在 **`include/common.php`、`shopee/shopee_order_req.php`、`shopee_order_import.php`** 三处各有一份完全相同的实现。`shopee_order_import.php` 单文件就 248 KB、`shopee_order_req.php` 116 KB，重复块很大。

**涉及函数**：
```
normalizePdfTextItem, getPdfTextItemX, getPdfTextItemY, groupPdfItemsIntoLines,
sortPdfItemsForReading, isLikelyAirbillCode, extractAirbillCodeFromPdfItems,
extractShopeeAirbillDataFromPdfItems, extractRecipientNameFromPdfItems,
extractRecipientAddressFromPdfItems, readFileAsArrayBuffer, dispatchInputEvent, bind
```

**建议 / Fix**：
- `include/common.php` 已经有这份代码。把它抽成一个独立 JS 文件，例如 `js/pdf_airbill_parser.js`，三个地方统一用 `<script src>` 引入，删掉内联副本。这样改一次 airbill 解析规则三处同步生效。

---

## 4. 🟠 Import 文件的 PDF 解析工具 —— 复制到 3 个 import 文件

**问题**：`extractTextFromPdfContent`、`getPdfTextLines`、`cleanPdfTextOperand`、`normalizeImportText`、`normalizeImportLookup`、`getImportOptionList` 在三个 import 文件里各一份：
```
facebook_ads_topup_import.php, shopee_ads_topup_import.php, shopee_order_import.php
```

**建议 / Fix**：新建 `include/import_pdf_common.php`（或合进第 3 项的 `js/pdf_airbill_parser.js`），三个 import 文件统一引入。

---

## 5. 🟠 「预计到货日期」弹窗 —— 复制到 10 个文件

**问题**：`openEstimatedReceivedDateModal()` / `closeEstimatedReceivedDateModal()` 及其 HTML modal 在 10 个文件重复：
```
finance/arrival_management.php, finance/fb_order_req_income_table_detail.php,
finance/fb_order_req_table.php, finance/website_order_request_income_table_detail.php,
finance/website_order_request_table.php, lazada_order_req_income_table_detail.php,
lazada_order_req_table.php, shopee/shopee_order_req_table.php,
shopee/shopee_processing_order.php, shopee/shopee_verify.php
```

**建议 / Fix**：把 modal 的 HTML + JS 抽到 `include/estimated_date_modal.php`，各页面 `include` 即可（这正是用户说的 "comment share / 共享" 方式）。

---

## 6. 🟠 分页全选逻辑 `updateCheckboxesOnOtherPages` —— 16 PHP + 18 JS

**问题**：DataTable 跨页保持勾选的逻辑，在 16 个 PHP 文件 + 18 个 JS 文件重复（合计 34 处）。

**PHP 文件**：
```
finance/fb_order_req_income_table.php (+_summary), finance/lazadaOrder_request_income.php,
finance/website_order_request_income_table.php (+_detail/_summary),
lazada_order_req_income_table.php (+_detail/_summary),
shopee/shopeeOrder_request_income.php, shopee/shopee_order_report_table.php,
shopee/shopee_order_req_income_table.php (+_detail/_summary),
stock_report.php, stock_report_summary.php
```

**建议 / Fix**：移到 `js/common.fun.js`，PHP 内联版本全部删除。

---

## 7. 🟠 财务表格行生成 `generateTableRow` —— 10 个文件

**文件**：
```
finance/atome_trans_backup_table.php, finance/del_fees_claim_table.php,
finance/downline_top_up_record_table.php, finance/fb_ads_topup_trans_table.php,
finance/internal_consume_item_table.php, finance/internal_consume_ticket_credit_table.php,
finance/merchant_comm_record_table.php, finance/stock_credit_top_up_request_table.php,
finance/stripe_trans_backup_table.php, shopee/shopee_withdrawal_transactions_table.php
```
**建议**：列结构相近，抽成参数化的共享函数放 `include/common.php`；列差异用配置数组传入。

**其他较小的重复函数（顺带处理）**：
- `toggleFilters` / `autoToggleSections` / `applyFilterOrGroup` → 3 个 shopee 文件
- `activatePlatformTab` / `getValidDataTableRowCount` → 3 个 finance 文件
- `showNewCustomerInlineError` / `clearNewCustomerInlineError` → 3 个 order 文件
- `togglePassword` → `changePassword.php`, `user.php`
- `setStatus` → `include/common.php` 等 4 处

---

## 8. 🟡 列表页 (`*_table.php`) 顶部样板 —— 125+ 文件

**问题**：约 125 个 `_table.php` 页面顶部几乎一模一样（见 `brand_table.php:1-40`）：
```php
$pageTitle = "...";  $currentPagePin = N;
include 'menuHeader.php';            // 266 个文件都有
include 'checkCurrentPagePin.php';
$pageTitle = getPinGroupNameById(...);
$pinAccess = checkCurrentPin(...);
$_SESSION['act']=''; $_SESSION['viewChk']=''; ...   // 137 个文件重复重置
$num = 1;
$redirect_page = $SITEURL.'/xxx.php';
$deleteRedirectPage = $SITEURL.'/xxx_table.php';
$result = getData('*','','',$tblName,$connect);
// "currently network temporary fail" alert  → 87 个文件重复
```

**建议 / Fix**：把 session 重置 + 网络失败检查 + 公共 head 抽成 `include/list_page_header.php`，每页只传 `$pageTitle / $currentPagePin / $tblName / 基名`。可消掉每页 20–30 行。

---

## 9. 🟡 HTML / CSS 样板重复

| 样板 | 重复文件数 | 建议 |
|---|---|---|
| `pre-load-center` / preloader 那段 HTML | **165** | 抽进公共 header partial |
| `.btn { padding:0.2rem 0.5rem; font-size:0.75rem }` 内联 `<style>` | **62** | 移到 `css/main.css` 一个 class |
| `createSortingTable('table')` 初始化脚本 | **129** | 放共享 JS，统一在 DataTable 初始化 |
| "currently network temporary fail" alert 块 | **87** | 抽成 helper（见第 8 项） |

---

## 10. 🔴 硬编码 URL / 变量 / 密钥（含安全风险）

### 10.1 数据库凭据明文写死（安全风险）
`init.php:66-71`：
```php
define('dbpwd', $siteOrlocalMode ? 'Byd1234@Global' : '');
define('dbhost', $siteOrlocalMode ? '127.0.0.1:3306' : 'localhost');
```
⚠️ DB 密码明文进了 git 仓库。**建议移到环境变量 / `.env`（不进版本库）**，并轮换该密码。

> 另注：`init.php:50-57` 的环境判断有逻辑疑点——`$siteOrlocalMode` 为 false（本地）时 `$siteUrl` 被设成 `http://localhost/cms` 却放在「非本地」分支，dbUser 又取 `'root'`。建议 developer 复核这段环境分支。

### 10.2 重复硬编码的 URL / CDN
| 值 | 出现次数 | 建议 |
|---|---|---|
| `https://api.telegram.org/bot` | 8 | 定义 `define('TELEGRAM_API', ...)` |
| `https://code.jquery.com/jquery-3.6.4.min.js` | 3 | 集中到公共 head partial |
| `https://cdn.jsdelivr.net/npm/chart.js` 等 | 多处 | 同上 |
| `https://api.qrserver.com/v1/create-qr-code/` | 2 | 常量化 |
| `https://ipapi.co/`, `https://icons.duckduckgo.com/ip3/`, `https://www.google.com/s2/favicons` | 各处 | 常量化 |
| easyparcel demo/live 域名 | 多处 | 已部分用常量 `EASYPARCEL_DOMAIN_*`，统一其余 |

### 10.3 域名散落多处（应集中到 `init.php` 的 `SITEURL`）
`cms.beyourdiary.com` / `uatcms.beyourdiary.com` / `uat.cms.beyourdiary` / `http://localhost/cms` / `https://cms.beyourdiary.com/blog/preview.php` 等被直接写在多个文件里。
**建议**：一律改用已有的 `SITEURL` 常量或在 `common_variable.php` 增加常量；其中 `uat.cms.beyourdiary`（缺 `.com`）疑似笔误，需核对。

### 10.4 `common_variable.php` 已有常量但未充分使用
该文件已定义 `FB_LINK`、`COMPANY_LINK`、`INSTA_LINK`、图片路径常量 `img/img_server/ATCH` 等，但代码里仍有人写死 `https://www.facebook.com/beyourdiary/` 等。
**建议**：全局替换为常量引用。

---

## 11. 建议的执行顺序 / Recommended Order

1. **先做安全项**（第 10.1）：DB 密码移出仓库 + 轮换。
2. **高收益低风险**（第 1、2 项）：`deleteDir/addDirToZip` 与 JS 重复函数集中——量大、逻辑独立、最容易验证。
3. **PDF/Import 解析**（第 3、4 项）：抽成 `js/pdf_airbill_parser.js`，回归测试 shopee 导入。
4. **UI partial 化**（第 5、8、9 项）：弹窗、列表页头、preloader、`.btn` 样式。
5. **表格逻辑**（第 6、7 项）：分页勾选 + `generateTableRow` 参数化。
6. **URL/常量收口**（第 10.2–10.4）。

### 通用注意事项
- **PHP**：函数不能重复定义——集中前必须删干净所有本地副本，建议用 `if (!function_exists('xxx'))` 包裹。
- **JS**：后加载的定义会覆盖先加载的——删本地副本前先确认 `common.fun.js` 里是最新正确版本，必要时把差异合并上去。
- 每抽一组，建议先在 UAT 跑一遍对应页面再上线。

---

*本报告由静态扫描生成，文件清单为实测结果；行号为参考定位点，重构前请以实际文件为准。*
