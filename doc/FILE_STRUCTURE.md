# IMS Project — File Structure / 文件结构总览

> Generated: 2026-06-09
> Root: `c:\wamp64\www\ims`
> Notes: `header/` is third-party libraries (summarized, not listed file-by-file). `app_sessions/`, `temp/`, `error_log` etc. are runtime/generated and not part of source.

---

## Top-level overview / 顶层概览

| Folder | Files | Purpose / 用途 |
|---|---:|---|
| **(root)** | 161 | Main application pages (entity CRUD, imports, crons, config) |
| `include/` | 13 | **Shared PHP** — common functions, DB, config, auth |
| `js/` | 97 | **Custom JavaScript** per page + shared `common.fun.js` |
| `css/` | 14 | Stylesheets |
| `finance/` | 125 | Finance module (transactions, income tables, reports) |
| `shopee/` | 32 | Shopee integration module |
| `task/` | 41 | Task board module |
| `report/` | 1 | Reports |
| `blog/` | 1 | Blog preview |
| `ps/` | 1 | (misc) |
| `header/` | 2,144 | **Third-party libraries** (do not edit) |
| `images_server/` | 112 | Uploaded/served images (data) |
| `attachment/`, `uploads/`, `data/` | 5/4/16 | User uploads & data (runtime) |
| `app_sessions/`, `temp/` | runtime | PHP sessions / temp files (not source) |
| `scripts/` | 1 | Dev tooling (`precommit_check.ps1`) |
| `.githooks/` | 1 | Git hooks (`pre-commit`) |

---

## Architectural conventions / 架构约定

The app follows a per-entity file pattern. For most entities `X` you'll find:

| File | Role |
|---|---|
| `X.php` | Add/Edit form page |
| `X_table.php` | List/table page |
| `X_import.php` | Bulk import page (some entities) |
| `js/X.js` / `js/X_table.js` | Page-specific JavaScript |

Bootstrap chain: every page → `init.php` (config/DB/constants) → `menuHeader.php` → `include/common.php` (shared functions) → `js/common.fun.js` (shared JS).

---

## Root — config & infrastructure / 根目录 — 配置与基础设施

```
init.php                  # Entry config: env detection, DB, SITEURL, constants, session
index.php                 # Landing / router
login.php  logout.php     # Auth
forgotPassword.php  changePassword.php  reset.php
checkCurrentPagePin.php   # Per-page PIN access control
menuHeader.php            # Page <head> + menu bootstrap (included by ~266 pages)
menu_bar.php  header.php  # Navigation
insert_table.php          # (202 KB) Central insert/update DB logic
recordDelete.php  searchData.php  getSearch.php  getSearch2.php  export.php
redirect.php  shorten.php  error_log.php  test.php
.htaccess  .cpanel.yml  .ftpquota  README.md
```

### Docs & tooling added during review / 本次审查新增
```
CODE_DUPLICATION_REPORT.md       # Duplication report (Chinese)
CODE_DUPLICATION_REPORT_EN.md    # Duplication report (English)
COMMIT_CHECKLIST.md              # Pre-commit checklist & rules
CLAUDE.md                        # Project work rules for Claude Code / devs
scripts/precommit_check.ps1      # Manual duplication/hardcode check (PowerShell)
.githooks/pre-commit             # Git pre-commit guard (bash)
```

### Cron jobs / 定时任务
```
cron_customer_follow_up_due.php
cron_customer_follow_up_lost.php
cron_customer_follow_up_missed.php
cron_flow_daily_email.php
cron_flow_housekeeping.php
cron_stock_order_tracking_refresh.php
cron_system_alert_message.php
```

---

## Root — business modules / 根目录 — 业务模块

### Dashboard & customers / 仪表盘与客户
```
dashboard.php  dashboard-old.php
customerInfo.php  customerInfoTable.php
customer_daily_report.php  customer_follow_up_list.php  customer_label_breakdown.php
cus_level(.php/_table.php)  cus_repeat(.php/_table.php)  cus_segmentation(.php/_table.php)
website_customer_record(.php/_table.php)
urb_cust_reg.php
lazada_cust_rcd(.php/_table.php)
```

### Products / 产品
```
product(.php/_table.php/_import.php)  product_category(.php/_table.php)
prod_status(.php/_table.php)
brand(.php/_table.php)  brand_series(.php/_table.php)
package(.php/_table.php/_import.php)
label(.php/_table.php)  tag.php  tagTable.php
barcode_generator.php
```

