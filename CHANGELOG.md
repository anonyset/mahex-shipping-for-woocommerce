# 3.2.0 — 2026-10-07

- Per-user draggable workspace and persisted order preparation board with operators, audit history and undo.
- Thermal/A4 label designer using real order data, barcode, logo and saved printable layout.
- Validated manual packing with trusted weights, capacities, unit assignment and print integration.
- Visual rule editor using the live rate engine, preview, guarded saves and shipping-session cache invalidation.
- Fixed premature rewrite endpoint initialization; included label layout in update settings snapshots.
- See [upgrade guide](UPGRADE-3.2.0.md) for usage and verification limits.

# 3.1.0 — 2026-10-06

- Persian setup with readiness checks, validated CSV/XLSX carrier-reference import, owner-only tracking and optional email templates.
- Actual shipping costs and profit, RTL printable invoice/Save as PDF, native XLSX, scheduled tariffs and historical rate metadata.
- Compatibility/checksum update guards and pre-update settings snapshots.
- Fixed packaging double-counting and prevented simulated refresh of imported carrier statuses.
- See [upgrade guide](UPGRADE-3.1.0.md) for supported formats and verification limits.

# Changelog

## 3.0.1 - 2026-10-06

- Added native WordPress update discovery from the public GitHub `update.json` manifest.
- Added one-click GitHub package updates with safe source-folder normalization.
- Added WordPress plugin-information metadata, GitHub and documentation links.
- Added bilingual GitHub Pages documentation with visual installation, Shipping Zone, packing and update tutorials.
- Added GitHub Actions for PHP lint/manifest validation and documentation deployment.
- Added security, contribution and release documentation plus issue templates.
- Shipping/pricing/packing core remains local-only; GitHub network access is limited to update checks and package downloads.

## 3.0.0 - 2026-10-06

- Multi-Store & Control Center: branches, branch stock/transfers, governance/approvals, QC/tasks, wallet/credit and network analytics.
- Local shipping core with no external operational API dependency.

## 1.0.0 - 2026-10-05

- Rebuilt the plugin around a local-only WooCommerce shipping operations core.
- Added versioned migrations, pre-upgrade snapshots, rollback and a fatal-error Safe Mode.
- Added integrity diagnostics, repair actions, setup verification and release qualification checks.
- Added dedicated event, inventory-ledger, print-queue, order-index and packing-session tables.
- Added a conditional AND/OR pricing rule engine with priorities and stop-processing behavior.
- Added integer-safe margin, subsidy, customer-share, cap and rounding calculations.
- Added multi-box best-fit packing with weight, volume, stock and maximum-item constraints.
- Added fragile, liquid, separate-shipment and no-mix product rules.
- Added packaging stock ledger, adjustments, low-stock/critical warnings and valuation.
- Added batch picking, zone pick lists, packing lists, packing verification and ready-to-ship workflows.
- Added draggable label-field ordering, A4/thermal output, watermark/font controls, print queue and reprint counters.
- Added customizable dashboards, quick date windows, saved search filters and large-store order indexing.
- Added local customer tracking timeline, delivery preferences, address book and account shipping cards.
- Added separate operational permissions for managers, operators, packers and accountants.
- Removed external-network execution from the 1.0 runtime/UI; migration cleanup strips legacy connection settings during upgrade.

## 0.4.0 - 2026-10-05

- Enterprise logistics foundation, multi-warehouse routing, parcel splitting, ETA, NDR, queueing and audit features.

## 0.3.0 - 2026-10-05

- Operations center, advanced pricing, packaging inventory, bulk labels and reporting.

## 0.2.0 - 2026-10-05

- Currency/province fixes, volumetric packing integration, rate rules, health tools and reporting foundations.

## 2.0.0 — Shipping OS
- اضافه شدن Rate Cache نسل‌دار با invalidation روی نرخ، محصول و بسته‌بندی.
- اضافه شدن Event Store ساختاریافته با Correlation ID برای سفارش‌ها و مرسوله‌ها.
- اضافه شدن Parcel Store اختصاصی برای جست‌وجو و گزارش سریع مرسوله‌ها.
- اضافه شدن Daily Metrics برای گزارش‌های سریع در فروشگاه‌های بزرگ.
- اضافه شدن Problem Center و اسکن سفارش‌های مانده، مغایرت بسته‌بندی، کدپستی و کرایه صفر.
- اضافه شدن شیفت اپراتور و گزارش عملکرد بسته‌بندی.
- اضافه شدن مدیریت پرونده برگشتی و هزینه برگشت.
- اضافه شدن Insight Engine برای سفارش‌های زیان‌ده، شهرهای کم‌بازده، کمبود بسته‌بندی و پیش‌بینی حجم.
- اضافه شدن WP-CLI برای health، metrics، problems و cache.
- اضافه شدن پنل Shipping OS 2.0 و Module Registry.
- حفظ معماری Local-only بدون اتصال API خارجی.

## 2.5.0 — Intelligence & Automation
- Added local Smart Shipping Advisor, loss analysis, rule suggestions/conflict detection, simulator and what-if analysis.
- Added packaging forecasts, reorder suggestions, efficiency/oversize analytics and profit forecasting.
- Added return/customer/city/product scoring, anomalies and duplicate shipment detection.
- Added Operations Inbox, SLA prediction, priority planning, workload balance, bottleneck detection and briefs.
- Added return/exchange/reship/damage/evidence workflows.
- Added local finance ledger, daily settlement, monthly closing, budget vs actual and cost centers.
- Added report/dashboard builders, scheduled snapshots, archive batches, performance budgets and release guard.
- Added batch migration/backfill from 2.0 without external API dependencies.
