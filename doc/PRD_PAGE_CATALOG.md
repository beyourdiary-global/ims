# IMS — Page-by-Page Process Catalog / 逐页流程清单

> Generated: 2026-06-09 · Part 3 of the PRD set: [PRD.md](PRD.md) (overview) · [PRD_DETAILED.md](PRD_DETAILED.md) (patterns + complex flows + Mermaid) · **this file** = every page listed with its process.
> **Process shorthand** (defined in PRD_DETAILED.md): `→B1` = standard List-page process · `→B2` = standard Add/Edit form process · `→B3` = soft-delete · `→B4` = Excel/ZIP export · `→B5` = import. Entries below add the **entity-specific** detail on top of those patterns.
> Conventions: soft delete (`status='D'`), audit on every write, PIN-gated buttons, form POSTs to itself with `actionBtn`. Two DBs: `$connect` (cms), `$finance_connect` (financial).

---

## Legend / 图例
| Tag | Meaning |
|---|---|
| **CRUD-L** | List page (`_table.php`) — follows →B1 |
| **CRUD-F** | Add/Edit form (`.php`) — follows →B2 |
| **IMPORT** | Bulk import — follows →B5 |
| **SPECIAL** | Custom process (detailed below) |
| **REPORT** | Read-only reporting |
| **CRON** | Scheduled job |
| **INCLUDE** | Shared logic, not a standalone page |

---

# 1. Auth & Account / 登录与账号

| Page | Tag | Process |
|---|---|---|
| `index.php` | SPECIAL | Login form + landing. Shows error by `?err=1..4` (1 invalid user, 2 wrong pw, 3 locked, 4 no PIN). |
| `login.php` | SPECIAL | Auth handler. 1) md5 password 2) find active user 3) lock check (fail_count=4) 4) verify pw (else fail_count++) 5) build session + PIN access map 6) remember-me cookie 7) audit 8) warm ~24 JSON caches 9) →`dashboard.php`. *(full flow in PRD_DETAILED §A1)* |
| `logout.php` | SPECIAL | Clear session + auto-login cookie → `index.php`. |
| `forgotPassword.php` | SPECIAL | Enter email → generate reset token → email link. |
| `changePassword.php` | SPECIAL | Two modes: (a) token reset via `?token=&email=` (no login needed); (b) logged-in change. Validates token/old pw → update `password_alt` (md5) → audit. |
| `reset.php` | SPECIAL | Password reset helper. |
| `user_profile.php` | SPECIAL | View/edit own profile (name, contact, password entry point). |
| `include/auto_login.php` | INCLUDE | `cmsTryAutoLoginFromCookie()` / `cmsSetAutoLoginCookieForUserRow()` — restore session from cookie. |

---

# 2. Dashboard & Daily Reports / 仪表盘

| Page | Tag | Process |
|---|---|---|
| `dashboard.php` | SPECIAL/REPORT | Landing (PIN 7, always allowed). Query KPIs across cms+financial → render panels & Chart.js charts. |
| `dashboard-old.php` | REPORT | Legacy dashboard (kept for reference). |
| `customer_daily_report.php` | REPORT | Per-platform daily customer/sales report; `setActivePlatform()` switches channel tab; date filters. |
| `customer_label_breakdown.php` | REPORT | Customer label distribution chart/table. |
| `include/dashboardPanel.php` | INCLUDE | Dashboard panel rendering helper. |

---

# 3. Customers / 客户

