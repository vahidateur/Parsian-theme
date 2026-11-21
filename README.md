# Parsian Music Academy Theme

A custom WordPress theme and companion plugin for the Parsian Music Academy, built
for a Persian, right-to-left (RTL) music-school website.

The repository contains the `parsian-music` theme, the `parsian-assistant`
companion plugin, local development tooling, and the project's multi-agent
governance records.

---

## English

### Overview

Parsian Music Academy Theme is a self-contained WordPress product: a dark,
gold-accented theme with a dashboard-style homepage, teacher profiles, a
consultation form, an optional AI assistant, and a messaging notification gateway
for Bale and Rubika. The companion plugin holds infrastructure that should survive
a theme switch: conversations, webhook ingestion, account linking, update
deduplication, privacy export/erasure, and legacy settings migration.

### Architecture

| Component | Path | Role |
|---|---|---|
| Theme | `parsian-music/` | Presentation, templates, teacher content types, consultation form, settings UI, AI chat widget, notification gateway |
| Companion plugin | `parsian-assistant/` | Shared assistant engine, Bale adapter, webhook ingestion, account linking, privacy tooling, migration |
| Governance | `.agents/`, `AGENTS.md` | Multi-agent task, ownership, review, and release policies |
| CI | `.github/workflows/` | Platform CI matrix, governance validator, Scope Guard, production archives |
| Local development | `wp-local/`, `tools/platform-lab/` | Disposable local WordPress lab, test suites, PHP/JS test harnesses |

### Main features

**Theme**

- Dashboard-style, three-column homepage with a dark/gold design.
- Teacher custom post type (`pm_teacher`) and department taxonomy (`pm_department`).
- Teacher profile with biography, achievements, media, before/after comparison,
  audio sample, video, and consultation form.
- Teacher archive with department filtering.
- Consultation form with server-side validation, nonce, rate limiting, duplicate
  prevention, private booking storage, and notification dispatch.
- Templates for pages, posts, archives, search, 404, and comments; a full-width
  template for page builders.
- RTL layout, responsive design, and an inline SVG icon sprite.
- Tabbed "Parsian" settings panel and WordPress Customizer integration.
- Branded login screen and managed admin dashboard widgets.
- Notification gateway with queue, retries, deduplication, and email fallback.
- AI assistant with a knowledge base and an admin approval workflow.

**Companion plugin**

- Shared assistant engine used by the website chat and Bale.
- Bale webhook ingestion and polling, with update deduplication and rate limits.
- One-time account-linking tokens for Bale chats.
- Deferred replies, bounded retries, operator escalation, and recovery workers.
- Privacy exporter and eraser scoped to per-user privacy subjects.
- Admin-only migration preview, run, restore, and backup retention.
- Graceful degradation when the theme is inactive.

### Requirements

Verified from the repository's platform documentation and CI matrix:

| Component | Minimum | Tested |
|---|---|---|
| WordPress | 7.1 | 7.1.4 |
| PHP | 8.4.27 | 8.5.12 |
| MySQL | 8.4 LTS | 8.4.13 |
| MariaDB | 12.3 LTS | 12.3.3 (primary), 11.8.9 (compatibility) |
| WP-CLI | 2.12.0 | 2.12.0 |
| Node.js / npm (JS tests only) | 26.13.0 / 12.2.2 | same |

Required PHP extensions: `curl`, `json`, `mbstring`, `mysqli` or `pdo_mysql`,
`openssl`, `zip`, `xml`, and `gd` or `imagick`. `opcache` is recommended.
Composer is not required; there is no Composer manifest.

The site should run over HTTPS in production.

### Installation

**Theme**

1. Copy `parsian-music/` into `wp-content/themes/`, or upload the theme ZIP from
   **Appearance → Themes → Add New**.
2. Activate the theme.
3. Open **Settings → Permalinks** and save once so teacher archive URLs resolve.
4. Create menus under **Appearance → Menus**. Until a menu is assigned, a default
   menu is shown.
5. On first activation, demo teachers and departments are created. Replace demo
   content with real academy content.

