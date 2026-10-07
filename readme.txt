=== Mahex Shipping for WooCommerce ===
Contributors: hoseinmomeni
Tags: woocommerce, shipping, iran, persian, rtl, packaging, warehouse
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 3.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

سامانه محلی مدیریت ارسال ووکامرس با نرخ‌گذاری، بسته‌بندی، موجودی، عملیات انبار، چاپ، رهگیری داخلی و گزارش سود.

== Description ==

Mahex Shipping for WooCommerce 3.0.1 یک پروژه مستقل برای مدیریت عملیات ارسال داخل وردپرس و ووکامرس است. هسته نسخه 3.0.1 به‌صورت Local-only طراحی شده و برای محاسبه نرخ، ثبت گردش داخلی مرسوله، بسته‌بندی، چاپ، رهگیری داخلی و گزارش‌ها به سرویس خارجی وابسته نیست.

قابلیت‌های تازه ۳.۶.۰: برنامه‌ریز آفلاین موج‌های ارسال با ظرفیت و snapshot، و کمپین نمونه‌برداری کیفیت بسته‌بندی با قرنطینه و بازبینی دوم. بدون API ماهکس.

قابلیت‌های شاخص:

* موتور نرخ‌گذاری شرطی AND/OR با اولویت، توقف پردازش، وزن، تعداد، مبلغ سبد، دسته، کلاس ارسال و برند.
* کنترل سود شامل سود درصدی/ثابت/پلکانی، حداقل سود، یارانه فروشگاه، سهم مشتری، سقف کرایه و گردکردن.
* بسته‌بندی چندکارتنی بر اساس ابعاد، حجم، وزن، موجودی، شکستنی/مایع، ارسال جدا و No-Mix.
* موجودی ملزومات بسته‌بندی با دفتر ورود/خروج، هشدار کمبود، شمارش، Adjustment و ارزش موجودی.
* Pick List، Packing List، Packing Station، تأیید SKU/بارکد و صف Ready to Ship.
* Label Builder مرتب‌شونده، چاپ A4/حرارتی، واترمارک، بارکد/QR، Print Queue و شمارش چاپ مجدد.
* دفترچه آدرس مشتری، توضیح و زمان ترجیحی تحویل، کارت ارسال در حساب کاربری و صفحه رهگیری داخلی.
* داشبورد سود و عملیات، جست‌وجوی سریع، فیلترهای ذخیره‌شده، گزارش مشتری/شهر و Order Index اختصاصی.
* Migration، Backup قبل از ارتقا، Rollback، Safe Mode، Repair Center، Profiler و Release Qualification Suite.
* نقش‌ها و دسترسی‌های جداگانه برای مدیر، اپراتور، بسته‌بند و حسابدار.
* پشتیبانی از HPOS و واحد پول IRR/IRT.

== Installation ==

1. ZIP افزونه را از Plugins > Add New > Upload Plugin نصب کنید.
2. WooCommerce باید فعال باشد.
3. افزونه را فعال کنید؛ Migration و Backup پیش از ارتقا به‌صورت خودکار اجرا می‌شوند.
4. روش ارسال Mahex را به Shipping Zoneهای موردنظر اضافه کنید.
5. از «ماهکس > مرکز نسخه 1.0» نرخ، بسته‌بندی، چاپ و عملیات را تنظیم کنید.
6. قبل از استفاده روی فروشگاه اصلی، Release Qualification و یک سفارش آزمایشی Checkout را اجرا کنید.

== Frequently Asked Questions ==

= آیا این افزونه رسمی ماهکس است؟ =

خیر. این یک پروژه مستقل است و ادعای رسمی‌بودن یا تأییدشدن توسط ماهکس ندارد.

= نسخه 1.0.0 برای محاسبه یا رهگیری به سرویس بیرونی متصل می‌شود؟ =

خیر. مسیر اجرایی نسخه 1.0.0 محلی است. نرخ، بسته‌بندی، گردش مرسوله و رهگیری ارائه‌شده توسط این نسخه در خود فروشگاه مدیریت می‌شوند.


= بروزرسانی از GitHub چگونه انجام می‌شود؟ =

نسخه 3.0.1 فایل update.json عمومی Repository را بررسی می‌کند. اگر نسخه جدیدتر باشد، WordPress آن را در صفحه افزونه‌ها و بروزرسانی‌ها نمایش می‌دهد. عملیات اصلی ارسال همچنان محلی است.