| Page | Tag | Process |
|---|---|---|
| `customerInfo.php` / `customerInfoTable.php` | CRUD-F / CRUD-L | Customer master. Form captures contact/address/company/level. →B1/B2. |
| `customer_follow_up_list.php` | SPECIAL | Follow-up pipeline (due/upcoming/missed/lost). Log action + next-contact date + notes (message shortcuts). Status auto-moved by crons. *(flow in PRD_DETAILED §C7)* |
| `cus_level.php` / `_table.php` | CRUD-F/L | Customer tier definitions. |
| `cus_repeat.php` / `_table.php` | CRUD-F/L | Repeat-purchase classification rules. |
| `cus_segmentation.php` / `_table.php` | CRUD-F/L | Segmentation rules. |
| `website_customer_record.php` / `_table.php` | CRUD-F/L | Website-channel customers. |
| `urb_cust_reg.php` | SPECIAL | Customer registration intake. 1) dedupe lookup by name/IC 2) handle file attachment upload 3) seed FB link from `fb_cust_deals` / `fb_order_req` 4) `INSERT` registration 5) may redirect to a related FB order (`fb_order_req.php?id=&act=E`). |
| `lazada_cust_rcd.php` / `_table.php` | CRUD-F/L | Lazada customer records. |
| `shopee/shopee_cust_info.php` / `_table.php` | CRUD-F/L | Shopee customer records. |
| `tag.php` / `tagTable.php` | CRUD-F/L | Customer tags (+ `include/customer_tag.php` shared logic). |
| `label.php` / `label_table.php` | CRUD-F/L | Labels. |
| `include/customer_follow_up_common.php` | INCLUDE | (191 KB) Follow-up shared logic, status transitions, list rendering. |
| `include/customer_tag.php` | INCLUDE | (67 KB) Tagging engine. |

---

# 4. Products & Catalog / 产品目录

| Page | Tag | Process |
|---|---|---|
| `product.php` / `product_table.php` | CRUD-F/L | Product master (brand, category, status, cost, barcode). →B1/B2. |
| `product_import.php` | IMPORT | Excel import. `parse_xlsx()` → reverse-map names→IDs (`getReverseMapping`/`resolveForeignId`) → normalize dates/numbers/barcode-status → preview → upsert. →B5. |
| `product_category.php` / `_table.php` | CRUD-F/L | Categories. |
| `prod_status.php` / `_table.php` | CRUD-F/L | Product statuses. |
| `brand.php` / `brand_table.php` | CRUD-F/L | Brands (linked to company). *(reference impl for →B2)*. |
| `brand_series.php` / `_table.php` | CRUD-F/L | Brand series. |
| `package.php` / `package_table.php` | CRUD-F/L | Product bundles: name, item_code, cost/price + currency, product list, barcode_slot_total. |
| `package_import.php` | IMPORT | Bulk package import. →B5. |
| `barcode_generator.php` | SPECIAL | 1) pick product/warehouse 2) `generate` → read `barcode_prefix`+`barcode_next_number` from `projects` 3) build codes, render printable QR/barcodes (phpqrcode) 4) `UPDATE projects.barcode_next_number`. |

---

# 5. Stock & Warehouse / 库存与仓库

