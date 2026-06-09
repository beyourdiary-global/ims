# IMS — Product Requirements Document (PRD) / 产品需求文档

> Generated: 2026-06-09 · System: BeYourDiary CMS / IMS (Inventory + Order + Finance Management)
> Audience: Product, QA, developers, new team members.
> Companion docs: [FILE_STRUCTURE.md](FILE_STRUCTURE.md) · [CODE_DUPLICATION_REPORT_EN.md](CODE_DUPLICATION_REPORT_EN.md)
> This PRD describes **what each page does, how pages link together, and the key features** — derived from the actual codebase, not a wishlist.

---

## 1. Product Overview / 产品概述

A web-based back-office system that runs the operations of an e-commerce business across multiple sales channels (Shopee, Lazada, Facebook, company website). It covers the full chain:

**Customers → Products/Stock → Orders → Fulfilment/Shipping → Finance/Reconciliation → Reporting.**

- **Tech**: PHP (no framework), MySQL (two databases: main `cms` + `financial`), jQuery/Bootstrap front-end, server-rendered pages.
- **Environments**: auto-detected by hostname in `init.php` — `cms.beyourdiary.com` (live), `uatcms.beyourdiary.com` (UAT), localhost (dev).
- **Two databases**:
  - `beyourdi_cms` — operational data (customers, products, orders, users)
  - `beyourdi_financial` — finance/accounting data (transactions, income, commissions)

---

## 2. Users & Access Control / 用户与权限

### Authentication / 登录
| Page | Function |
|---|---|
| `login.php` | Username/password login |
| `forgotPassword.php` → `changePassword.php` | Password reset via emailed token (`?token=&email=`) |
| `logout.php` | End session |
| `include/auto_login.php` | "Remember me" — auto-login from cookie |
| `user_profile.php` | Logged-in user's own profile |

Sessions: 30-day lifetime, isolated session folder (`app_sessions/`), HttpOnly + SameSite=Lax cookies.

### Permission model — "PIN" based / 权限模型（PIN）
Access is **page + action** based, driven by **PIN groups** stored in the DB:

- Every page declares `$currentPagePin = N;` near the top.
- `checkCurrentPagePin.php` + `include/access.php` check the user's allowed PINs (`$_SESSION['usr_pin_access']`).
- If a user has no access to a page's PIN → redirected to `dashboard.php`.
- Within a page, each button is gated by an **action permission**:
  `isActionAllowed("Add" | "View" | "Edit" | "Delete" | "Export" | "Search" | "Reset", $pinAccess)`.

| Admin page | Function |
|---|---|
| `pin.php` / `pin_group.php` | Define permission units & groups |
| `user.php` / `user_group.php` | Manage users & assign groups |
| `user_record_log.php`, `audit_log.php` | Activity / audit trail |

> **Implication for PRD/QA**: a feature's visibility depends on the user's PIN group. Test each role separately.

---

## 3. Global Navigation & Standard Page Patterns / 全局导航与标准页面模式

### 3.1 Entry & shell
- **Home** = `dashboard.php` (KPIs/panels). Every page's breadcrumb starts with **Dashboard › [Page Title]**.
- **`menuHeader.php`** renders the top bar on every page: menu, profile, password, logout, and the **system-alert (notification) bell** (live, generated per user via `system_alert_common.php`).
- The menu items shown are **filtered by the user's PIN access** (permission-driven menu).

### 3.2 The standard CRUD triplet / 标准 CRUD 三件套
Almost every data entity follows the **same page pattern** (this is the core "page linking" model):

```
   X_table.php  ──(Add)──▶  X.php?act=I            (blank form → insert)
        │
        ├──(View)────────▶  X.php?act=V&id=...      (read-only)
        ├──(Edit)────────▶  X.php?act=E&id=...      (prefilled form → update)
        └──(Delete)──────▶  recordDelete.php  ──▶ back to X_table.php
```

- `act` codes: `I` = Insert/Add, `E` = Edit/Update, `D` = Delete (defined in `init.php`).
- List page sets `$redirect_page` (the form) and `$deleteRedirectPage` (back to list).
- Buttons rendered by shared helpers: `renderViewEditButton()`, `renderDeleteButton()`.
- Data fetched via `getData()`; saved via `insert_table.php`; search/sort via DataTables + `searchData.php`.
- Many list pages have **Export** (Excel/ZIP) and bulk-select across pages.

