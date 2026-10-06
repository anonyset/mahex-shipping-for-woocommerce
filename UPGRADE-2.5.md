# Upgrade to 2.5.0 — Intelligence & Automation

این نسخه روی هسته 2.0.0 ساخته شده و کاملاً Local-only است؛ هیچ API خارجی برای قابلیت‌های اصلی استفاده نمی‌شود.

## مسیر ارتقا
- جداول 2.5 با `dbDelta` ساخته می‌شوند.
- Backfill دفتر مالی سفارش‌های قبلی به صورت Batch انجام می‌شود تا فروشگاه بزرگ هنگام ارتقا قفل نشود.
- داده‌های 1.0 و 2.0 حذف یا بازنویسی نمی‌شوند.
- آرشیو Eventهای قدیمی به صورت Batch و قابل خاموش‌کردن اجرا می‌شود.

## ماژول‌های جدید
Intelligence, Automation Inbox, Workflow Manager, Finance Ledger, Report Builder, Archive Manager, Performance Budget, Release Guard.
