# Mahex Shipping for WooCommerce 1.0.0 — Feature Matrix

> وضعیت: تمام موارد زیر در معماری نسخه 1.0.0 دارای مسیر اجرایی/مدیریتی هستند. نسخه 1.0.0 Local-only است.

| # | قابلیت | محل پیاده‌سازی | نتیجه |
|---:|---|---|---|
| 1 | Migration Engine | `V1/MigrationManager + Schema` | مهاجرت نسخه‌بندی‌شده و بدون حذف داده سفارش‌ها |
| 2 | Rollback ارتقا | `V1/MigrationManager + BackupManager` | بازیابی Snapshot در شکست Migration |
| 3 | Backup قبل از Upgrade | `V1/BackupManager` | Snapshot خودکار پیش از مهاجرت |
| 4 | Database Integrity Scanner | `V1/Diagnostics` | شناسایی جدول/ایندکس/رهگیری ناسالم |
| 5 | Repair Center | `V1/Diagnostics + Admin` | تعمیر خودکار موارد قابل اصلاح |
| 6 | Safe Mode | `V1/SafeMode` | کنارگذاشتن روش ارسال در Fatal مربوط به افزونه |
| 7 | Feature Flags | `V1/Config` | روشن/خاموش‌کردن ماژول‌های اصلی |
| 8 | Setup Verification | `V1/SetupVerifier` | بررسی WooCommerce، تنظیمات، Cron و داده پایه |
| 9 | Reset جزئی | `V1/Admin` | Reset انتخابی قیمت/چاپ/مشتری/Profiler |
| 10 | Factory Reset امن | `V1/Admin` | تأیید چندمرحله‌ای و Backup قبل از Reset |
| 11 | داشبورد 1.0 | `V1/Admin + Reports` | KPI ارسال، بسته‌بندی، سود و برگشتی |
| 12 | داشبورد قابل شخصی‌سازی | `V1/Admin` | نمایش واقعی ویجت‌های انتخاب‌شده |
| 13 | فیلتر زمانی سریع | `V1/Admin + Reports` | امروز، ۷، ۳۰، ۹۰ و ۳۶۵ روز |
| 14 | جستجوی سراسری | `V1/OrderIndex` | سفارش، مشتری، موبایل، شهر و رهگیری |
| 15 | Saved Filters | `V1/Admin` | ذخیره جست‌وجوهای پرتکرار |
| 16 | ستون‌های قابل تنظیم | `V1/Config + Admin` | ستون‌های جدول عملیات قابل انتخاب |
| 17 | Bulk Edit | `V1/Admin` | ویرایش گروهی وضعیت بسته‌بندی سفارش‌ها |
| 18 | Quick View | `V1/Admin` | نمایش اقلام سفارش در همان جدول |
| 19 | Command Palette | `V1/Admin` | پرش سریع میان بخش‌های مرکز نسخه 1 |
| 20 | Activity Feed | `V1/ActivityLog` | رویدادهای ساختاریافته و قابل مشاهده |
| 21 | Rule Builder تصویری | `V1/Admin + RuleEngine` | ساخت Rule از پنل بدون کدنویسی |
| 22 | AND/OR Rules | `V1/RuleEngine` | منطق ترکیبی شرط‌ها |
| 23 | اولویت Ruleها | `V1/RuleEngine` | ترتیب اجرا بر اساس Priority |
| 24 | Stop Processing | `V1/RuleEngine` | توقف ادامه قوانین پس از Match |
| 25 | بازه وزنی نامحدود | `V1/RuleEngine` | Min/Max وزن برای هر قانون |
| 26 | نرخ بر اساس تعداد | `V1/RuleEngine` | Min/Max تعداد کالا |
| 27 | نرخ بر اساس دسته محصول | `V1/RuleEngine` | شرط دسته‌های ووکامرس |
| 28 | نرخ بر اساس کلاس ارسال | `V1/RuleEngine` | Shipping Class در Rule |
| 29 | نرخ بر اساس برند | `V1/RuleEngine` | پشتیبانی taxonomyهای رایج برند |
| 30 | نرخ ترکیبی | `V1/RuleEngine` | ترکیب مقصد، وزن، تعداد، مبلغ و محصول |
| 31 | Minimum Shipping Margin | `V1/MarginEngine` | کف سود ریالی نسبت به کرایه پایه |
| 32 | سود درصدی | `V1/MarginEngine` | افزایش درصدی با محاسبه عددصحیح |
| 33 | سود مبلغ ثابت | `V1/MarginEngine` | حاشیه ثابت ریالی |
| 34 | سود پلکانی | `V1/MarginEngine` | Tier بر اساس کرایه پایه |
| 35 | سقف کرایه مشتری | `V1/MarginEngine` | Cap برای مبلغ پرداختی مشتری |
| 36 | Subsidy Shipping | `V1/MarginEngine` | یارانه درصدی فروشگاه |
| 37 | Split Shipping Cost | `V1/MarginEngine` | سهم قابل تنظیم مشتری از کرایه |
| 38 | Round Pricing | `V1/MarginEngine` | گردکردن به پله ریالی |
| 39 | Psychological Pricing | `V1/MarginEngine` | پایان قیمت قابل تنظیم |
| 40 | Margin Simulator | `Admin + checkout breakdown` | ریز محاسبه و پیش‌نمایش Rule/هزینه |
| 41 | 3D Packing Logic | `V1/PackingEngine` | ابعاد، حجم و وزن در انتخاب بسته |
| 42 | Best Fit Package | `V1/PackingEngine` | انتخاب کوچک‌ترین بسته مناسب |
| 43 | Multi-Box Packing | `V1/PackingEngine` | تقسیم سبد به چند بسته |
| 44 | Max Weight Per Box | `Packaging Profiles` | سقف وزن هر پروفایل |
| 45 | Max Items Per Box | `Packaging Profiles` | حداکثر تعداد در بسته |
| 46 | Fragile Separation | `ProductFields + PackingEngine` | تفکیک کالای شکستنی |
| 47 | Liquid Separation | `ProductFields + PackingEngine` | تفکیک محصول مایع |
| 48 | No-Mix Rules | `ProductFields + PackingEngine` | گروه‌های غیرقابل اختلاط |
| 49 | Packaging Waste | `V1/PackingEngine` | درصد فضای غیرقابل استفاده |
| 50 | Packaging Preview | `V1/Admin` | پیش‌نمایش بسته‌های سفارش |
| 51 | Stock Ledger بسته‌بندی | `V1/InventoryLedger` | دفتر ورود/خروج مستقل |
| 52 | حداقل موجودی | `Packaging Profiles` | Min stock برای هر بسته |
| 53 | هشدار بحرانی | `V1/InventoryLedger` | Low/Critical stock |
| 54 | مصرف بر اساس سفارش | `Inventory/PackagingInventory` | ثبت مصرف متصل به Order |
| 55 | برگشت موجودی | `Inventory/PackagingInventory` | برگشت مصرف در لغو/Refund |
| 56 | شمارش انبار | `V1/Admin + InventoryLedger` | ثبت موجودی واقعی |
| 57 | Adjustment | `V1/InventoryLedger` | اصلاح موجودی همراه دلیل و اپراتور |
| 58 | تاریخچه قیمت کارتن | `Packaging Profiles` | ثبت Price History هنگام تغییر هزینه |
| 59 | ارزش ریالی موجودی | `V1/InventoryLedger` | ارزش موجودی بر مبنای Unit Cost |
| 60 | گزارش مصرف | `V1/InventoryLedger` | مصرف اخیر و قابل گزارش |
| 61 | Drag & Drop Label Builder | `V1/Admin` | مرتب‌سازی فیلدهای لیبل با Drag & Drop |
| 62 | A4 Template Builder | `BulkLabelRenderer` | ستون‌بندی قابل تنظیم A4 |
| 63 | Thermal Templates | `WaybillRenderer` | خروجی حرارتی چند اندازه |
| 64 | Persian Font Control | `V1/Config + WaybillRenderer` | انتخاب فونت چاپ |
| 65 | Dynamic Fields | `WaybillRenderer` | گیرنده، مقصد، آدرس، موبایل، بارکد و… |
| 66 | Conditional Fields | `WaybillRenderer` | نمایش شرطی مبلغ COD |
| 67 | Watermark | `WaybillRenderer` | واترمارک/لوگوی قابل تنظیم |
| 68 | Print Queue | `V1/PrintManager` | صف پایدار درخواست چاپ |
| 69 | Reprint Counter | `V1/PrintManager` | شمارش چاپ مجدد |
| 70 | چاپ بدون Browser UI | `Document renderers` | خروجی چاپ تمیز مستقل از پنل |
| 71 | Pick List | `V1/Operations` | لیست جمع‌آوری کالا |
| 72 | Packing List | `V1/Operations` | فهرست بسته‌بندی سفارش |
| 73 | Batch Picking | `V1/Operations` | تجمیع چند سفارش |
| 74 | Zone Picking | `V1/Operations` | گروه‌بندی بر اساس زون محصول |
| 75 | Packing Verification | `V1/Operations` | تطبیق اقلام اسکن‌شده |
| 76 | Barcode Verification | `V1/Operations` | پذیرش SKU/Product ID برای Verify |
| 77 | Wrong Item Warning | `V1/Operations` | گزارش اقلام اضافه |
| 78 | Missing Item Warning | `V1/Operations` | گزارش اقلام جاافتاده |
| 79 | Packing Station Mode | `V1/Admin + Operations` | رابط اختصاصی تأیید بسته |
| 80 | Ready-to-Ship Queue | `V1/Operations` | صف سفارش‌های آماده ارسال |
| 81 | صفحه رهگیری حرفه‌ای | `Documents/PublicTracking` | رهگیری داخلی برندپذیر |
| 82 | Timeline داخلی | `Documents/PublicTracking` | تاریخچه رویدادهای مرسوله |
| 83 | Estimated Delivery | `Enterprise/ETA + tracking UI` | نمایش ETA ذخیره‌شده |
| 84 | Order Shipping Card | `V1/CustomerExperience` | کارت ارسال در My Account |
| 85 | Download Label | `V1/CustomerExperience` | چاپ لیبل با کنترل مالکیت سفارش |
| 86 | Shipping Notes | `V1/CustomerExperience` | یادداشت‌های تحویل در سفارش/حساب |
| 87 | Delivery Preferences | `V1/CustomerExperience` | ذخیره ترجیحات تحویل Checkout |
| 88 | Preferred Time | `V1/CustomerExperience` | بازه زمانی ترجیحی |
| 89 | Address Book | `V1/CustomerExperience` | چند آدرس ذخیره‌شده مشتری |
| 90 | Reorder Shipping | `V1/CustomerExperience` | انتقال ترجیحات از سفارش قبلی |
| 91 | Profit & Loss Dashboard | `V1/Reports` | درآمد، هزینه و سود ارسال |
| 92 | Cost Allocation | `V1/Reports` | تفکیک حمل، بسته‌بندی و هزینه عملیاتی |
| 93 | Customer Profitability | `V1/Reports` | سودآوری به تفکیک مشتری |
| 94 | City Profitability | `V1/Reports` | سودآوری شهر/استان |
| 95 | Return Cost Report | `V1/Reports` | هزینه مرسوله‌های برگشتی |
| 96 | Role Permissions 2.0 | `Enterprise/Roles + V1/Admin` | مدیر/اپراتور/بسته‌بند/حسابدار |
| 97 | Sensitive Settings Lock | `V1/Admin` | محدودسازی Restore/Import/Reset حساس |
| 98 | Performance Profiler | `V1/Profiler` | Count/Avg/P95/Max مسیرهای اندازه‌گیری‌شده |
| 99 | Large Store Mode | `V1/OrderIndex` | جدول ایندکس اختصاصی برای جست‌وجو/گزارش |
| 100 | Release Qualification Suite | `V1/ReleaseQualification` | تست سلامت نسخه پیش از انتشار |


# Shipping OS 2.0

- ✅ Rate Cache نسل‌دار و Invalidation دقیق
- ✅ Event Store ساختاریافته + Correlation ID
- ✅ Parcel Store اختصاصی
- ✅ Daily Metrics برای گزارش سریع
- ✅ Problem Center و اولویت‌بندی مشکلات روز
- ✅ شیفت اپراتور و سنجش عملکرد
- ✅ پرونده برگشتی و هزینه برگشت
- ✅ Insight Engine برای زیان، مقصد، موجودی و حجم آینده
- ✅ Module Registry
- ✅ WP-CLI: health / metrics / problems / cache
- ✅ Migration و Snapshot قبل از ارتقا
- ✅ Local-only؛ بدون API خارجی

## 2.5.0
۵۰ قابلیت Intelligence & Automation جدید در `FEATURES-2.5.md` مستند شده‌اند. این لایه بدون API خارجی و بر داده‌های محلی ووکامرس/افزونه کار می‌کند.