= اگر ارتقا مشکل داشته باشد چه می‌شود؟ =

قبل از Migration یک Snapshot داخلی ایجاد می‌شود. در خطای Migration، Restore انجام می‌شود و Safe Mode می‌تواند روش ارسال افزونه را موقتاً از Checkout کنار بگذارد تا فروشگاه از دسترس خارج نشود.

= آیا برای فروشگاه بزرگ مناسب است؟ =

Large Store Mode و جدول Order Index برای جلوگیری از اسکن سنگین متادیتای تمام سفارش‌ها در گزارش‌ها و جست‌وجو اضافه شده‌اند. با این حال هر فروشگاه بزرگ باید نسخه را ابتدا روی Staging و با داده واقعی خودش تست کند.

== Changelog ==

= 3.6.0 =
* Offline dispatch waves using real WooCommerce orders, capacity controls, deterministic sequencing, snapshots, drift checks and drag-and-drop.
* Manual packaging-quality sampling campaigns, versioned criteria, quarantine, second review, responsive RTL board and minimal-data exports.
* Standard privacy export/erase integration and quarantine guard before local handover sealing.
* No Mahex/carrier API, webhook or carrier queue; physical observations remain manual declarations.

= 3.5.0 =
* Historical address remediation with selected-field preview, conflict-aware undo and recipient verification.
* Internal dispatch holds, bin workbench, controlled bulk actions and dispatch instructions.
* Minute-level work calendars, frozen local promises and structured incident cases.
* No carrier API; independently gated WordPress installation and browser tests.

= 3.3.0 =
* Local scanning and measured parcels, inventory, batch RTL PDF, returns and claims.
* Carrier CSV reconciliation, scoped roles, rule versions and safe settings transfer.
* Isolated MySQL/HPOS and browser verification; no carrier API.

= 3.2.0 =
* Draggable workspace and actual order board with undo.
* Real order label designer, manual packing and visual rate rules.
* Fixed startup rewrite initialization; guarded persistent saves.

= 3.0.1 =

* اضافه شدن GitHub Update Channel استاندارد برای بروزرسانی از داخل WordPress.
* اضافه شدن Plugin Information modal و لینک GitHub/مستندات در ردیف افزونه.
* اضافه شدن مستندات دو‌زبانه GitHub Pages و آموزش‌های تصویری.
* اضافه شدن CI برای PHP lint و اعتبارسنجی update.json.

= 3.0.0 =

* Multi-Store & Control Center، شعب، انبار، QC، Task، Wallet/Credit و Network Analytics.


= 1.0.0 =

* معماری Local-only و حذف مسیرهای شبکه خارجی از اجرای نسخه 1.
* Migration/Backup/Rollback، Safe Mode، Repair Center و Release Qualification.
* موتور نرخ شرطی، Margin Engine و محاسبه عددصحیح برای جلوگیری از خطای گردکردن.
* بسته‌بندی چندکارتنی، Best Fit، محدودیت وزن/تعداد، جداسازی شکستنی/مایع/No-Mix.
* موجودی و Inventory Ledger بسته‌بندی.
* مرکز عملیات Pick/Packing، Packing Station، Bulk Edit و Ready-to-Ship.
* Label Builder، Print Queue، قالب A4 و حرارتی و شمارش Reprint.
* داشبورد قابل تنظیم، بازه زمانی سریع، جست‌وجو و گزارش سودآوری.
* دفترچه آدرس، ترجیحات تحویل، رهگیری داخلی و کارت ارسال مشتری.
* نقش‌های عملیاتی و قفل تنظیمات حساس.

== Upgrade Notice ==

= 1.0.0 =

این یک ارتقای بزرگ است. افزونه قبل از Migration Snapshot داخلی می‌سازد، اما برای سایت اصلی ابتدا روی Staging نصب و Checkout، نرخ، چاپ و بسته‌بندی آزمایش شود.

== 2.5.0 ==
Shipping OS: performance cache, event/parcel stores, daily metrics, operator shifts, problem center, returns and local insights.


## 2.5.0 Intelligence & Automation
تحلیل سود و زیان، پیشنهاد Rule، پیش‌بینی بسته‌بندی، عملیات هوشمند، Workflowهای برگشتی/تعویض، دفتر مالی، گزارش‌ساز، آرشیو و Release Guard — کاملاً محلی و بدون API خارجی.