| Page | Tag | Process |
|---|---|---|
| `stockIn.php` | SPECIAL | Stock-in for a product/warehouse. 1) validate prod/whse/user 2) per submit: `INSERT` stock-in row (brand, product, barcode, batch, status, category, warehouse, PIC) 3) maintain stock costing & `balance_quantity` (shipping_cost logic). |
| `stockOut.php` | SPECIAL | Stock-out. 1) find available stock rows (not yet out) 2) match order across channels (Lazada/Shopee/FB/Website) by order id 3) `UPDATE *_order_req.order_status` 4) mark stock row out (date/PIC/customer purchase id). |
| `stockCosting.php` | SPECIAL | Costing maintenance per brand/product. |
| `stockRecord.php` | SPECIAL | Stock movement record view. |
| `stock_list.php` → `stock_list_table.php` | CRUD-L | Current stock listing. |
| `stock_report.php` / `_detail.php` / `_summary.php` | REPORT | Stock report: summary totals + drill-down detail; bulk-select; export. |
| `warehouse.php` / `warehouse_table.php` | CRUD-F/L | Warehouse master. |
| `warehouse_stock_in.php` | SPECIAL | Inbound stock-in order (header + items). Attachment upload (`siUploadAttachmentFiles`); add/edit; delete cascades soft-delete to items. |
| `warehouse_stock_in_table.php` | CRUD-L | Stock-in order list + export. |
| `warehouse_stock_in_import.php` | IMPORT | Bulk inbound import. →B5. |
| `warehouse_stock_in_scan.php` | SPECIAL | Barcode-scan inbound + attachments + IP/country security check → `scanSaveOrderSecure()`. *(flow in PRD_DETAILED §C5)* |
| `purchase_order.php` | SPECIAL | PO to suppliers (header + line items). →B2 with multi-row items; `INSERT/UPDATE` PO. |
| `purchase_order_table.php` | CRUD-L | PO list. |
| `purchase_order_import.php` | IMPORT | Bulk PO import. →B5. |
| `update_shipment_info.php` | SPECIAL | Update shipment/tracking info on an order. |
| `rate_checking.php` | SPECIAL | Check shipping rate by country (EasyParcel) → feeds `make_order.php`. |
| `courier.php` / `courier_table.php` | CRUD-F/L | Courier master. |

---

# 6. Orders / 订单

| Page | Tag | Process |
|---|---|---|
| `make_order.php` | SPECIAL | Manual order + EasyParcel airbill booking. 1) from `rate_checking.php` 2) MY/SG domain+auth 3) create courier/customer if new 4) book → AWB 5) `INSERT ship_request`. *(flow in PRD_DETAILED §C1)* |
| `common_import.php` | INCLUDE/SPECIAL | Shared order-import card/UI logic. |
| `order_request_info_common.php` | INCLUDE | Shared order-info rendering. |
| `lazada_order_req.php` / `lazada_order_req_table.php` | SPECIAL/L | Lazada order requests (create/process). |
| `lazada_order_request_info.php` | SPECIAL | Lazada order info detail. |
| `lazada_order_req_income_table.php` / `_detail` / `_summary` | REPORT | Lazada income reconciliation (see §8). |
| `fb_cust_deals.php` / `_table.php` | CRUD-F/L | Facebook customer deals. |
| `shopee_order_import.php` | IMPORT/SPECIAL | Shopee order + airbill PDF import. PDF→`extractShopeeAirbillDataFromPdfItems` → match → preview → upsert. *(flow in PRD_DETAILED §C3)* |
| `shopee_ads_topup_import.php` | IMPORT | Shopee ads top-up import (PDF/Excel). →B5. |
| `facebook_ads_topup_import.php` | IMPORT | FB ads top-up import (PDF/Excel). →B5. |

---

# 7. Shopee module / Shopee 模块 (`shopee/`)

| Page | Tag | Process |
|---|---|---|
| `shopee/index.php` | SPECIAL | Shopee module landing. |
| `shopee_order_req.php` / `shopee_order_req_table.php` | SPECIAL/L | Shopee order requests; shares PDF airbill parser with import. |
| `shopee_order_request_info.php` / `shopee_order_request_common.php` | SPECIAL/INCLUDE | Order info + shared logic. |
| `shopee_processing_order.php` | SPECIAL | Processing queue with filters/grouping (`applyFilterOrGroup`, `autoToggleSections`). |
| `shopee_verify.php` | SPECIAL | Verify orders; estimated-received-date modal; POST verify → finance-verified. *(flow in PRD_DETAILED §C4)* |
| `shopee_order_verification_table.php` | CRUD-L | Verification list. |
| `shopee_finance_verified_table.php` | REPORT | Finance-verified Shopee orders. |
| `shopee_order_report_table.php` | REPORT | Shopee order report; bulk-select + export. |
| `shopee_order_req_income_table.php` / `_detail` / `_summary` | REPORT | Shopee income reconciliation (see §8). |
| `shopeeOrder_request_income.php` | REPORT | Shopee income entry view. |
| `shopee_ads_topup_trans.php` / `_table` / `_detail` / `_summary` | CRUD-F/REPORT | Shopee ads top-up transactions. |
| `shopee_withdrawal_transactions.php` / `_table` / `_detail` / `_summary` | CRUD-F/REPORT | Shopee payout/withdrawal reconciliation. |
| `shopee_acc.php` / `_table.php` | CRUD-F/L | Shopee account master. |
| `shopee_sg_setting.php` / `_table.php` | CRUD-F/L | Shopee SG settings. |
| `shopee_service_charges_rate_setting.php` / `_table.php` | CRUD-F/L | Service-charge rates. |
| `payment_method_shopee.php` / `_table.php` | CRUD-F/L | Shopee payment methods. |
| `include/shopee_order_detail_pdf_common.php` | INCLUDE | (53 KB) Shopee order PDF builder. |
| `include/shopee_order_verify_modal_ui.php` | INCLUDE | (37 KB) Verify modal UI + PDF preview. |