> So when this PRD lists `entity(.php/_table.php)`, read it as: **`_table.php` = list screen, `.php` = add/edit screen, linked by the pattern above.**

### 3.3 Import pattern / 批量导入
Several entities support bulk import (`X_import.php`): upload a file (Excel/PDF), the page parses it (incl. PDF text extraction for airbills/receipts), previews, then writes records.

---

## 4. Modules / 功能模块

### 4.1 Dashboard / 仪表盘
| Page | Function |
|---|---|
| `dashboard.php` | KPI panels, charts (Chart.js), quick stats. Landing page after login. |
| `customer_daily_report.php` | Daily customer/sales report |
| `customer_label_breakdown.php` | Customer label distribution |

### 4.2 Customers / 客户管理
| Page(s) | Function |
|---|---|
| `customerInfo(.php/Table.php)` | Master customer records (add/edit/list) |
| `customer_follow_up_list.php` (+ `include/customer_follow_up_common.php`) | Sales follow-up pipeline; due/missed/lost driven by crons |
| `cus_level(.php/_table.php)` | Customer tiers/levels |
| `cus_repeat(.php/_table.php)` | Repeat-purchase classification |
| `cus_segmentation(.php/_table.php)` | Segmentation rules |
| `website_customer_record(.php/_table.php)` | Customers from the website channel |
| `urb_cust_reg.php` | Customer registration intake |
| `lazada_cust_rcd(.php/_table.php)`, `shopee/shopee_cust_info(.php/_table.php)` | Channel-specific customer records |
| `tag.php`/`tagTable.php` (+ `include/customer_tag.php`) | Customer tagging |
| `label(.php/_table.php)` | Customer/record labels |

**Follow-up automation**: `cron_customer_follow_up_due.php`, `_missed.php`, `_lost.php` move customers through follow-up states automatically.

### 4.3 Products & Catalog / 产品与目录
| Page(s) | Function |
|---|---|
| `product(.php/_table.php/_import.php)` | Product master + bulk import |
| `product_category(.php/_table.php)`, `prod_status(.php/_table.php)` | Categories & status |
| `brand(.php/_table.php)`, `brand_series(.php/_table.php)` | Brands & series |
| `package(.php/_table.php/_import.php)` | Product bundles/packages |
| `barcode_generator.php` | Generate barcodes/QR (phpqrcode) |

### 4.4 Stock & Warehouse / 库存与仓库
| Page(s) | Function |
|---|---|
| `stockIn.php` / `stockOut.php` / `stockCosting.php` / `stockRecord.php` | Stock movements & costing |
| `stock_list(.php/_table.php)` | Current stock listing |
| `stock_report(.php/_detail.php/_summary.php)` | Stock reports (detail + summary views) |
| `warehouse(.php/_table.php)` | Warehouse master |
| `warehouse_stock_in(.php/_table.php/_import.php/_scan.php)` | Inbound stock — manual, import, and **barcode scan** flow |
| `purchase_order(.php/_table.php/_import.php)` | Purchase orders to suppliers |
| `update_shipment_info.php` | Update shipment/tracking |
| `courier(.php/_table.php)`, `rate_checking.php` | Couriers & shipping-rate lookup (EasyParcel) |

### 4.5 Orders / 订单（核心流程）
| Page(s) | Function |
|---|---|
| `make_order.php` | Create an order manually |
| `common_import.php`, `order_request_info_common.php` | Shared order intake/info logic |
| **Shopee** `shopee/shopee_order_req(.php/_table.php)`, `shopee_processing_order.php`, `shopee_verify.php`, `shopee_order_verification_table.php` | Shopee order request → processing → verification |
| `shopee_order_import.php` (248 KB) | Import Shopee orders (incl. PDF airbill parsing) |
| **Lazada** `lazada_order_req(.php/_table.php)`, `lazada_order_request_info.php` | Lazada orders |
| **Facebook** `finance/fb_order_req(.php/_table.php)`, `fb_cust_deals(.php/_table.php)` | FB deals & orders |
| **Website** `finance/website_order_request(.php/_table.php/_info.php)` | Website orders |
| `finance/order_process_list.php`, `arrival_management.php`, `waiting_to_pack.php` | Fulfilment work queues |