**Companion plugin**

1. Copy `parsian-assistant/` into `wp-content/plugins/`, or upload its ZIP from
   **Plugins → Add New**.
2. Activate **Parsian Assistant**.
3. The plugin creates its database tables on activation and schedules its
   maintenance/recovery hooks.

**Initial configuration**

- Open the **Parsian** admin menu.
- Configure brand, contact details, homepage content, teachers, forms, login, and
  admin branding.
- For the AI assistant, enter an OpenAI-compatible API URL, model, and API key.
  Endpoint changes require the `manage_options` capability. Additional AI hosts
  must be added explicitly to the administrator-managed allowlist; only documented
  provider hosts pass by default.
- For Bale/Rubika notifications, choose the platform, store the bot token, set the
  destination Chat ID, and use the built-in connection test.
- Review the plugin's `docs/` folder for deployment, cron, migration, security, and
  Bale setup guidance.

### Security architecture

- Capability checks and per-action nonces protect privileged admin-post handlers.
  Nonces are treated as CSRF protection, not authorization.
- Provider endpoint settings (`bale_base`, `rubika_base`, `ai_api_url`, and the AI
  allowlist) require `manage_options`; other theme settings remain available to
  `edit_theme_options`.
- Credential-bearing outbound requests validate the destination before attaching a
  token or API key: HTTPS only, exact provider hostname allowlist, no userinfo,
  no unapproved ports, and no query/fragment components.
- Automatic redirects are disabled for credential-bearing requests, and unsafe-URL
  rejection is enforced at the WordPress HTTP API layer.
- The Bale webhook uses a high-entropy path secret compared with `hash_equals`.
- Account linking uses one-time SHA-256 hashed tokens with expiry and atomic
  single-use consumption.
- Privacy export and erasure are scoped to per-user pseudonymous subjects.
- AI prompt assembly wraps retrieved content as untrusted data, applies injection
  heuristics, uses no tool/function calling, and redacts credential patterns from
  responses and provider errors.
- Secrets are stored server-side and are not localized to JavaScript.

### Tests and CI

Run the checks from a checkout with PHP available:

```bash
php wp-local/tests/pa-outbound-policy-test.php
php .agents/scripts/validate-governance.php --all
php .agents/scripts/validate-governance.php --self-test
php .agents/scripts/tests/validate-task-scope-test.php
```

The WordPress-backed suites require a local WordPress + database and WP-CLI:

```bash
cd wp-local
./wp eval-file tests/pa-security-test.php
./wp eval-file tests/pa-webhook-test.php
./wp eval-file tests/pa-durability-test.php
./wp eval-file tests/pa-migration-test.php
# ...and the remaining pa-*-test.php / pm-1.6.0-smoke.php suites
```

JavaScript popup tests use jsdom:

```bash
cd wp-local/tests/js
npm ci
npm test
```

GitHub Actions runs:

- **Platform CI** — PHP 8.4.27/8.5.12, WordPress 7.1.4 with MySQL 8.4.13, MariaDB
  12.3.3/11.8.9 compatibility jobs, PHP lint, PHP suites, outbound credential
  policy tests, third-party theme compatibility, activation/data persistence,
  Node popup tests, and production archive builds.
- **Governance** — governance validator, self-tests, and the Scope Guard suite.

### Development structure

```text
parsian-music/                 WordPress theme
  inc/                         PHP modules (settings, forms, notifications, AI, etc.)
  template-parts/              Section/card/form/archive templates
  assets/                      CSS, JavaScript, images, icon sprite
parsian-assistant/             Companion plugin
  inc/                         Engine, Bale adapter, store, privacy, migration, admin
  assets/                      Popup assets
  docs/                        Deployment, cron, security, migration, Bale guides
.agents/                       Multi-agent governance policies and validators
.github/workflows/             CI and governance workflows
docs/                          Platform compatibility and upgrade records
tools/platform-lab/            Isolated PHP lab container definition
wp-local/                      Local WordPress lab and test suites
```

### Contributing

