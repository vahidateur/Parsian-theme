/**
 * Parsian Music Academy – global interactions.
 */
(function () {
  'use strict';

  var faDigits = '۰۱۲۳۴۵۶۷۸۹';

  function $(selector, context) {
    return (context || document).querySelector(selector);
  }

  function $$(selector, context) {
    return Array.prototype.slice.call((context || document).querySelectorAll(selector));
  }

  function toEn(value) {
    return String(value)
      .replace(/[۰-۹]/g, function (digit) { return faDigits.indexOf(digit); })
      .replace(/[٠-٩]/g, function (digit) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(digit); });
  }

  function toFa(value) {
    return String(value).replace(/\d/g, function (digit) { return faDigits[digit]; });
  }

  /* Mobile menu ---------------------------------------------------------- */
  var burger = $('[data-pm-burger]');
  var nav = document.getElementById('pm-primary-nav');
  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    $$('a', nav).forEach(function (link) {
      link.addEventListener('click', function () {
        nav.classList.remove('is-open');
        burger.setAttribute('aria-expanded', 'false');
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        burger.setAttribute('aria-expanded', 'false');
        burger.focus();
      }
    });

    document.addEventListener('click', function (event) {
      if (!nav.classList.contains('is-open')) return;
      if (nav.contains(event.target) || burger.contains(event.target)) return;
      nav.classList.remove('is-open');
      burger.setAttribute('aria-expanded', 'false');
    });
  }

  /* Search panel --------------------------------------------------------- */
  var searchToggle = $('[data-pm-search-toggle]');
  var searchPanel = document.getElementById('pm-search-panel');
  if (searchToggle && searchPanel) {
    searchToggle.addEventListener('click', function () {
      var open = searchPanel.hidden;
      searchPanel.hidden = !open;
      searchPanel.classList.toggle('is-open', open);
      searchToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        var input = $('input[type="search"]', searchPanel);
        if (input) input.focus();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !searchPanel.hidden) {
        searchPanel.hidden = true;
        searchPanel.classList.remove('is-open');
        searchToggle.setAttribute('aria-expanded', 'false');
        searchToggle.focus();
      }
    });
  }

  /* Header on scroll ----------------------------------------------------- */
  var header = $('.pm-header--overlay');
  if (header) {
    var updateHeader = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 28);
    };
    updateHeader();
    window.addEventListener('scroll', updateHeader, { passive: true });
  }

  /* Teacher filters ------------------------------------------------------ */
  $$('[data-pm-filter-root]').forEach(function (root) {
    var chips = $$('[data-pm-filter]', root);
    var cards = $$('[data-pm-cat]', root);

    chips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        var filter = chip.getAttribute('data-pm-filter');
        chips.forEach(function (item) {
          var active = item === chip;
          item.classList.toggle('is-on', active);
          item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        cards.forEach(function (card) {
          var show = filter === 'all' || card.getAttribute('data-pm-cat') === filter;
          card.classList.toggle('is-filtered-out', !show);
        });
      });
    });
  });

  /* Slider --------------------------------------------------------------- */
  $$('[data-pm-slider]').forEach(function (root) {
    var slides = $$('.pm-slider__slide', root);
    var dots = $$('.pm-dots button', root);
    var current = 0;
    var timer = null;

    if (slides.length < 2) return;

    function go(index) {
      current = (index + slides.length) % slides.length;
      slides.forEach(function (slide, i) {
        slide.classList.toggle('is-on', i === current);
      });
      dots.forEach(function (dot, i) {
        dot.classList.toggle('is-on', i === current);
      });
    }

    function start() {
      stop();
      timer = window.setInterval(function () { go(current + 1); }, 5000);
    }

    function stop() {
      if (timer) window.clearInterval(timer);
      timer = null;
    }

    dots.forEach(function (dot, i) {
      dot.addEventListener('click', function () { go(i); start(); });
    });

    var prev = $('[data-pm-prev]', root);
    var next = $('[data-pm-next]', root);
    if (prev) prev.addEventListener('click', function () { go(current - 1); start(); });
    if (next) next.addEventListener('click', function () { go(current + 1); start(); });

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    start();
  });

  /* Consultation type tabs ----------------------------------------------- */
  $$('[data-pm-tabs]').forEach(function (root) {
    var tabs = $$('[data-pm-type]', root);
    var form = root.closest('form');
    var input = form ? $('[data-pm-type-input]', form) : null;
    var panels = form ? $$('[data-pm-form-panel]', form) : [];

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var type = tab.getAttribute('data-pm-type');
        tabs.forEach(function (item) {
          var active = item === tab;
          item.classList.toggle('is-on', active);
          item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (input) input.value = type;
        panels.forEach(function (panel) {
          panel.hidden = panel.getAttribute('data-pm-form-panel') !== type;
        });
      });
    });
  });

  /* Before / after comparison -------------------------------------------- */
  $$('[data-pm-compare]').forEach(function (root) {
    var buttons = $$('[data-view]', root);
    var image = $('[data-pm-compare-image]', root);
    var before = root.getAttribute('data-before');
    var after = root.getAttribute('data-after');

    if (!image || !before || !after) return;

    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        var view = button.getAttribute('data-view');
        buttons.forEach(function (item) {
          var active = item === button;
          item.classList.toggle('is-on', active);
          item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        image.src = view === 'before' ? before : after;
        root.classList.toggle('is-before', view === 'before');
        if (image.animate) {
          image.animate([{ opacity: .55 }, { opacity: 1 }], { duration: 320 });
        }
      });
    });
  });

  /* Form validation ------------------------------------------------------ */
  $$('[data-pm-form]').forEach(function (form) {
    var name = $('[name="pm_name"]', form);
    var phone = $('[name="pm_phone"]', form);
    var instrument = $('[name="pm_instrument"]', form);

    function fieldOf(input) {
      return input ? input.closest('.pm-field') : null;
    }

    function setError(input, message) {
      var field = fieldOf(input);
      if (!field) return;
      field.classList.toggle('is-invalid', Boolean(message));
      var error = $('[data-pm-error="' + input.getAttribute('name').replace('pm_', '') + '"]', field);
      if (error) error.textContent = message || '';
    }

    if (phone) {
      phone.addEventListener('input', function () {
        phone.value = toFa(toEn(phone.value).replace(/\D/g, '').slice(0, 11));
        setError(phone, '');
      });
    }

    [name, instrument].forEach(function (input) {
      if (input) {
        input.addEventListener('input', function () { setError(input, ''); });
        input.addEventListener('change', function () { setError(input, ''); });
      }
    });

    form.addEventListener('submit', function (event) {
      var valid = true;
      var firstInvalid = null;

      var nameLength = name ? Array.from(name.value.trim()).length : 0;
      if (!name || nameLength < 3) {
        setError(name, 'نام کامل را وارد کنید.');
        valid = false;
        firstInvalid = firstInvalid || name;
      } else {
        setError(name, '');
      }

      var phoneValue = phone ? toEn(phone.value).replace(/\D/g, '') : '';
      if (!phone || !/^09\d{9}$/.test(phoneValue)) {
        setError(phone, 'شمارهٔ موبایل معتبر نیست.');
        valid = false;
        firstInvalid = firstInvalid || phone;
      } else {
        setError(phone, '');
      }

      if (!instrument || !instrument.value) {
        setError(instrument, 'ساز مورد علاقه را انتخاب کنید.');
        valid = false;
        firstInvalid = firstInvalid || instrument;
      } else {
        setError(instrument, '');
      }

      if (!valid) {
        event.preventDefault();
        if (firstInvalid) firstInvalid.focus();
        return;
      }

      var submit = $('.pm-form__submit', form);
      if (submit) {
        submit.classList.add('is-loading');
        submit.disabled = true;
      }
    });
  });
})();
