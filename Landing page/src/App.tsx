import { useEffect, useState } from "react";
import "../wp-theme/parsian-music/assets/css/main.css";
import spriteHtml from "../wp-theme/parsian-music/assets/sprite.svg.html?raw";

import hero from "../wp-theme/parsian-music/assets/images/hero.jpg";
import hall from "../wp-theme/parsian-music/assets/images/hall.jpg";
import conductor from "../wp-theme/parsian-music/assets/images/conductor.jpg";
import student from "../wp-theme/parsian-music/assets/images/student.jpg";
import deptStrings from "../wp-theme/parsian-music/assets/images/dept-strings.jpg";
import deptPiano from "../wp-theme/parsian-music/assets/images/dept-piano.jpg";
import deptPersian from "../wp-theme/parsian-music/assets/images/dept-persian.jpg";
import deptTheory from "../wp-theme/parsian-music/assets/images/dept-theory.jpg";
import deptVocal from "../wp-theme/parsian-music/assets/images/dept-vocal.jpg";
import deptKids from "../wp-theme/parsian-music/assets/images/dept-kids.jpg";
import t1 from "../wp-theme/parsian-music/assets/images/teacher-1.jpg";
import t2 from "../wp-theme/parsian-music/assets/images/teacher-2.jpg";
import t3 from "../wp-theme/parsian-music/assets/images/teacher-3.jpg";
import t4 from "../wp-theme/parsian-music/assets/images/teacher-4.jpg";
import t5 from "../wp-theme/parsian-music/assets/images/teacher-5.jpg";
import t7 from "../wp-theme/parsian-music/assets/images/teacher-7.jpg";

function Icon({ n, className = "" }: { n: string; className?: string }) {
  return (
    <svg className={`ico ${className}`} aria-hidden="true">
      <use href={`#i-${n}`} />
    </svg>
  );
}

const navItems = [
  ["خانه", "#home"],
  ["دوره‌ها", "#courses"],
  ["اساتید", "#teachers"],
  ["مسترکلاس‌ها", "#masterclass"],
  ["پذیرش و کنکور", "#faq"],
  ["درباره ما", "#stats"],
  ["ارتباط با ما", "#contact"],
];

const features: [string, string, string][] = [
  ["laurel", "با سابقه تربیت", "هنرمندان موفق"],
  ["award", "آمادگی برای", "اجرا، آزمون و کنکور"],
  ["wave", "فضا و تجهیزات", "استاندارد حرفه‌ای"],
  ["users", "اساتید دانشگاهی", "و بین‌المللی"],
  ["cap", "مسیر آکادمیک", "تا مقاطع عالی"],
];

const depts: [string, string, string][] = [
  ["ویولن و", "سازهای زهی", deptStrings],
  ["پیانو", "", deptPiano],
  ["موسیقی", "ایرانی", deptPersian],
  ["آهنگسازی", "و تئوری", deptTheory],
  ["صداسازی و", "آواز", deptVocal],
  ["موسیقی کودک", "(متد ارف)", deptKids],
];

const stats = [
  ["+۱۵", "سال تجربه تخصصی"],
  ["+۳۰۰۰", "هنرجوی فعال"],
  ["+۱۲۰", "اجرای صحنه‌ای و کنسرت"],
  ["%۹۰", "رضایت هنرجویان"],
];

const filters: [string, string][] = [
  ["all", "همه"],
  ["strings", "سازهای زهی"],
  ["piano", "پیانو"],
  ["persian", "موسیقی ایرانی"],
  ["theory", "تئوری و ارشد"],
  ["vocal", "آواز"],
  ["kids", "موسیقی کودک"],
];

const teachers: [string, string, string, string, string][] = [
  ["دکتر آرش شایگان", "ویولن", "PhD", "strings", conductor],
  ["استاد پیمان آزاد", "موسیقی ایرانی", "DMA", "persian", t3],
  ["دکتر لیلا فرهمند", "پیانو", "PhD", "piano", t2],
  ["دکتر مهدی رستگار", "آهنگسازی", "PhD", "theory", t1],
  ["دکتر سیاوش نادری", "آهنگسازی", "MA", "theory", t7],
  ["استاد نازنین کمالی", "موسیقی کودک", "MA", "kids", t4],
];

