# 17 — Multi-location Local SEO (CPT, store finder, KML sitemap)

**MOAT lever:** Data gravity (each location row enriches schema +
sitemap) + computation lock-in (per-branch LocalBusiness JSON-LD
nodes auto-built from the row).

## What the feature does

- Registers an `ebq_location` CPT under EBQ HQ.
- Stores per-branch address / geo / hours / phone meta.
- Emits a per-branch LocalBusiness JSON-LD node on each location's
  detail page.
- Renders a `[ebq_location_finder]` shortcode + `ebq/location-finder`
  block (search-as-you-type filter).
- Publishes a `/ebq-locations.kml` KML sitemap and adds it to
  `robots.txt` + the main sitemap index.

## Files

- [`ebq-seo-wp/includes/class-ebq-locations.php`](../../ebq-seo-wp/includes/class-ebq-locations.php)
- Plan flag: `plan_features.local_multi`

## Pre-conditions

- Plan has `local_multi` on (Agency by default).
- At least 2 published `ebq_location` posts with address + geo set.

## Scenarios

### 1. CPT availability

In wp-admin sidebar under "EBQ HQ" → "Locations".

✅ List screen renders, "Add new location" works.

### 2. KML sitemap

```bash
curl -i https://yoursite.test/ebq-locations.kml
```

✅ HTTP 200, content-type `application/vnd.google-earth.kml+xml`,
body contains a `<Placemark>` per location with `<Point><coordinates>`.

### 3. Sitemap index advertises KML

```bash
curl https://yoursite.test/ebq-sitemap.xml | grep ebq-locations.kml
```

✅ `<sitemap>` entry references the KML.

### 4. LocalBusiness schema on location page

```bash
curl -s https://yoursite.test/locations/<slug>/ | grep -o '"@type":"[^"]*"' | sort -u
```

✅ Includes `"@type":"LocalBusiness"` (or operator-chosen subtype like
`Restaurant`).

### 5. Store finder filter

Embed `[ebq_location_finder]` on a page. Type a city name in the
search input.

✅ List filters live; only matching locations stay visible.

### 6. Plan gate (Pro tier)

Switch the plan to Pro. Within ~5 min:

✅ `/ebq-locations.kml` returns 404, the CPT submenu hides, schema
nodes stop emitting.
