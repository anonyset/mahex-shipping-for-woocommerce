# دور ۱ — ارتقای مدیریت M01 تا M50
این فهرست اولیه پیش از تکمیل توسعه گسترده ثبت شد؛ نمونه اولیه محلی پیش از ثبت فهرست وجود داشت. تقویم ساده، روزهای SLA، nonce و مجوزها پیش‌نیاز موجود هستند و پیشنهاد جدید شمرده نشده‌اند.
وضعیت توسعه: ۵۰ قابلیت نرم‌افزاری پیاده‌سازی شده؛ آزمون مستقل PHP و syntax پاس شده؛ آزمون نصب واقعی/HPOS/مرورگر و انتشار در اختیار آزمون نهایی دور است و هنوز در این گزارش ادعای موفقیت آنها نشده است.

| شناسه | پیشنهاد تازه | معیار پذیرش | محل کد / شاهد | وضعیت |
|---|---|---|---|---|
| M01 | آماده‌سازی دقیقه‌ای مستقل از روز حمل | ۱۲۰ دقیقه کار از ۱۱:۳۰ با استراحت ۱۲ تا ۱۳ در ۱۴:۳۰ پایان می‌یابد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M02 | شروع متفاوت شیفت هر روز هفته | شیفت چهارشنبه ۱۰ تا ۱۱ از ساعت ۱۰ پذیرش دارد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M03 | پایان متفاوت شیفت هر روز هفته | ۶۰ دقیقه مانده شیفت چهارشنبه به پنجشنبه منتقل می‌شود | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M04 | حذف زمان استراحت از آماده‌سازی | دقیقه‌های بازه استراحت در daily_minutes شمرده نمی‌شوند | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M05 | انتقال مانده آماده‌سازی به شیفت بعد | کار بیشتر از بازه نخست روز بعد ادامه می‌یابد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M06 | شروع زودهنگام استثنایی یک تاریخ | استثنای ۶ صبح، سفارش ۶ صبح را همان روز می‌پذیرد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M07 | پایان دیرهنگام استثنایی یک تاریخ | استثنای پایان ۲۰، سفارش ساعت ۱۷ را به فردا نمی‌برد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M08 | بستن شیفت در تاریخ خاص | مقدار closed روز را برای کار آماده‌سازی بسته می‌کند | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M09 | بازگشایی روز تعطیل با شیفت استثنایی | شیفت تاریخ بر holidays اولویت دارد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M10 | آماده‌سازی چندروزه با صورت ریز روزها | daily_minutes مصرف هر روز را جدا ثبت می‌کند | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M11 | ثبت زمان دقیق پایان آماده‌سازی | completed مقدار ISO با دقیقه صحیح دارد | ServicePolicies::effort/working؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M12 | ثبت تعهد ثابت از زمان واقعی سفارش | promise با تاریخ ایجاد WC_Order تولید و ذخیره می‌شود | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M13 | ثبت مقصد ثابت همراه تعهد | province و city در promise ثبت می‌شوند | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M14 | اثر انگشت سیاست همراه تعهد | policy_hash طول ۶۴ دارد | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M15 | هشدار تغییر سیاست پس از ثبت تعهد | تفاوت fingerprint فعلی و ثابت هشدار می‌دهد | ServicePolicies::deadline/transition/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M16 | نگهداری نسخه‌های قبلی تعهد | اصلاح قبلی در promise_history تا ۵۰ نسخه حفظ می‌شود | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M17 | اصلاح دستی مهلت با دلیل | override بدون note رد می‌شود | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M18 | رد اصلاح مهلت قبل از ایجاد سفارش | override پیش از created_at رد می‌شود | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M19 | توقف ساعت تعهد با زمان شروع | hold زمان paused_at را ذخیره می‌کند | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M20 | ادامه ساعت تعهد و تمدید به اندازه توقف | resume بعد از ۳۶۰۰ ثانیه مهلت را ۳۶۰۰ ثانیه می‌افزاید | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M21 | جمع زمان توقف سفارش | pause_seconds بین توقف‌های متعدد جمع می‌شود | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M22 | حفظ مهلت اصلی پس از اصلاح | original_deadline پس از override و resume ثابت می‌ماند | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M23 | برآورد مجدد مستقل از تعهد ثابت | forecast جای promise را نمی‌گیرد | ServicePolicies::deadline/transition/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M24 | نمایش شروع بازه پذیرش مؤثر | accepted_at شروع واقعی شیفت قابل کار را دارد | ServicePolicies::deadline/transition/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M25 | نمایش دقیقه و پایان آماده‌سازی | صفحه minutes و completed را نمایش می‌دهد | ServicePolicies::deadline/transition/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M26 | پرونده ساختاریافته آسیب و اختلال سفارش | open نوع معتبر و شناسه یکتا را ثبت می‌کند | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M27 | شدت چهارسطحی پرونده | low/medium/high/critical ثبت می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M28 | پذیرش صریح رسیدگی پرونده | ack فقط پرونده open را acknowledged می‌کند | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M29 | ثبت زمان پذیرش پرونده | ack_at زمان واقعی عملیات است | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M30 | بستن صریح پرونده پس از رسیدگی | resolve پرونده را closed می‌کند | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M31 | نتیجه الزامی بستن پرونده | resolve بدون نتیجه با سیاست پیش‌فرض رد می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M32 | بازگشایی پرونده با دلیل | reopen فقط از closed و با note ممکن است | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M33 | سپردن پرونده به مسئول مجاز | assign فقط کاربر دارای مجوز عملیات را می‌پذیرد | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M34 | مهلت مستقل رسیدگی پرونده | due تاریخ محلی فرم را به epoch تبدیل و تا ۹۰ روز اعتبارسنجی می‌کند | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M35 | نشانگر پرونده گذشته از مهلت | due گذشته و پرونده بسته‌نشده نشانگر معوق دارند | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M36 | سن پرونده به ساعت | اختلاف اکنون و created_at به ساعت نمایش داده می‌شود | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M37 | زمان سپری‌شده تا پذیرش | اختلاف ack_at و created_at نمایش داده می‌شود | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M38 | زمان سپری‌شده تا حل | اختلاف resolved_at و created_at نمایش داده می‌شود | ServicePolicies::transition/summary/page؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M39 | شمار پرونده بحرانی حل‌نشده | summary critical تنها برای بسته‌نشده‌ها می‌شمارد | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M40 | شمار همه پرونده‌های باز | summary open فقط بسته‌نشده‌ها را می‌شمارد | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M41 | ثبت علت ریشه‌ای پرونده | root_cause مستقل ثبت و نمایش می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M42 | ثبت اقدام اصلاحی پرونده | corrective مستقل ثبت و نمایش می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M43 | ثبت اقدام پیشگیرانه پرونده | preventive مستقل ثبت و نمایش می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M44 | ثبت نتیجه تماس با مشتری | contact مستقل ثبت و نمایش می‌شود؛ پیام خودکار ارسال نمی‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M45 | طبقه‌بندی نتیجه رسیدگی | resolution مستقل ثبت و نمایش می‌شود | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M46 | ثبت هزینه زیان پرونده با مجوز مالی | cost عدد محدود است و مجوز finance لازم دارد | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M47 | ثبت مبلغ بازیافت با مجوز مالی | recovery عدد محدود است و مجوز finance لازم دارد | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M48 | گزارش خالص زیان پرونده‌ها | summary net برابر loss منهای recovery است | ServicePolicies::transition/summary/page؛ tests/v34-policies-test.php | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M49 | جست‌وجوی پرونده در سفارش انتخابی | ورودی جست‌وجو کارت‌های نامرتبط را پنهان می‌کند | ServicePolicies::v34-policies.js؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |
| M50 | دریافت خصوصی پرونده بدون افشای مبلغ به اپراتور | export مجوز، nonce و سفارش را بررسی و مبلغ را برای اپراتور غیرمالی حذف می‌کند | ServicePolicies::export؛ آزمون یکپارچه/مرورگر نهایی دور مورد نیاز است | پیاده‌سازی؛ انتظار تأیید یکپارچه |