**Order lifecycle (cross-module flow)** / 订单生命周期:
```
Channel order (import or manual)
   → Order request (per channel _order_req)
   → Processing / Waiting-to-pack / Arrival mgmt
   → Verify (shopee_verify / verification tables)
   → Shipping (airbill, courier, tracking)
   → Finance income table (reconciliation, §4.6)
```

### 4.6 Finance / 财务（`finance/` 模块）
The finance module mirrors operational data into accounting/reconciliation. Standard pattern per type: `X.php` (entry) · `X_table.php` (list) · `_table_detail.php` · `_table_summary.php`.

| Group | Pages | Function |
|---|---|---|
| **Channel income** | `fb_order_req_income_table*`, `website_order_request_income_table*`, `lazada_order_req_income_table*`, `shopee/shopee_order_req_income_table*`, `shopeeOrder_request_income.php`, `lazadaOrder_request_income.php` | Reconcile order income per channel (detail + summary) |
| **Transaction backups** | `atome_trans_backup*`, `bank_trans_backup*`, `stripe_trans_backup*`, `j&t_trans_backup*` | Payment-gateway / bank transaction records & import |
| **Cash & capital** | `cash_on_hand_trans*`, `curr_bank_trans*`, `initial_capital_trans*`, `investment_trans*`, `invtr_trans*` | Cash, bank, capital ledgers |
| **Top-ups & ads** | `fb_ads_topup_trans*`, `shopee/shopee_ads_topup_trans*`, `downline_top_up_record*`, `stock_credit_top_up_request*`, `meta_ads_acc*` | Ad spend & credit top-ups |
| **Commissions & consumption** | `merchant_comm_record*`, `internal_consume_item*`, `internal_consume_ticket_credit*`, `del_fees_claim*`, `report/staff_comm_table.php` | Commissions, internal consumption, delivery-fee claims |
| **Debts/creditors** | `other_creditor_trans*`, `sundry_debt_trans*` | Creditor & sundry debt ledgers |
| **Invoices** | `cred_inv_create.php`, `cred_notes_inv*`, `debit_inv_create.php`, `debit_notes_inv*` | Credit/debit notes & invoices |
| **Accounts/master** | `agent*`, `merchant*`, `lazada_acc*`, `fb_page_acc*`, `expense_type*`, `payment_terms*`, `fin_payment_method*`, `tax*`, `chanel_social_media*` | Finance master data |
| **Withdrawals** | `shopee/shopee_withdrawal_transactions*` | Shopee payout reconciliation |
| **Stock orders / supply** | `finance/stock_order_request(.php/_import/_info/_table/_view)`, `stock_order_tracking_refresh.php` | Supplier stock ordering & tracking |
| **Reports** | `brand_report_table*`, `package_report_table*`, `payment_method_report_table*`, `sales_person_report_table*`, `flow_report.php`, `flow_setting.php` | Finance reporting & cash-flow |
| **PDF** | `generate_pdf.php`, `template.html` (+ `include/shopee_order_detail_pdf_common.php`) | PDF document generation (dompdf/fpdf) |

**Finance automation**: `cron_flow_daily_email.php` (daily finance email), `cron_flow_housekeeping.php`.

### 4.7 Shopee module / Shopee 模块（`shopee/`）
Channel-specific extension of orders + finance. Key pages: order request/processing/verification (§4.5), income & withdrawal reconciliation (§4.6), plus settings:
| Page | Function |
|---|---|
| `shopee_acc(.php/_table.php)` | Shopee account master |
| `shopee_sg_setting(.php/_table.php)` | Shopee Singapore settings |
| `shopee_service_charges_rate_setting(.php/_table.php)` | Service-charge rate config |
| `payment_method_shopee(.php/_table.php)` | Shopee payment methods |
| `shopee_finance_verified_table.php` | Finance-verified Shopee orders |

### 4.8 Task / Project board / 任务看板（`task/`）
| Page | Function |
|---|---|
| `task/board.php` (?project_id=) | Kanban-style task board |
| `task/sheets.php` | Spreadsheet view |
| `task/summary.php` | Project summary |
| `task/board_item_detail_modal.php`, `board_item_history.php` | Task detail & history |
| `task/project_settings.php`, `project_user_access.php` | Project config & member access |
| `menu_bar.php` | Task-module sub-navigation (links to the above by `project_id`) |

