title: Fix product exports may get stuck permanently
issue: 8402
---
# Core
* Added stale run detection in `src/Core/Content/ProductExport/ScheduledTask/ProductExportGenerateTaskHandler.php` with configurable `product_export.stale_min_seconds` and `product_export.stale_interval_factor` parameters so exports recover when their cronjob stops unexpectedly.
* Changed `isRunning` and delete outdated export files on manual edits via `src/Core/Content/ProductExport/EventListener/ProductExportEventListener.php` to allow subsequent generations to proceed.