const faqs = [
  ["آیا شروع موسیقی در بزرگسالی امکان‌پذیر است؟", "بله. برای بزرگسالان کلاس‌ها و برنامه‌های آموزشی ویژه با سرعت و روش متناسب با شرایط شما طراحی شده است."],
  ["آیا تعیین سطح رایگان است؟", "بله، جلسه تعیین سطح و مشاوره اولیه برای تمام متقاضیان کاملاً رایگان برگزار می‌شود."],
  ["برای آمادگی کنکور کارشناسی ارشد چه دوره‌هایی دارید؟", "دوره‌های تخصصی تئوری، سلفژ، ریتم، آنالیز و اجرای تخصصی ساز با اساتید باتجربه کنکور."],
];

const slides = [
  { src: hall, alt: "سالن اجرا" },
  { src: hero, alt: "کنسرواتوار پارسیان" },
];

export default function App() {
  const [menu, setMenu] = useState(false);
  const [filter, setFilter] = useState("all");
  const [slide, setSlide] = useState(0);
  const [type, setType] = useState("inperson");
  const [sent, setSent] = useState(false);

  useEffect(() => {
    document.body.classList.add("pm-body", "home");
  }, []);

  useEffect(() => {
    const id = setInterval(() => setSlide((s) => (s + 1) % slides.length), 5000);
    return () => clearInterval(id);
  }, [slide]);

  const go = (n: number) => setSlide((n + slides.length) % slides.length);

  return (
    <div className="pm-body">
      <div dangerouslySetInnerHTML={{ __html: spriteHtml }} />

      <header className="pm-header" id="home">
        <div className="pm-container pm-header__in">
          <a className="pm-brand" href="#home">
            <Icon n="wave" />
            <span className="pm-brand__t">
              <strong>کنسرواتوار موسیقی پارسیان</strong>
              <small>Parsian Music Conservatory</small>
            </span>
          </a>
          <nav className={`pm-nav ${menu ? "is-open" : ""}`} aria-label="منوی اصلی">
            <ul>
              {navItems.map(([l, h], i) => (
                <li key={l} className={i === 0 ? "is-active" : ""}>
                  <a href={h} onClick={() => setMenu(false)}>{l}</a>
                </li>
              ))}
            </ul>
          </nav>
          <div className="pm-actions">
            <button className="pm-search" aria-label="جستجو"><Icon n="search" /></button>
            <a className="pm-login" href="#"><Icon n="user" /> ورود / ثبت‌نام</a>
            <a className="pm-consult" href="#contact"><Icon n="calendar" /> رزرو مشاوره</a>
            <button className="pm-burger" onClick={() => setMenu(!menu)} aria-label="منو"><Icon n="menu" /></button>
          </div>
        </div>
      </header>

      <main>
        <section className="pm-hero">
          <img className="pm-hero__bg" src={hero} alt="نوازنده ویولن‌سل" />
          <div className="pm-hero__shade" />
          <div className="pm-container pm-hero__in">
            <div className="pm-hero__content">
              <div className="pm-eyebrow">آموزش آکادمیک موسیقی با رویکرد بین‌المللی</div>
              <h1>
                <span>مسیر حرفه‌ای</span>
                <span><b className="pm-gold">موسیقی</b> از اینجا آغاز می‌شود</span>
              </h1>
              <p>در کنسرواتوار موسیقی پارسیان، با اساتید برجسته، برنامه‌های آموزشی استاندارد و فضای تخصصی، استعداد خود را به اجرای حرفه‌ای تبدیل کنید.</p>
              <div className="pm-hero__btns">
                <a className="pm-btn pm-btn--gold" href="#contact">شروع مسیر هنری من <Icon n="arrow-left" /></a>
                <a className="pm-btn pm-btn--ghost" href="#masterclass"><Icon n="play-circle" /> معرفی کنسرواتوار</a>
              </div>
            </div>
          </div>
        </section>

        <section className="pm-features">
          <div className="pm-container">
            <ul className="pm-features__list">
              {features.map(([ic, a, b]) => (
                <li key={a}>
                  <Icon n={ic} />
                  <span>{a}<br />{b}</span>
                </li>
              ))}
            </ul>
          </div>
        </section>

        <section className="pm-section" id="courses">
          <div className="pm-container">
            <div className="pm-title"><h2>دپارتمان‌های آموزشی</h2></div>
            <div className="pm-depts">
              {depts.map(([a, b, img]) => (
                <a className="pm-dept" href="#" key={a} onClick={(e) => e.preventDefault()}>
                  <img src={img} alt={`${a} ${b}`} loading="lazy" />
                  <span className="pm-dept__t">{a}{b && <small>{b}</small>}</span>
                  <Icon n="arrow-left" />
                </a>
              ))}
            </div>
          </div>
        </section>

        <section className="pm-section" id="masterclass">
          <div className="pm-container pm-feature-row">
            <div className="pm-slider">
              {slides.map((s, i) => (
                <div key={i} className={`pm-slider__slide ${i === slide ? "is-on" : ""}`}>
                  <img src={s.src} alt={s.alt} />
                </div>
              ))}
              <div className="pm-dots">
                {slides.map((_, i) => (
                  <button key={i} className={i === slide ? "is-on" : ""} onClick={() => go(i)} aria-label={`${i + 1}`} />
                ))}
              </div>
              <div className="pm-slider__nav">
                <button className="pm-circle-btn" onClick={() => go(slide - 1)} aria-label="قبلی"><Icon n="chev-right" /></button>
                <button className="pm-circle-btn" onClick={() => go(slide + 1)} aria-label="بعدی"><Icon n="chev-left" /></button>
              </div>
            </div>

            <div className="pm-master">
              <img src={conductor} alt="رهبر ارکستر" />
              <div className="pm-master__body">
                <div className="pm-master__tag">FEATURED MASTERCLASS</div>
                <h3>مستر کلاس ویژه<br />تفسیر و اجرای آثار کلاسیک</h3>
                <p>با حضور دکتر آرش شایگان<br />(دکتری نوازندگی ویولن)</p>
                <a className="pm-btn pm-btn--gold" href="#contact">مشاهده و ثبت‌نام <Icon n="arrow-left" /></a>
              </div>
              <button className="pm-master__play" aria-label="پخش"><Icon n="play" /></button>
            </div>
          </div>
        </section>

        <section className="pm-section" id="stats">
          <div className="pm-container">
            <div className="pm-stats">
              <Icon n="laurel" className="pm-laurel pm-laurel--r" />
              {stats.map(([n, l]) => (
                <div key={l}><strong dir="ltr">{n}</strong><span>{l}</span></div>
              ))}
              <Icon n="laurel" className="pm-laurel pm-laurel--l" />
            </div>
          </div>
        </section>

        <section className="pm-section" id="teachers">
          <div className="pm-container">
            <div className="pm-title"><h2>اساتید برجسته کنسرواتوار</h2></div>
            <div className="pm-chips">
              {filters.map(([k, l]) => (
                <button key={k} className={`pm-chip ${filter === k ? "is-on" : ""}`} onClick={() => setFilter(k)}>{l}</button>
              ))}
            </div>
            <div className="pm-teachers">
              {teachers.map(([name, field, deg, cat, img]) => (
                <article key={name} className={`pm-teacher ${filter !== "all" && filter !== cat ? "is-hidden" : ""}`}>
                  <img src={img} alt={name} loading="lazy" />
                  <div className="pm-teacher__body">
                    <h3>{name}</h3>
                    <div className="pm-teacher__row">
                      <span>{field} <bdi>{deg}</bdi></span>
                      <button className="pm-circle-btn" aria-label="پخش"><Icon n="play" /></button>
                    </div>
                  </div>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section className="pm-section">
          <div className="pm-container">
            <div className="pm-title"><h2>نمونه‌ای از مسیر پیشرفت هنرجویان</h2></div>
            <div className="pm-progress">
              <div className="pm-ba">
                <div className="pm-ba__half pm-ba__half--after"><img src={student} alt="پس از یک سال آموزش" /><span className="pm-ba__label">پس از یک سال آموزش</span></div>
                <div className="pm-ba__half pm-ba__half--before"><img src={student} alt="قبل از آموزش" /><span className="pm-ba__label">قبل از آموزش</span></div>
                <button className="pm-circle-btn pm-ba__center" aria-label="پخش"><Icon n="play" /></button>
                <button className="pm-circle-btn pm-ba__prev" aria-label="قبلی"><Icon n="chev-right" /></button>
                <button className="pm-circle-btn pm-ba__next" aria-label="بعدی"><Icon n="chev-left" /></button>
              </div>
              <div className="pm-testi">
                <span className="pm-testi__q">”</span>
                <div className="pm-testi__head">
                  <img className="pm-testi__avatar" src={t5} alt="مهرداد کیانی" />
                  <div>
                    <div className="pm-testi__name">مهرداد کیانی</div>
                    <div className="pm-testi__role">هنرجوی ویولن</div>
                    <div className="pm-stars">{[0, 1, 2, 3, 4].map((i) => <Icon key={i} n="star" />)}</div>
                  </div>
                </div>
                <p>در مدت یک سال، از سطح مبتدی به اجرای صحنه‌ای رسیدم. فضای حرفه‌ای و حمایت اساتید بزرگ‌ترین نقطه قوت پارسیان است.</p>
              </div>
            </div>
          </div>
        </section>

        <section className="pm-section" id="contact">
          <div className="pm-container">
            <div className="pm-book" id="faq">
              <div className="pm-book__col pm-faq">
                <h3><Icon n="help" /> سوالات پرتکرار</h3>
                {faqs.map(([q, a]) => (
                  <details key={q}>
                    <summary><Icon n="chev-down" /><span>{q}</span></summary>
                    <p>{a}</p>
                  </details>
                ))}
              </div>

              <form
                className="pm-book__col pm-form"
                onSubmit={(e) => {
                  e.preventDefault();
                  setSent(true);
                  (e.target as HTMLFormElement).reset();
                }}
              >
                <h3>رزرو مشاوره و تعیین سطح</h3>
                {sent && <div className="pm-notice">درخواست شما ثبت شد. به زودی با شما تماس می‌گیریم.</div>}
                <div className="pm-tabs">
                  <button type="button" className={`pm-tab ${type === "inperson" ? "is-on" : ""}`} onClick={() => setType("inperson")}>
                    {type === "inperson" && <Icon n="radio" />} مشاوره حضوری
                  </button>
                  <button type="button" className={`pm-tab ${type === "phone" ? "is-on" : ""}`} onClick={() => setType("phone")}>
                    {type === "phone" && <Icon n="radio" />} مشاوره تلفنی
                  </button>
                </div>
                <div className="pm-field"><Icon n="user" /><input type="text" placeholder="نام و نام خانوادگی" required /></div>
                <div className="pm-field"><Icon n="mobile" /><input type="tel" placeholder="شماره موبایل" required /></div>
                <div className="pm-field">
                  <Icon n="music" />
                  <select required defaultValue="">
                    <option value="">ساز مورد علاقه</option>
                    {["ویولن", "ویولن‌سل", "پیانو", "گیتار", "تار و سه‌تار", "سنتور", "آواز", "موسیقی کودک"].map((o) => <option key={o}>{o}</option>)}
                  </select>
                  <Icon n="chev-down" className="pm-field__chev" />
                </div>
                <button type="submit" className="pm-btn pm-btn--gold"><Icon n="calendar" /> ثبت درخواست مشاوره و تعیین سطح</button>
              </form>

              <div className="pm-book__col">
                <div className="pm-assist">
                  <div className="pm-assist__t">
                    <h4>دستیار هوشمند<br />پارسیان</h4>
                    <p>پاسخ فوری به<br />سوالات شما</p>
                    <a className="pm-circle-btn" href="#contact" aria-label="شروع گفتگو"><Icon n="arrow-left" /></a>
                  </div>
                  <div className="pm-assist__orb"><Icon n="wave" /></div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </main>

      <footer className="pm-footer">
        <div className="pm-container pm-footer__in">
          <a className="pm-brand" href="#home">
            <Icon n="music" />
            <span className="pm-brand__t">
              <strong>کنسرواتوار موسیقی پارسیان</strong>
              <small>Parsian Music Conservatory</small>
            </span>
          </a>
          <nav className="pm-nav" aria-label="منوی فوتر">
            <ul>
              {navItems.map(([l, h], i) => (
                <li key={l} className={i === 0 ? "is-active" : ""}><a href={h}>{l}</a></li>
              ))}
            </ul>
          </nav>
          <div className="pm-social">
            <a href="#" aria-label="Instagram"><Icon n="instagram" /></a>
            <a href="#" aria-label="YouTube"><Icon n="youtube" /></a>
            <a href="#" aria-label="Telegram"><Icon n="telegram" /></a>
          </div>
        </div>
      </footer>
    </div>
  );
}
