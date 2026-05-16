# 18 — Bulk Image SEO (find-replace + AI alt + media filters)

## What the feature does

- Find & replace inside `<img>` `alt`, `title`, and caption across
  posts and the media library.
- One-click queue of an AI-powered alt-text generation job for every
  attachment with no alt text.
- Media-library filter dropdown for "missing alt".

## Files

- [`ebq-seo-wp/includes/class-ebq-image-bulk-page.php`](../../ebq-seo-wp/includes/class-ebq-image-bulk-page.php)
- [`app/Http/Controllers/Api/V1/ImageBulkController.php`](../../app/Http/Controllers/Api/V1/ImageBulkController.php)
- Plan flag: `plan_features.image_bulk`

## Pre-conditions

- Plan has `image_bulk` on (Startup+).
- Test site has at least 5 attachments without alt text.

## Scenarios

### 1. Page accessibility

EBQ HQ → Image SEO.

✅ Two cards render: "Find & Replace" and "Bulk AI Alt".

### 2. Find & replace dry path

In Find & Replace: enter a string that exists in at least one alt
value, replace with itself. Submit.

✅ The "applied N rows" status reflects the matched attachments.
WP-CLI sanity:

```bash
wp eval "echo get_post_meta(<ATTACHMENT_ID>, '_wp_attachment_image_alt', true);"
```

### 3. Media-library filter

Media → Library → use the new "Missing alt" dropdown.

✅ Only attachments with empty `_wp_attachment_image_alt` show.

### 4. Plan gate

Toggle `image_bulk` off:

✅ The "EBQ HQ → Image SEO" submenu disappears; the media-library
dropdown still renders but the value resolves to no rows.