### 4.9 System Settings & Administration / 系统设置与管理
| Page | Function |
|---|---|
| `system_setting.php` | Global system configuration |
| `theme_setting.php` | UI theme |
| `token_setting(.php/_table.php)` | API tokens (e.g. Telegram bot token, integration keys) |
| `platform(.php/_table.php)` | Sales-platform definitions |
| `currencies(.php/_table.php)`, `currency_unit(.php/_table.php)`, `weight_unit(.php/_table.php)` | Units & currency |
| `bank(.php/_table.php)`, `sql_account(.php/_table.php)` | Bank & accounting-software accounts |
| `message_shortcuts(.php/_table.php)` | Canned-message templates |
| `goalTarget(.php/_table.php)` | Sales targets |
| `system_alert_action.php`, `system_alert_live.php` (+ `include/system_alert_common.php`) | In-app notification engine |
| **HR reference**: `holiday`, `marital_status`, `race`, `identityType`, `em_type_status`, `employee_epf_rate`, `employer_epf_rate`, `socso_category` (each `.php/_table.php`) | Payroll/HR lookup tables |

---

## 5. Integrations / 外部集成

| Integration | Where | Purpose |
|---|---|---|
| **Shopee / Lazada / Facebook / Website** | Order & finance modules | Multi-channel order intake & reconciliation |
| **EasyParcel** (MY/SG) | `include/common.php`, `rate_checking.php`, courier | Shipping rates & booking (live + demo endpoints) |
| **Telegram Bot API** | `include/common.php`, `token_setting.php`, `finance/stock_order_request_info.php` | Alerts/notifications to staff |
| **PDF / Airbill parsing** | `shopee_order_import.php`, `shopee/shopee_order_req.php`, `include/common.php` | Read airbill data from uploaded PDFs |
| **QR / Barcode** | `barcode_generator.php` (phpqrcode), `api.qrserver.com` | Labels |
| **Email** | crons, password reset | Notifications, reports (`email_cc` config) |
| **Tracking.my / EasyParcel tracking** | shipment pages | Parcel tracking |

---

## 6. Automation (Cron Jobs) / 定时任务

| Cron | Schedule intent | Action |
|---|---|---|
| `cron_customer_follow_up_due.php` | Daily | Flag follow-ups that are due |
| `cron_customer_follow_up_missed.php` | Daily | Flag missed follow-ups |
| `cron_customer_follow_up_lost.php` | Daily | Mark lost leads |
| `cron_flow_daily_email.php` | Daily | Send finance/flow summary email |
| `cron_flow_housekeeping.php` | Periodic | Finance data housekeeping |
| `cron_stock_order_tracking_refresh.php` | Periodic | Refresh supplier stock-order tracking |
| `cron_system_alert_message.php` | Periodic | Generate system alert messages |

---

## 7. Non-functional Notes / 非功能说明（给开发/QA）

- **Shared code**: business logic lives in `include/common.php` (PHP) and `js/common.fun.js` (JS). See [FILE_STRUCTURE.md](FILE_STRUCTURE.md).
- **Known tech debt**: large-scale code duplication and hardcoded values — see [CODE_DUPLICATION_REPORT_EN.md](CODE_DUPLICATION_REPORT_EN.md). New features should reuse shared functions, not copy them.
- **Two-DB transactions**: operations spanning `cms` + `financial` must keep both consistent (most income/reconciliation pages touch both).
- **Permission testing**: because UI is PIN-gated, every feature must be QA'd per role.
- **Environments**: verify on UAT (`uatcms.beyourdiary.com`) before live.

---

## 8. Page-linking quick reference / 页面跳转速查

```
login.php ─▶ dashboard.php ─▶ [menuHeader menu, filtered by PIN]
                               │
   ┌───────────────┬──────────┼───────────────┬───────────────┐
 Customers       Products    Stock          Orders          Finance
   │               │           │               │               │
 *_table.php ◀────────────── standard CRUD ──────────────▶ *.php (add/edit)
   │                                                            │
 Export(Excel/ZIP)                                       _table_detail.php
 Bulk-select / search (DataTables)                       _table_summary.php

Order flow:  channel import/make_order ─▶ *_order_req ─▶ processing/verify
             ─▶ shipping(airbill/courier) ─▶ *_income_table (finance reconcile)
```

---

*This PRD reflects the system as built (scan date 2026-06-09). Page pairings `X(.php/_table.php)` follow the standard CRUD pattern in §3.2. For exact file inventory see FILE_STRUCTURE.md.*