1. Read `AGENTS.md` and `.agents/README.md` before changing application code.
2. Work on a focused branch; keep changes scoped and reviewable.
3. Run the relevant PHP, JS, governance, and Scope Guard checks.
4. Never commit credentials, local databases, backups, or generated archives.
5. Open a pull request describing the change, tests run, and any limitations.
6. Report security issues privately to the repository owner rather than in a
   public issue.

### License

Original project source code is **dual-licensed** at your option under:

- the **Apache License, Version 2.0** (see [`LICENSE`](LICENSE)); or
- the **GNU General Public License, version 2 or later** (see
  [`LICENSE-GPL-2.0.txt`](LICENSE-GPL-2.0.txt)).

The GPL option exists because WordPress themes and plugins are derivative works of
WordPress (GPL-2.0-or-later). Apache-2.0 is compatible with GPLv3 but not GPLv2;
offering the GPL option keeps WordPress distribution compatible.

Third-party components and assets are **not** relicensed by this project. See
[`THIRD-PARTY-NOTICES.md`](THIRD-PARTY-NOTICES.md) and [`NOTICE`](NOTICE). Assets
whose provenance could not be verified are excluded from the project's license
grant until the owner confirms their rights.

This documentation is a technical licensing assessment, not legal advice.

### Known limitations

- WP-Cron is traffic-driven; a system cron job is recommended for reliable
  notification retries and maintenance.
- The Vazirmatn font is loaded from Google Fonts at run time; no font files are
  bundled.
- Demo teacher images and the theme screenshot are placeholders with unverified
  provenance; replace them with licensed assets before redistribution.
- Stored provider credentials can be changed by users holding
  `edit_theme_options`. This is a pre-existing configuration behavior, separate
  from the outbound destination policy; restrict that capability to trusted staff.
- The outbound policy test suite is an application-level harness with stubbed
  WordPress functions; it does not replace full WordPress/TLS/DNS runtime testing.

---

## فارسی

### معرفی

«قالب آکادمی موسیقی پارسیان» یک محصول وردپرسی شامل قالب `parsian-music` و افزونهٔ
همراه `parsian-assistant` است. قالب برای سایت فارسی و راست‌چین آموزشگاه موسیقی
طراحی شده و شامل صفحهٔ اصلی داشبوردی، پروفایل اساتید، فرم مشاوره، دستیار هوشمند
اختیاری و درگاه اعلان بله/روبیکا است. افزونهٔ همراه زیرساخت‌هایی را نگه می‌دارد که
با تغییر قالب از بین نروند: گفتگوها، دریافت وب‌هوک، پیوند حساب، جلوگیری از تکرار
به‌روزرسانی، خروجی/حذف دادهٔ شخصی و مهاجرت تنظیمات قدیمی.

### معماری

| بخش | مسیر | نقش |
|---|---|---|
| قالب | `parsian-music/` | ظاهر، قالب‌ها، نوع محتوای استاد، فرم مشاوره، تنظیمات، چت هوشمند، درگاه اعلان |
| افزونهٔ همراه | `parsian-assistant/` | موتور مشترک دستیار، آداپتور بله، وب‌هوک، پیوند حساب، ابزار حریم خصوصی، مهاجرت |
| حاکمیت | `.agents/` و `AGENTS.md` | سیاست‌های چندعاملی، مالکیت، بازبینی و انتشار |
| CI | `.github/workflows/` | ماتریس CI، اعتبارسنج حاکمیت، Scope Guard، ساخت آرشیو |
| توسعهٔ محلی | `wp-local/` و `tools/platform-lab/` | محیط وردپرس محلی، مجموعه‌های تست PHP/JS |

### ویژگی‌های اصلی

**قالب**

- صفحهٔ اصلی داشبوردی سه‌ستونه با طراحی تیره و طلایی.
- نوع محتوای سفارشی استاد (`pm_teacher`) و تاکسونومی دپارتمان (`pm_department`).
- پروفایل استاد با بیوگرافی، دستاوردها، رسانه، مقایسهٔ قبل/بعد، نمونهٔ صوتی،
  ویدئو و فرم مشاوره.
