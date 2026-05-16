# 16 — WooCommerce Pro (GTIN/MPN/ISBN, auto-noindex, schema enrichment)

**MOAT lever:** Computation lock-in for product schema — we emit the
exact identifiers Google Shopping requires.

## What the feature does

Loads only when `class_exists('WooCommerce')`:

- Adds Product Identifiers panel to the product edit screen
  (GTIN-8/12/13/14, MPN, ISBN, brand).
- Enriches the auto Product JSON-LD with those identifiers via the
  `ebq_schema_product_node` filter.
- Auto-noindexes products with catalog visibility `hidden` OR stock
  status `outofstock`.
- Provides `[ebq_product_gtin]` shortcode for front-end display.

## Files

- [`ebq-seo-wp/includes/class-ebq-woocommerce.php`](../../ebq-seo-wp/includes/class-ebq-woocommerce.php)
- [`ebq-seo-wp/includes/class-ebq-schema-output.php`](../../ebq-seo-wp/includes/class-ebq-schema-output.php) — Product node hook
- Plan flag: `plan_features.woo_pro`

## Pre-conditions

- WooCommerce ≥ 8.0 active.
- Plan has `woo_pro` on (Pro+).
- A test product with stock + visibility set, and a hidden product to
  verify the noindex path.

## Scenarios

### 1. Identifier UI present

In wp-admin, edit a product. Scroll to the General tab.

✅ The "EBQ product identifiers" group shows GTIN type dropdown +
GTIN, MPN, ISBN, brand inputs.

### 2. Schema enrichment

Save a GTIN-13 (e.g. `4006381333931`) on a product, then view the
product page source:

```bash
curl -s https://yoursite.test/product/<slug>/ | grep -o '"gtin13":"[^"]*"'
```

✅ The Product JSON-LD block contains `"gtin13":"4006381333931"`.

### 3. Auto-noindex hidden product

Set a product's catalog visibility to "Hidden". View the product page:

```bash
curl -s https://yoursite.test/product/<slug>/ | grep -o '<meta name="robots"[^>]*>'
```

✅ Robots meta contains `noindex`.

### 4. Auto-noindex out-of-stock

Set a product's stock status to "Out of stock":

```bash
curl -s https://yoursite.test/product/<slug>/ | grep -o '<meta name="robots"[^>]*>'
```

✅ Robots meta contains `noindex`.

### 5. Plan gate

Toggle `woo_pro` off; verify the identifier UI disappears and the
auto-noindex path no-ops.
