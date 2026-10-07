# دور ۱ — ارتقای مدیریت M01 تا M50
این فهرست اولیه پیش از تکمیل توسعه گسترده ثبت شد؛ نمونه اولیه محلی پیش از ثبت فهرست وجود داشت. تقویم ساده، روزهای SLA، nonce و مجوزها پیش‌نیاز موجود هستند و پیشنهاد جدید شمرده نشده‌اند.
وضعیت توسعه: ۵۰ قابلیت نرم‌افزاری پیاده‌سازی شده؛ آزمون مستقل PHP و syntax پاس شده؛ آزمون نصب واقعی، HPOS و کلاسیک، مرورگر و انتشار نسخه ۳.۴.۰ موفق شدند؛ شواهد قطعی در گزارش مرکزی دور ثبت شده‌اند.

| شناسه | پیشنهاد تازه | معیار پذیرش | محل کد / شاهد | وضعیت |
|---|---|---|---|---|
| M01 | آماده‌سازی دقیقه‌ای مستقل از روز حمل | ۱۲۰ دقیقه کار از ۱۱:۳۰ با استراحت ۱۲ تا ۱۳ در ۱۴:۳۰ پایان می‌یابد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M02 | شروع متفاوت شیفت هر روز هفته | شیفت چهارشنبه ۱۰ تا ۱۱ از ساعت ۱۰ پذیرش دارد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M03 | پایان متفاوت شیفت هر روز هفته | ۶۰ دقیقه مانده شیفت چهارشنبه به پنجشنبه منتقل می‌شود | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M04 | حذف زمان استراحت از آماده‌سازی | دقیقه‌های بازه استراحت در daily_minutes شمرده نمی‌شوند | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M05 | انتقال مانده آماده‌سازی به شیفت بعد | کار بیشتر از بازه نخست روز بعد ادامه می‌یابد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M06 | شروع زودهنگام استثنایی یک تاریخ | استثنای ۶ صبح، سفارش ۶ صبح را همان روز می‌پذیرد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M07 | پایان دیرهنگام استثنایی یک تاریخ | استثنای پایان ۲۰، سفارش ساعت ۱۷ را به فردا نمی‌برد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M08 | بستن شیفت در تاریخ خاص | مقدار closed روز را برای کار آماده‌سازی بسته می‌کند | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M09 | بازگشایی روز تعطیل با شیفت استثنایی | شیفت تاریخ بر holidays اولویت دارد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M10 | آماده‌سازی چندروزه با صورت ریز روزها | daily_minutes مصرف هر روز را جدا ثبت می‌کند | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M11 | ثبت زمان دقیق پایان آماده‌سازی | completed مقدار ISO با دقیقه صحیح دارد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M12 | ثبت تعهد ثابت از زمان واقعی سفارش | promise با تاریخ ایجاد WC_Order تولید و ذخیره می‌شود | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M13 | ثبت مقصد ثابت همراه تعهد | province و city در promise ثبت می‌شوند | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M14 | اثر انگشت سیاست همراه تعهد | policy_hash طول ۶۴ دارد | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M15 | هشدار تغییر سیاست پس از ثبت تعهد | تفاوت fingerprint فعلی و ثابت هشدار می‌دهد | ServicePolicies::deadline/transition/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M16 | نگهداری نسخه‌های قبلی تعهد | اصلاح قبلی در promise_history تا ۵۰ نسخه حفظ می‌شود | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M17 | اصلاح دستی مهلت با دلیل | override بدون note رد می‌شود | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M18 | رد اصلاح مهلت قبل از ایجاد سفارش | override پیش از created_at رد می‌شود | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M19 | توقف ساعت تعهد با زمان شروع | hold زمان paused_at را ذخیره می‌کند | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M20 | ادامه ساعت تعهد و تمدید به اندازه توقف | resume بعد از ۳۶۰۰ ثانیه مهلت را ۳۶۰۰ ثانیه می‌افزاید | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M21 | جمع زمان توقف سفارش | pause_seconds بین توقف‌های متعدد جمع می‌شود | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M22 | حفظ مهلت اصلی پس از اصلاح | original_deadline پس از override و resume ثابت می‌ماند | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M23 | برآورد مجدد مستقل از تعهد ثابت | forecast جای promise را نمی‌گیرد | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M24 | نمایش شروع بازه پذیرش مؤثر | accepted_at شروع واقعی شیفت قابل کار را دارد | ServicePolicies::deadline/transition/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M25 | نمایش دقیقه و پایان آماده‌سازی | صفحه minutes و completed را نمایش می‌دهد | ServicePolicies::deadline/transition/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M26 | پرونده ساختاریافته آسیب و اختلال سفارش | open نوع معتبر و شناسه یکتا را ثبت می‌کند | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M27 | شدت چهارسطحی پرونده | low/medium/high/critical ثبت می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M28 | پذیرش صریح رسیدگی پرونده | ack فقط پرونده open را acknowledged می‌کند | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M29 | ثبت زمان پذیرش پرونده | ack_at زمان واقعی عملیات است | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M30 | بستن صریح پرونده پس از رسیدگی | resolve پرونده را closed می‌کند | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M31 | نتیجه الزامی بستن پرونده | resolve بدون نتیجه با سیاست پیش‌فرض رد می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M32 | بازگشایی پرونده با دلیل | reopen فقط از closed و با note ممکن است | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M33 | سپردن پرونده به مسئول مجاز | assign فقط کاربر دارای مجوز عملیات را می‌پذیرد | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M34 | مهلت مستقل رسیدگی پرونده | due تاریخ محلی فرم را به epoch تبدیل و تا ۹۰ روز اعتبارسنجی می‌کند | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M35 | نشانگر پرونده گذشته از مهلت | due گذشته و پرونده بسته‌نشده نشانگر معوق دارند | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M36 | سن پرونده به ساعت | اختلاف اکنون و created_at به ساعت نمایش داده می‌شود | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M37 | زمان سپری‌شده تا پذیرش | اختلاف ack_at و created_at نمایش داده می‌شود | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M38 | زمان سپری‌شده تا حل | اختلاف resolved_at و created_at نمایش داده می‌شود | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M39 | شمار پرونده بحرانی حل‌نشده | summary critical تنها برای بسته‌نشده‌ها می‌شمارد | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M40 | شمار همه پرونده‌های باز | summary open فقط بسته‌نشده‌ها را می‌شمارد | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M41 | ثبت علت ریشه‌ای پرونده | root_cause مستقل ثبت و نمایش می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M42 | ثبت اقدام اصلاحی پرونده | corrective مستقل ثبت و نمایش می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M43 | ثبت اقدام پیشگیرانه پرونده | preventive مستقل ثبت و نمایش می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M44 | ثبت نتیجه تماس با مشتری | contact مستقل ثبت و نمایش می‌شود؛ پیام خودکار ارسال نمی‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M45 | طبقه‌بندی نتیجه رسیدگی | resolution مستقل ثبت و نمایش می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M46 | ثبت هزینه زیان پرونده با مجوز مالی | cost عدد محدود است و مجوز finance لازم دارد | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M47 | ثبت مبلغ بازیافت با مجوز مالی | recovery عدد محدود است و مجوز finance لازم دارد | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M48 | گزارش خالص زیان پرونده‌ها | summary net برابر loss منهای recovery است | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M49 | جست‌وجوی پرونده در سفارش انتخابی | ورودی جست‌وجو کارت‌های نامرتبط را پنهان می‌کند | ServicePolicies::v34-policies.js؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |
| M50 | دریافت خصوصی پرونده بدون افشای مبلغ به اپراتور | export مجوز، nonce و سفارش را بررسی و مبلغ را برای اپراتور غیرمالی حذف می‌کند | ServicePolicies::export؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی نرم‌افزاری تأییدشده در دور ۱ |

