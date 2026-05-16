# 22 — Role Manager (per-role capability matrix)

## What the feature does

Lets administrators grant / revoke EBQ-specific WP capabilities
(`ebq_manage_seo`, `ebq_manage_redirects`, `ebq_manage_hq`,
`ebq_manage_ai_writer`, `ebq_use_link_genius`, `ebq_manage_locations`,
`ebq_run_audit`, `ebq_manage_reports`) per role from one screen.

## Files

- [`ebq-seo-wp/includes/class-ebq-role-manager.php`](../../ebq-seo-wp/includes/class-ebq-role-manager.php)
- Plan flag: `plan_features.role_manager`

## Pre-conditions

- Plan has `role_manager` on (Pro+).

## Scenarios

### 1. Page accessibility

EBQ HQ → Role Manager.

✅ Matrix table renders with one row per cap × one column per WP role.
Administrators column is read-only (always granted).

### 2. Toggle a cap

Tick `ebq_use_link_genius` for the Editor role. Save.

✅ Notice "Capabilities saved." Verify cap stuck:

```php
wp eval "echo (int) get_role('editor')->has_cap('ebq_use_link_genius');"
```

✅ Output: `1`.

### 3. Revoke

Untick the same cell, save.

✅ Output of the same `has_cap` check is `0`.

### 4. Plan gate

Switch the plan to Free:

✅ The "Role Manager" submenu disappears. Existing cap grants are
preserved (caps still exist on roles) but no longer editable from the
plugin.
