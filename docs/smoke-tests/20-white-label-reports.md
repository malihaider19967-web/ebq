# 20 — White-label client reports (PDF + scheduled email + branding)

## What the feature does

Configures a per-website branded SEO report (logo, colours, sender,
footer) and a cadence (off / weekly / monthly). Each scheduled run
renders a PDF and emails it to the configured recipients.

## Files

- WP UI: [`ebq-seo-wp/includes/class-ebq-reports-page.php`](../../ebq-seo-wp/includes/class-ebq-reports-page.php)
- Backend: [`app/Http/Controllers/Api/V1/ClientReportsController.php`](../../app/Http/Controllers/Api/V1/ClientReportsController.php)
- Tables: `client_report_brands`, `client_report_schedules`, `client_reports`
- Plan flag: `plan_features.white_label`

## Pre-conditions

- Migration `2026_05_18_003000_create_client_reports_tables` ran.
- Plan has `white_label` on (Agency by default).

## Scenarios

### 1. Page accessibility

EBQ HQ → Client Reports.

✅ Two-column layout renders with "Branding" and "Schedule" cards.

### 2. Save branding

Fill in logo URL + sender name + frequency=monthly + recipients.
Click Save.

✅ Page reloads with `ebq_saved=1` query param. `client_report_brands`
table has a row for the website.

```bash
php artisan tinker --execute="echo \DB::table('client_report_brands')->where('website_id', <ID>)->first()?->frequency;"
```

✅ Output: `monthly`.

### 3. Send test email

Click "Send test email".

✅ Returns to the page with a `Test sent` query flag and the queue
shows a pending job (operator-implemented `SendScheduledReportJob`).

### 4. Plan gate (lower tier)

Switch plan to Startup and reload:

✅ The "Client Reports" submenu disappears.