محدودیت‌ها: تعهد صرفاً برنامه داخلی فروشگاه است، خبر زنده یا تعهد شرکت حمل نیست. پنجره‌های شیفت درون یک روز هستند؛ شیفت شبانه باید به دو روز تفکیک شود. استراحت مشترک برای همه روزهاست. ویرایش شیفت‌ها، تاریخ‌های استثنایی و استراحت با جدول فارسی و ورودی ساعت/تاریخ انجام می‌شود. پرونده‌ها حداکثر ۲۰۰ مورد و تاریخچه حداکثر ۲۰۰ رخداد در هر سفارش دارند. واحد هزینه همان واحد پول سفارش انتخاب‌شده است و کنار مقادیر نمایش داده می‌شود؛ بازیافت صرفاً ثبت حسابداری است و پرداخت یا بازپرداخت واقعی انجام نمی‌دهد. تماس صرفاً ثبت نتیجه اپراتور است و پیام ارسال نمی‌کند. برآورد مستقل باید با فرم ثبت شود و خودکار تعهد ثابت را تغییر نمی‌دهد. هیچ سخت‌افزار، سرویس خارجی یا API ماهکس دخیل نیست.

شاهد تکمیلی توسعه: آزمون مستقل `tests/v34-policies-test.php` در ۲۰۲۶-۱۰-۰۷ پس از اصلاح محاسبه بازه‌ای پاس شد؛ علاوه بر معیارهای جدول، آماده‌سازی ۱۴۴۰۰ دقیقه‌ای در ۲۴۰ شیفت، استراحت‌های هم‌پوشان، تقویم کاملاً مسدود، آماده‌سازی صفر، پذیرش ساعت ۶ و پایان شیفت ساعت ۲۰ بررسی شدند. افق محاسبه ۱۰۹۶ روز است؛ پیمایش دقیقه‌به‌دقیقه حذف شد. lint PHP و بررسی syntax جاوااسکریپت پاس شدند. این شاهد جایگزین آزمون نصب واقعی و مرورگر نهایی دور نیست.

زمان ایجاد دارای ثانیه به دقیقه بعد گرد می‌شود تا پذیرش پیش از ثبت واقعی سفارش رخ ندهد. برآورد مستقل همچنان از زمان ایجاد سفارش و سیاست فعلی محاسبه می‌شود؛ برآورد از اکنون نیست.


تأیید نهایی ۲۰۲۶-۱۰-۰۷: [اجرای موفق MySQL/HPOS/کلاسیک و مرورگر](https://github.com/anonyset/mahex-shipping-for-woocommerce/actions/runs/37569129245) و [ریلیز مستقل ۳.۴.۰](https://github.com/anonyset/mahex-shipping-for-woocommerce/releases/tag/v3.4.0). بررسی کد همه معیارها و آزمون‌های متناسب انجام شده‌اند؛ آزمون مرورگر جریان‌های اصلی را پوشش می‌دهد، نه اجرای مستقل صد سناریوی فیزیکی. CRC و SHA256 فایل منتشرشده پس از دانلود مستقل تأیید شدند.