- آرشیو اساتید با فیلتر دپارتمان.
- فرم مشاوره با اعتبارسنجی سمت سرور، nonce، محدودیت نرخ، جلوگیری از تکرار، ذخیرهٔ
  خصوصی درخواست و ارسال اعلان.
- قالب‌های صفحه، نوشته، آرشیو، جستجو، ۴۰۴ و دیدگاه‌ها؛ قالب تمام‌عرض برای
  صفحه‌سازها.
- چیدمان راست‌چین، واکنش‌گرا و اسپرایت SVG داخلی.
- پنل تنظیمات تب‌بندی‌شدهٔ «پارسیان» و اتصال به سفارشی‌ساز وردپرس.
- صفحهٔ ورود اختصاصی و ابزارک‌های پیشخوان مدیریت‌شده.
- درگاه اعلان با صف، تلاش مجدد، حذف تکراری و ایمیل پشتیبان.
- دستیار هوشمند با پایگاه دانش و گردش تأیید مدیر.

**افزونهٔ همراه**

- موتور مشترک دستیار برای چت سایت و بله.
- دریافت وب‌هوک و polling بله با حذف تکراری و محدودیت نرخ.
- توکن‌های یک‌بارمصرف پیوند حساب برای چت بله.
- پاسخ‌های معوق، تلاش مجدد محدود، ارجاع به اپراتور و کارگرهای بازیابی.
- خروجی و حذف دادهٔ شخصی مبتنی بر شناسهٔ ناشناس هر کاربر.
- پیش‌نمایش، اجرا، بازیابی و نگهداری پشتیبان مهاجرت فقط برای مدیر.
- ادامهٔ کار با قابلیت محدود در صورت غیرفعال بودن قالب.

### پیش‌نیازها

| جزء | حداقل | آزمایش‌شده |
|---|---|---|
| WordPress | 7.1 | 7.1.4 |
| PHP | 8.4.27 | 8.5.12 |
| MySQL | 8.4 LTS | 8.4.13 |
| MariaDB | 12.3 LTS | 12.3.3 (اصلی)، 11.8.9 (سازگاری) |
| WP-CLI | 2.12.0 | 2.12.0 |
| Node.js / npm (فقط تست JS) | 26.13.0 / 12.2.2 | همان |

افزونه‌های PHP موردنیاز: `curl`، `json`، `mbstring`، `mysqli` یا `pdo_mysql`،
`openssl`، `zip`، `xml` و `gd` یا `imagick`. `opcache` توصیه می‌شود. Composer لازم
نیست. اجرای سایت روی HTTPS توصیه می‌شود.

### نصب

**قالب**

1. پوشهٔ `parsian-music` را در `wp-content/themes/` قرار دهید یا فایل ZIP را از
   **نمایش ← پوسته‌ها ← افزودن** نصب کنید.
2. قالب را فعال کنید.
3. یک بار **تنظیمات ← پیوندهای یکتا** را ذخیره کنید تا آدرس آرشیو اساتید ساخته شود.
4. از **نمایش ← منوها** منوی اصلی و فوتر را بسازید.
5. در اولین فعال‌سازی، اساتید و دپارتمان‌های نمایشی ساخته می‌شوند؛ آن‌ها را با
   محتوای واقعی جایگزین کنید.

**افزونهٔ همراه**

1. پوشهٔ `parsian-assistant` را در `wp-content/plugins/` قرار دهید یا ZIP آن را از
   **افزونه‌ها ← افزودن** نصب کنید.
2. افزونهٔ **Parsian Assistant** را فعال کنید.
3. جداول پایگاه داده در فعال‌سازی ساخته می‌شوند و هوک‌های نگهداری/بازیابی زمان‌بندی
   می‌شوند.

**تنظیمات اولیه**

- منوی **پارسیان** را باز کنید.
- برند، اطلاعات تماس، محتوای صفحهٔ اصلی، اساتید، فرم‌ها، ورود و پیشخوان را تنظیم
  کنید.
