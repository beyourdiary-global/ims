# Beyourdiary CMS — REST API v1

Read-only JSON access to the CMS data behind the **Daily Sales & Customer Report**:
Shopee customer records and the Shopee order report.

Nothing in here can write, edit or delete CMS data.

---

## 1. Get a key

1. Log in to the CMS as usual.
2. Open **`https://cms.beyourdiary.com/api/key_manager.php`**.
3. Type a name (e.g. `Reporting AI`) and click **Create key**.
4. **Copy the key immediately** — it is shown once. Only its SHA-256 hash is stored,
   so it can never be recovered. If you lose it, revoke it and create a new one.

Keys are **read-only** and can be revoked at any time from the same page.

---

## 2. Authenticate

Send the key one of three ways (most preferred first):

| How | Example |
| --- | --- |
| `X-API-Key` header **(recommended)** | `X-API-Key: cms_1a2b3c...` |
| Bearer token | `Authorization: Bearer cms_1a2b3c...` |
| Query string | `?api_key=cms_1a2b3c...` |

> The query-string form is convenient for pasting into a browser, but URLs end up in
> logs, history and referrers. Use the header whenever the caller supports it.

Quick test:

```bash
curl -s -H "X-API-Key: cms_YOUR_KEY" https://cms.beyourdiary.com/api/ping.php
```

```json
{"ok":true,"data":{"pong":true,"key_name":"Reporting AI","scopes":["read"],"server_time":"2026-10-07T14:00:00+08:00"}}
```

Every response is `{"ok":true,"data":{...}}` or `{"ok":false,"error":{"code":"...","message":"..."}}`.

Error codes: `missing_api_key`, `invalid_api_key`, `insufficient_scope`,
`missing_parameter`, `customer_not_found`, `unknown_platform`, `db_unavailable`,
`key_store_unavailable`, `internal_error`.

---

## 3. Endpoints

Base URL: `https://cms.beyourdiary.com/api`

A machine-readable index is always available at **`GET /index.php`** (no key required).

### `GET /ping.php`
Health check. Returns the key name and scopes.

### `GET /customers.php`
Shopee customer records — the "Customer" side of the report.

| Param | Meaning |
| --- | --- |
| `q` | free-text search over buyer username / contact / remark |
| `limit` | page size, default 50, max 500 |
| `offset` | pagination offset, default 0 |
| `tag_id` | only customers carrying this tag id |
| `label_type` | `segmentation` \| `level` \| `repeat` |
| `label_id` | label id, used together with `label_type` |

```bash
curl -s -H "X-API-Key: $KEY" \
  "https://cms.beyourdiary.com/api/customers.php?limit=20&q=chloe"
```

```json
{
  "ok": true,
  "data": {
    "total": 1, "count": 1, "limit": 20, "offset": 0, "has_more": false,
    "rows": [
      {
        "id": 744,
        "buyer_username": "chloe_075",
        "contact_no": "0123456789",
        "country": "132", "country_name": "Malaysia",
        "brand": "5",   "brand_name": "SO.FIT",
        "series": "9",  "series_name": "Series A",
        "pic": "31",    "pic_name": "Sally",
        "remark": "VIP from Jan",
        "birthday": "1992-04-11",
        "birthday_year": "1992", "birthday_month": "04", "birthday_day": "11",
        "status": "A",
        "tags": [{"tag_id": 7, "name": "Big Spender", "remark": ""}],
        "labels": {
          "segmentation": {"id": 2, "name": "Loyal"},
          "level": {"id": 11, "name": "Gold"}
        }
      }
    ]
  }
}
```

`*_name` fields are the human-readable value; the raw `country` / `brand` / `series` / `pic`
fields hold the stored id.

### `GET /customer.php`
One customer, with profile + tags + labels + user record log.

| Param | Meaning |
| --- | --- |
| `id` | customer id (preferred) |
| `username` | `buyer_username`, used when `id` is absent |
| `log_limit` | max user-record-log entries, default 20, max 200 |

```bash
curl -s -H "X-API-Key: $KEY" "https://cms.beyourdiary.com/api/customer.php?id=744"
```

Adds `user_record_log` (newest first) and `user_record_log_total`:

```json
"user_record_log": [
  {
    "id": 5012,
    "summary": "Follow up on delivery",
    "content": "<p>Customer replied...</p>",
    "attachments": ["https://server.beyourdiary.com/attachment/..."],
    "next_follow_up_date": "2026-10-10",
    "follow_up_times": "", "follow_up_day": "",
    "created_at": "2026-10-05 11:20:00",
    "updated_at": "2026-10-05 11:20:00",
    "created_by": "Susan"
  }
]
```

`content` is stored HTML.

### `GET /sales.php`
Shopee order report — the "Sales" side. Reuses the exact pipeline that
`shopee/shopee_order_report.php` renders, so totals match the CMS screen.

| Param | Meaning |
| --- | --- |
| `platform` | `shopee` (default) \| `facebook` \| `website` \| `lazada` |
| `date_from` | `YYYY-MM-DD`, inclusive |
| `date_to` | `YYYY-MM-DD`, inclusive (defaults to `date_from`) |
| `include_rows` | `1` (default) returns order rows, `0` returns totals only |
| `limit` | max rows, default 100, max 1000 |
| `offset` | row offset, default 0 |

```bash
curl -s -H "X-API-Key: $KEY" \
  "https://cms.beyourdiary.com/api/sales.php?date_from=2026-10-01&date_to=2026-10-07&limit=50"
```

```json
{
  "ok": true,
  "data": {
    "platform": "shopee",
    "date_from": "2026-10-01",
    "date_to": "2026-10-07",
    "order_count": 128,
    "totals": { "...": "same metric keys as the on-screen summary cards" },
    "breakdowns": { "...": "per package / brand / warehouse / payment" },
    "rows": { "total": 128, "count": 50, "limit": 50, "offset": 0, "has_more": true, "rows": [] }
  }
}
```

---

## 4. Files

| File | Role |
| --- | --- |
| `index.php` | public endpoint index / docs |
| `ping.php` | key health check |
| `customers.php` | customer list |
| `customer.php` | one customer + log |
| `sales.php` | order report |
| `key_manager.php` | browser UI to create / list / revoke keys (needs a CMS login) |
| `lib/bootstrap.php` | JSON envelope, DB bootstrap, key auth, request helpers |
| `lib/customer_shape.php` | shared customer row shaping |
| `lib/.htaccess` | blocks HTTP access to the library |

The `api_key` table is created automatically on first use, and is also part of
`run_migration.php` (section 6) for anyone who prefers an explicit migration.

---

## 5. Security notes

- Only **SHA-256 hashes** of keys are stored; the plaintext exists once, at creation.
- All endpoints are **read-only**. No endpoint issues an `INSERT`, `UPDATE` or `DELETE`.
- Keys carry the `read` scope. The scope check is enforced server-side, so a future
  write scope cannot be reached with a read key.
- `lib/` is denied at the web-server level and every library file also refuses to run
  when requested directly.
- Every key records `last_used_at`, `last_used_ip` and a call counter, so a leaked key
  is visible in the key manager.
- **A key pasted into a third-party AI chat is a key you no longer control.**
  It is read-only and revocable, but treat it like a password: revoke it when the
  experiment is over.