---

# 8. Finance module / 财务模块 (`finance/`)

> Standard per-type quartet: `X.php` (entry →B2) · `X_table.php` (list →B1) · `X_table_detail.php` (line breakdown) · `X_table_summary.php` (aggregated totals). Income tables support cross-page bulk-select (`updateCheckboxesOnOtherPages`) + export.

### 8.1 Channel income reconciliation / 渠道收入对账 (REPORT)
| Page group | Process |
|---|---|
| `fb_order_req_income_table(.php/_detail/_summary)` | FB order income reconcile. *(pattern in PRD_DETAILED §C6)* |
| `website_order_request_income_table(.php/_detail/_summary)` | Website order income. |
| `lazadaOrder_request_income.php`, `shopeeOrder_request_income.php` | Lazada/Shopee income entry views. |
| `order_process_list.php`, `arrival_management.php`, `waiting_to_pack.php` | Fulfilment work queues; platform tabs (`activatePlatformTab`); valid-row counts. |

### 8.2 Order request (finance side) / 订单请求 (SPECIAL)
| Page | Process |
|---|---|
| `fb_order_req.php` / `_table.php`, `fb_order_request_info.php` | FB order request CRUD + info. |
| `website_order_request.php` / `_table.php`, `_info.php` | Website order request CRUD + info; new-customer inline validation. |

### 8.3 Transaction backups (CRUD-F + REPORT)
| Page group | Process |
|---|---|
| `atome_trans_backup(.php/_table/_detail/_summary)` | Atome gateway transactions. |
| `bank_trans_backup(.php/_table)` | Bank transactions. |
| `stripe_trans_backup(.php/_table/_detail/_summary)` | Stripe transactions. |
| `j&t_trans_backup(.php/_import/_table)` | J&T transactions + import. |

### 8.4 Cash, bank & capital ledgers (CRUD-F)
`cash_on_hand_trans`, `curr_bank_trans`, `initial_capital_trans`, `investment_trans`, `invtr_trans`, `other_creditor_trans`, `sundry_debt_trans` (each `.php` + `_table.php`) — ledger entry + list + audit.

### 8.5 Top-ups, ads & credits (CRUD-F + REPORT)
`fb_ads_topup_trans(.php/_table/_detail/_summary)`, `downline_top_up_record(...)`, `stock_credit_top_up_request(...)`, `meta_ads_acc(.php/_table)` — top-up/ad-spend records & reconciliation.

### 8.6 Commissions & consumption (CRUD-F + REPORT)
`merchant_comm_record(...)`, `internal_consume_item(...)`, `internal_consume_ticket_credit(...)`, `del_fees_claim(...)` — `generateTableRow()` builds rows; detail+summary views.