### Stock & warehouse / 库存与仓库
```
stockIn.php  stockOut.php  stockCosting.php  stockRecord.php
stock_list(.php/_table.php)
stock_report(.php/_detail.php/_summary.php)
warehouse(.php/_table.php)
warehouse_stock_in(.php/_table.php/_import.php/_scan.php)
purchase_order(.php/_table.php/_import.php)
update_shipment_info.php  rate_checking.php  courier(.php/_table.php)
```

### Orders / 订单
```
make_order.php  order_request_info_common.php  common_import.php
lazada_order_req(.php/_table.php)  lazada_order_request_info.php
lazada_order_req_income_table(.php/_detail.php/_summary.php)
fb_cust_deals(.php/_table.php)
shopee_order_import.php  shopee_ads_topup_import.php  facebook_ads_topup_import.php
```

### Company / HR / payroll reference / 公司·人事·薪资参照
```
company(.php/_table.php/_import.php)
user(.php/_table.php)  user_group(.php/_table.php)  user_profile.php  user_record_log.php
pin(.php/_table.php)  pin_group(.php/_table.php)
holiday(.php/_table.php)  marital_status(.php/_table.php)  race(.php/_table.php)
identityType.php  identityTypeTable.php  em_type_status(.php/_table.php)
employee_epf_rate(.php/_table.php)  employer_epf_rate(.php/_table.php)
socso_category(.php/_table.php)  goalTarget(.php/_table.php)
```

### Settings & system / 设置与系统
```
system_setting.php  theme_setting.php  token_setting(.php/_table.php)
platform(.php/_table.php)  currencies(.php/_table.php)
currency_unit(.php/_table.php)  weight_unit(.php/_table.php)
bank(.php/_table.php)  sql_account(.php/_table.php)
message_shortcuts(.php/_table.php)
audit_log.php  system_alert_action.php  system_alert_live.php
```

---

## `include/` — Shared PHP / 共享 PHP（重点）

```
common.php                       # (457 KB) ⭐ Main shared function library
common_variable.php              # Global constants (links, image paths)
common_getVariable.php           # (placeholder)
connection.php                   # DB connection + auth gate
access.php                       # Access-control helper
auto_login.php                   # Cookie-based auto login
customer_follow_up_common.php    # (191 KB) Follow-up shared logic
customer_tag.php                 # (67 KB) Customer tagging
system_alert_common.php          # (43 KB) Alerts
user_record_log.php              # (48 KB) Activity logging
shopee_order_detail_pdf_common.php  # (53 KB) Shopee PDF
shopee_order_verify_modal_ui.php    # (37 KB) Shopee verify modal
dashboardPanel.php
```

> ⭐ `common.php` + `js/common.fun.js` are the canonical homes for shared code. See [CODE_DUPLICATION_REPORT_EN.md](CODE_DUPLICATION_REPORT_EN.md).

---

## `js/` — Custom JavaScript / 自定义 JS

```
common.fun.js          # ⭐ Shared utility library (84 functions, loaded by ~101 pages)
text_editor.js
# --- per-page scripts (paired with PHP pages) ---
dashboard.js  make_order.js  order_req.js
product_*.js  package_*.js  label*.js  purchase_order*.js
warehouse_stock_in*.js  stock_*.js  update_shipment.js
company_*.js  user_record_log.js  message_shortcuts.js
cus_*.js  website_*.js  urb_cust_reg.js  lazada_*.js  fb_*.js  shopee_*.js
# --- finance scripts ---
atome_trans_backup*.js  bank_trans_backup*.js  cash_on_hand_trans.js
curr_bank_trans.js  cred_inv.js  debit_inv.js  inv_trans.js  invtr_trans.js
init_cap_trans.js  internal_consume_*.js  merchant*.js  stripe_trans_backup*.js
del_fees_claim*.js  dw_top_up_record*.js  expense_type.js  fin_payment_*.js
j&t_trans_backup*.js  meta_ads_acc.js  othr_cred_trans.js  sundry_debt_trans.js
stock_credit_*.js  tax.js  agent.js  chanel_social_media.js
# --- task module ---
task_board.js  task_board_core.js  task_board_ui.js  sheets.js  summary.js
project_settings.js  project_user_access.js
```
*(97 files total — full list available via `ls js/`)*

---

## `css/` — Stylesheets

```
main.css ⭐          barcode_generator.css   changePassword.css
dashboard.css        login.css               package.css
pin.css              project_settings.css    project_user_access.css
sheets.css           shopeeOrderRequest.css  sidebar_menu.css
summary.css          task.css
```

---

## `finance/` — Finance module (125 files)

Per-account-type pattern: `X.php` (form) · `X_table.php` (list) · `X_table_detail.php` · `X_table_summary.php`.