محدودیت‌ها: تعهد صرفاً برنامه داخلی فروشگاه است، خبر زنده یا تعهد شرکت حمل نیست. پنجره‌های شیفت درون یک روز هستند؛ شیفت شبانه باید به دو روز تفکیک شود. استراحت مشترک برای همه روزهاست. ویرایش شیفت‌ها، تاریخ‌های استثنایی و استراحت با جدول فارسی و ورودی ساعت/تاریخ انجام می‌شود. پرونده‌ها حداکثر ۲۰۰ مورد و تاریخچه حداکثر ۲۰۰ رخداد در هر سفارش دارند. واحد هزینه همان واحد پول سفارش انتخاب‌شده است و کنار مقادیر نمایش داده می‌شود؛ بازیافت صرفاً ثبت حسابداری است و پرداخت یا بازپرداخت واقعی انجام نمی‌دهد. تماس صرفاً ثبت نتیجه اپراتور است و پیام ارسال نمی‌کند. برآورد مستقل باید با فرم ثبت شود و خودکار تعهد ثابت را تغییر نمی‌دهد. هیچ سخت‌افزار، سرویس خارجی یا API ماهکس دخیل نیست.

شاهد تکمیلی توسعه: آزمون مستقل `tests/v34-policies-test.php` در ۲۰۲۶-۱۰-۰۷ پس از اصلاح محاسبه بازه‌ای پاس شد؛ علاوه بر معیارهای جدول، آماده‌سازی ۱۴۴۰۰ دقیقه‌ای در ۲۴۰ شیفت، استراحت‌های هم‌پوشان، تقویم کاملاً مسدود، آماده‌سازی صفر، پذیرش ساعت ۶ و پایان شیفت ساعت ۲۰ بررسی شدند. افق محاسبه ۱۰۹۶ روز است؛ پیمایش دقیقه‌به‌دقیقه حذف شد. lint PHP و بررسی syntax جاوااسکریپت پاس شدند. این شاهد جایگزین آزمون نصب واقعی و مرورگر نهایی دور نیست.

زمان ایجاد دارای ثانیه به دقیقه بعد گرد می‌شود تا پذیرش پیش از ثبت واقعی سفارش رخ ندهد. برآورد مستقل همچنان از زمان ایجاد سفارش و سیاست فعلی محاسبه می‌شود؛ برآورد از اکنون نیست.