### 8.7 Invoices / credit & debit notes (SPECIAL)
| Page | Process |
|---|---|
| `cred_notes_inv.php` / `_table.php` | Credit-note invoice: header + product rows; add/update rows in `cred_inv_prod`. |
| `cred_inv_create.php` | Invoice view/print screen: status (Paid/Cancelled), **Print/Download** → `generate_pdf.php?id=&act=E`, Edit, Back. |
| `debit_notes_inv.php` / `_table.php`, `debit_inv_create.php` | Debit-note equivalent. |
| `generate_pdf.php` | dompdf: load HTML template (A4 portrait) → render → stream PDF. |
| `template.html` | PDF HTML template. |

### 8.8 Stock ordering (supplier) (SPECIAL)
| Page | Process |
|---|---|
| `stock_order_request.php` | Header + items; add/update (`addRecord`/`updRecord`); delete cascades to items; **Telegram alert** on actions (`stock_order_request_info.php`). |
| `stock_order_request_table.php` / `_view.php` / `_info.php` / `_import.php` | List / view / info / bulk import. |
| `stock_order_tracking_refresh.php` | Refresh tracking status (also via cron). |

### 8.9 Finance master data (CRUD-F/L)
`agent`, `merchant`, `lazada_acc`, `fb_page_acc`, `expense_type`, `payment_terms`, `fin_payment_method`, `tax`, `chanel_social_media` (each `.php` + `_table.php`).

### 8.10 Finance reports (REPORT)
`brand_report_table(.php/_summary)`, `package_report_table(.php/_summary)`, `payment_method_report_table(.php/_summary)`, `sales_person_report_table(.php/_summary)`, `flow_report.php`, `flow_setting.php`, `report/staff_comm_table.php`.

### 8.11 Finance shared/includes
`include/customer_follow_up_common.php` (shared), `finance/submodel/`, `finance/header/`.

---

# 9. Task / Project board / 任务看板 (`task/`)

| Page | Tag | Process |
|---|---|---|
| `task/board.php?project_id=` | SPECIAL | Kanban board. AJAX-driven (`ajaxUrl: board.php?project_id=`); per-field permission checks; `taskBoardAuditLog()`; JSON responses for drag/update. |
| `task/board_item_detail_modal.php` | SPECIAL | Task detail modal. |
| `task/board_item_history.php` | SPECIAL | Task change history. |
| `task/sheets.php?project_id=` | SPECIAL | Spreadsheet view of tasks. |
| `task/summary.php?project_id=` | REPORT | Project summary. |
| `task/project_settings.php` | SPECIAL | Project config. |
| `task/project_user_access.php` | SPECIAL | Per-project member access. |
| `task/common_task.php` | INCLUDE | Shared task logic. |
| `menu_bar.php` | INCLUDE | Task sub-nav (links board/sheets/summary/settings/access by `project_id`). |

---

# 10. System Settings & Admin / 系统设置与管理

| Page | Tag | Process |
|---|---|---|
| `system_setting.php` | SPECIAL | Global system config. |
| `theme_setting.php` | SPECIAL | UI theme settings. |
| `token_setting.php` / `_table.php` | CRUD-F/L | Integration tokens. Form: name, **page_used**, **bot_token**, **chat_id** (Telegram). Schema-guarded (`page_used` optional). Used by alert/notification sends. |
| `platform.php` / `_table.php` | CRUD-F/L | Sales platforms. |
| `currencies.php` / `_table.php` | CRUD-F/L | Currencies. |
| `currency_unit.php` / `_table.php` | CRUD-F/L | Currency units. |
| `weight_unit.php` / `_table.php` | CRUD-F/L | Weight units. |
| `bank.php` / `bank_table.php` | CRUD-F/L | Bank master. |
| `sql_account.php` / `_table.php` | CRUD-F/L | Accounting-software accounts. |
| `message_shortcuts.php` / `_table.php` | CRUD-F/L | Canned message templates. |
| `goalTarget.php` / `_table.php` | CRUD-F/L | Sales targets. |
| `system_alert_action.php` | SPECIAL | Handle alert click → action/redirect + mark read. |
| `system_alert_live.php` | SPECIAL | Live-poll endpoint for unread alerts. |
| `audit_log.php` | REPORT | Audit trail viewer. |
| `error_log.php` | REPORT | Error log viewer. |
| `user_record_log.php` | REPORT | User activity log. |
| `include/system_alert_common.php` | INCLUDE | (43 KB) Alert generation/fetch/count engine. |
| `include/user_record_log.php` | INCLUDE | (48 KB) Activity-logging engine. |