### Transaction backups & records
```
atome_trans_backup(.php + _table/_table_detail/_table_summary)
bank_trans_backup(.php/_table.php)
stripe_trans_backup(.php + _table/_detail/_summary)
j&t_trans_backup(.php/_import.php/_table.php)
cash_on_hand_trans(.php/_table.php)   curr_bank_trans(.php/_table.php)
initial_capital_trans(.php/_table.php)  investment_trans(.php/_table.php)
invtr_trans(.php/_table.php)  other_creditor_trans(.php/_table.php)
sundry_debt_trans(.php/_table.php)
```

### Income / order request tables
```
fb_order_req(.php/_table.php)  fb_order_request_info.php
fb_order_req_income_table(.php/_detail/_summary)
website_order_request(.php/_table.php)  website_order_request_info.php
website_order_request_income_table(.php/_detail/_summary)
lazadaOrder_request_income.php
order_process_list.php  arrival_management.php  waiting_to_pack.php
```

### Top-ups, commissions, credits, consumption
```
fb_ads_topup_trans(.php + _table/_detail/_summary)
downline_top_up_record(.php + _table/_detail/_summary)
stock_credit_top_up_request(.php + _table/_detail/_summary)
merchant_comm_record(.php + _table/_detail/_summary)
internal_consume_item(.php + _table/_detail/_summary)
internal_consume_ticket_credit(.php + _table/_detail/_summary)
del_fees_claim(.php + _table/_detail/_summary)
```

### Invoices, accounts, settings, reports
```
cred_inv_create.php  cred_notes_inv(.php/_table.php)
debit_inv_create.php  debit_notes_inv(.php/_table.php)
agent(.php/_table.php)  merchant(.php/_table.php)
lazada_acc(.php/_table.php)  fb_page_acc(.php/_table.php)  meta_ads_acc(.php/_table.php)
expense_type(.php/_table.php)  payment_terms(.php/_table.php)
fin_payment_method(.php/_table.php)  tax(.php/_table.php)
chanel_social_media(.php/_table.php)
stock_order_request(.php/_import.php/_info.php/_table.php/_view.php)
stock_order_tracking_refresh.php
flow_report.php  flow_setting.php
brand_report_table(.php/_summary)  package_report_table(.php/_summary)
payment_method_report_table(.php/_summary)  sales_person_report_table(.php/_summary)
generate_pdf.php  template.html
header/  submodel/  error_log
```

---

## `shopee/` — Shopee module (32 files)

```
index.php
shopee_order_req(.php/_table.php)  shopee_order_request_info.php  shopee_order_request_common.php
shopee_order_req_income_table(.php/_detail/_summary)
shopee_order_report_table.php  shopee_order_verification_table.php
shopee_processing_order.php  shopee_verify.php  shopee_finance_verified_table.php
shopeeOrder_request_income.php
shopee_ads_topup_trans(.php + _table/_detail/_summary)
shopee_withdrawal_transactions(.php + _table/_detail/_summary)
shopee_acc(.php/_table.php)  shopee_cust_info(.php/_table.php)
shopee_sg_setting(.php/_table.php)
shopee_service_charges_rate_setting(.php/_table.php)
payment_method_shopee(.php/_table.php)
```

---

## `task/` — Task board module (41 files)

```
board.php  board_item_detail_modal.php  board_item_history.php
common_task.php  sheets.php  summary.php
project_settings.php  project_user_access.php
svg_icon/   (icon assets)
```

---

## Other module folders

```
report/staff_comm_table.php
blog/preview.php
ps/index.php
```

---

## `header/` — Third-party libraries (2,144 files — DO NOT EDIT)

| Library | Files | Use |
|---|---:|---|
| `js/` | 983 | jQuery / DataTables / Chart.js / pdf.js & other vendor JS |
| `phpqrcode/` | 425 | QR code generation |
| `dompdf/` | 329 | HTML → PDF |
| `tinymce/` | 137 | Rich text editor |
| `fpdf/` | 115 | PDF generation |
| `fontawesome-free-6.0.0-web/` | 81 | Icons |
| `bootstrap-5.0.2-dist/` | 44 | CSS framework |
| `MaterialDesign-Webfont-master/` | 21 | Icon font |
| `DataTable/` | 4 | DataTables assets |
| `MDB/`, `font/`, `PhpXlsxGenerator/` | 5 | MDBootstrap / fonts / XLSX export |

---

## Runtime / generated (not source) / 运行时·生成（非源码）

```
app_sessions/     # PHP session files
temp/             # Temp files (72)
images_server/    # Served images (112)
uploads/  attachment/  data/   # User uploads & data
.git/             # Version control
```

---

*Pairings like `X(.php/_table.php)` mean both `X.php` and `X_table.php` exist. Counts are actual scan results as of 2026-06-09.*