- برای دستیار هوشمند، آدرس API سازگار با OpenAI، مدل و کلید API را وارد کنید.
  تغییر آدرس مقصد نیازمند قابلیت `manage_options` است. دامنه‌های اضافی API باید
  صراحتاً در فهرست مجاز مدیر افزوده شوند؛ به‌صورت پیش‌فرض فقط دامنه‌های مستند عبور
  می‌کنند.
- برای اعلان بله/روبیکا، پلتفرم، توکن ربات و Chat ID مقصد را تنظیم و اتصال را تست
  کنید.
- راهنمای استقرار، کران، مهاجرت، امنیت و راه‌اندازی بله در پوشهٔ `docs/` افزونه
  موجود است.

### معماری امنیتی

- بررسی قابلیت و nonce برای همهٔ عملیات مدیریتی؛ nonce فقط محافظ CSRF است، نه
  مجوز.
- تنظیمات مقصد سرویس‌ها (`bale_base`، `rubika_base`، `ai_api_url` و فهرست مجاز AI)
  نیازمند `manage_options` هستند؛ سایر تنظیمات قالب با `edit_theme_options` قابل
  ویرایش‌اند.
- درخواست‌های دارای اعتبارنامه پیش از افزودن توکن یا کلید، مقصد را اعتبارسنجی
  می‌کنند: فقط HTTPS، تطابق دقیق نام میزبان مجاز، بدون userinfo، بدون پورت غیرمجاز
  و بدون query/fragment.
- تغییرمسیر خودکار برای درخواست‌های دارای اعتبارنامه غیرفعال است و رد آدرس‌های
  ناامن در لایهٔ HTTP وردپرس اعمال می‌شود.
- وب‌هوک بله از یک مسیر محرمانهٔ پرآنتروپی با مقایسهٔ `hash_equals` استفاده می‌کند.
- پیوند حساب با توکن یک‌بارمصرف هش‌شده (SHA-256)، انقضا و مصرف اتمی انجام می‌شود.
- خروجی و حذف دادهٔ شخصی بر اساس شناسهٔ ناشناس هر کاربر محدود می‌شود.
- ورودی دستیار به‌عنوان دادهٔ غیرقابل‌اعتماد بسته‌بندی می‌شود، الگوهای تزریق بررسی
  می‌شوند، فراخوانی ابزار وجود ندارد و الگوهای اعتبارنامه از پاسخ‌ها و خطاها حذف
  می‌شوند.
- اعتبارنامه‌ها فقط سمت سرور ذخیره می‌شوند و به JavaScript ارسال نمی‌شوند.

### تست و CI

```bash
php wp-local/tests/pa-outbound-policy-test.php
php .agents/scripts/validate-governance.php --all
php .agents/scripts/validate-governance.php --self-test
php .agents/scripts/tests/validate-task-scope-test.php
```

مجموعه‌های وردپرسی نیازمند وردپرس محلی، پایگاه داده و WP-CLI هستند:

```bash
cd wp-local
./wp eval-file tests/pa-security-test.php
./wp eval-file tests/pa-webhook-test.php
./wp eval-file tests/pa-durability-test.php
./wp eval-file tests/pa-migration-test.php
```

تست‌های JavaScript با jsdom:

```bash
cd wp-local/tests/js
npm ci
npm test
```

گردش‌کارهای GitHub Actions:

- **Platform CI** — PHP 8.4.27/8.5.12، وردپرس 7.1.4 با MySQL 8.4.13، سازگاری
  MariaDB 12.3.3/11.8.9، lint، مجموعه‌های PHP، تست سیاست مقصد خروجی، سازگاری قالب
  سوم‌شخص، فعال‌سازی/ماندگاری داده، تست‌های Node و ساخت آرشیو.
- **Governance** — اعتبارسنج حاکمیت، خودآزمون‌ها و Scope Guard.

### ساختار توسعه