### Users & permissions
| Page | Tag | Process |
|---|---|---|
| `user.php` / `user_table.php` | CRUD-F/L | Users (assign group/access). `togglePassword()` on form. |
| `user_group.php` / `user_group_table.php` | CRUD-F/L | Groups: assign PIN access string (parsed at login). |
| `pin.php` / `pin_table.php` | CRUD-F/L | Permission units. |
| `pin_group.php` / `pin_group_table.php` | CRUD-F/L | Permission groups (drive menu + page titles via `getPinGroupNameById`). |

### HR / payroll reference (all CRUD-F/L)
`holiday`, `marital_status`, `race`, `identityType`, `em_type_status`, `employee_epf_rate`, `employer_epf_rate`, `socso_category` (each `.php` + `_table.php`) — lookup tables for payroll/HR.

---

# 11. Shared infrastructure / 共享基础设施 (`include/` + root)

| File | Process |
|---|---|
| `init.php` | Bootstrap: env detection → DB creds + `SITEURL` + constants + session config + action codes. |
| `include/common.php` | (457 KB) Master function library: `getData`, `audit_log`, EasyParcel, Telegram, PDF/airbill parsing, render helpers, etc. |
| `include/connection.php` | DB connect ($connect/$finance_connect) + auth gate (auto-login or redirect to login). |
| `include/access.php` | Per-page PIN access resolution. |
| `include/common_variable.php` | Global constants (links, image paths). |
| `menuHeader.php` | Top bar + menu (PIN-filtered) + alert bell (generates alerts per request). |
| `header.php` | Page head assets. |
| `checkCurrentPagePin.php` | Page PIN gate. |
| `insert_table.php` | (202 KB) **DB migration/schema sync** script (add/drop/alter columns) — not the form save handler. |
| `recordDelete.php` | `deleteRecord()` soft-delete + audit. |
| `searchData.php`, `getSearch.php`, `getSearch2.php` | Server-side search endpoints (autocomplete / DataTables). |
| `export.php` | Generic export helper. |
| `redirect.php`, `shorten.php` | URL redirect / shortener. |
| `recordDelete.php` | (above). |
| `blog/preview.php`, `ps/index.php` | Misc standalone pages. |

---

# 12. Cron / Automation / 定时任务 (CRON)

| File | Process | *(flow: PRD_DETAILED §D)* |
|---|---|---|
| `cron_customer_follow_up_due.php` | Flag follow-ups due today. |
| `cron_customer_follow_up_missed.php` | Mark missed (past due, no action). |
| `cron_customer_follow_up_lost.php` | Mark lost (long inactive). |
| `cron_flow_daily_email.php` | Compile + email daily finance/flow summary (`email_cc`). |
| `cron_flow_housekeeping.php` | Finance data housekeeping/cleanup. |
| `cron_stock_order_tracking_refresh.php` | Refresh supplier stock-order tracking. |
| `cron_system_alert_message.php` | Generate scheduled system alert messages. |

---

*This catalog lists all source pages. CRUD-tagged pages share the verified →B1/→B2/→B3 processes (see PRD_DETAILED.md) plus the entity-specific notes above; SPECIAL pages were inspected individually. For field-level detail of any single page, open the file — every page's save logic lives in its own `case 'addData'/'updData'` block near the top.*