```text
parsian-music/                 قالب وردپرس
  inc/                         ماژول‌های PHP
  template-parts/              قالب‌های بخش/کارت/فرم/آرشیو
  assets/                      CSS، JavaScript، تصاویر، اسپرایت آیکون
parsian-assistant/             افزونهٔ همراه
  inc/                         موتور، آداپتور بله، فروشگاه، حریم خصوصی، مهاجرت، مدیریت
  assets/                      دارایی‌های پاپ‌آپ
  docs/                        راهنمای استقرار، کران، امنیت، مهاجرت، بله
.agents/                       سیاست‌ها و اعتبارسنج‌های حاکمیت چندعاملی
.github/workflows/             گردش‌کارهای CI و حاکمیت
docs/                          سازگاری پلتفرم و سوابق ارتقا
tools/platform-lab/            تعریف کانتینر آزمایشگاه PHP
wp-local/                      محیط وردپرس محلی و مجموعه‌های تست
```

### مشارکت

1. پیش از تغییر کد برنامه، `AGENTS.md` و `.agents/README.md` را بخوانید.
2. روی شاخهٔ متمرکز کار کنید و دامنهٔ تغییر را محدود نگه دارید.
3. تست‌های PHP، JS، حاکمیت و Scope Guard را اجرا کنید.
4. هرگز اعتبارنامه، پایگاه دادهٔ محلی، پشتیبان یا آرشیو تولیدشده را commit نکنید.
5. Pull Request با شرح تغییر، تست‌های اجراشده و محدودیت‌ها باز کنید.
6. مسائل امنیتی را به‌صورت خصوصی به مالک مخزن گزارش دهید، نه در issue عمومی.

### مجوز

کد اصلی پروژه به‌صورت **دوگانه** و به انتخاب شما تحت یکی از دو مجوز زیر است:

- **Apache License 2.0** (فایل [`LICENSE`](LICENSE))؛ یا
- **GNU General Public License، نسخهٔ ۲ یا بالاتر** (فایل
  [`LICENSE-GPL-2.0.txt`](LICENSE-GPL-2.0.txt)).

گزینهٔ GPL برای سازگاری با وردپرس ارائه شده است؛ قالب‌ها و افزونه‌های وردپرس آثار
مشتق از وردپرس (GPL-2.0-or-later) محسوب می‌شوند. Apache-2.0 با GPLv3 سازگار است
اما با GPLv2 نه؛ گزینهٔ GPL توزیع وردپرسی را سازگار نگه می‌دارد.

اجزای شخص ثالث و دارایی‌های آن‌ها با این مجوز بازمجوزدهی نمی‌شوند. فایل
[`THIRD-PARTY-NOTICES.md`](THIRD-PARTY-NOTICES.md) و [`NOTICE`](NOTICE) را ببینید.
دارایی‌هایی که منشأ آن‌ها تأیید نشده است تا زمان تأیید مالک، از مجوز پروژه مستثنا
هستند.

این مستند یک ارزیابی فنی مجوز است، نه مشاورهٔ حقوقی.

### محدودیت‌های شناخته‌شده

- WP-Cron وابسته به ترافیک است؛ برای اعلان‌ها و نگهداری قابل‌اعتماد، کران سیستمی
  توصیه می‌شود.
- فونت وزیرمتن در زمان اجرا از Google Fonts بارگذاری می‌شود؛ فایل فونت در مخزن
  نیست.
- تصاویر نمایشی اساتید و اسکرین‌شات قالب، جایگزین‌پذیر و با منشأ تأییدنشده هستند؛
  پیش از توزیع با دارایی دارای مجوز جایگزین شوند.
- اعتبارنامه‌های ذخیره‌شدهٔ سرویس‌ها توسط کاربران دارای `edit_theme_options` قابل
  تغییر هستند. این رفتار پیش‌ازاین وجود داشته و جدا از سیاست مقصد خروجی است؛ این
  قابلیت را به کارکنان مورد اعتماد محدود کنید.
- مجموعهٔ تست سیاست مقصد خروجی یک هارنس سطح برنامه با توابع جایگزین وردپرس است و
  جای تست کامل زمان اجرا/TLS/DNS وردپرس را نمی‌گیرد.
