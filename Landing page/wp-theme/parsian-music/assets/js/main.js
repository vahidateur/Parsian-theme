(function () {
  'use strict';

  /* Mobile menu */
  var burger = document.getElementById('pm-burger');
  var nav = document.getElementById('pm-nav');
  if (burger && nav) {
    burger.addEventListener('click', function () { nav.classList.toggle('is-open'); });
  }

  /* Teachers filter */
  var chips = document.querySelectorAll('[data-filter]');
  var cards = document.querySelectorAll('[data-cat]');
  chips.forEach(function (chip) {
    chip.addEventListener('click', function () {
      var f = chip.getAttribute('data-filter');
      chips.forEach(function (c) { c.classList.toggle('is-on', c === chip); });
      cards.forEach(function (card) {
        card.classList.toggle('is-hidden', f !== 'all' && card.getAttribute('data-cat') !== f);
      });
    });
  });

  /* Hall slider */
  document.querySelectorAll('[data-slider]').forEach(function (root) {
    var slides = root.querySelectorAll('.pm-slider__slide');
    var dots = root.querySelectorAll('.pm-dots button');
    var i = 0, timer;
    function go(n) {
      i = (n + slides.length) % slides.length;
      slides.forEach(function (s, k) { s.classList.toggle('is-on', k === i); });
      dots.forEach(function (d, k) { d.classList.toggle('is-on', k === i); });
    }
    function auto() { clearInterval(timer); timer = setInterval(function () { go(i + 1); }, 5000); }
    var prev = root.querySelector('[data-prev]');
    var next = root.querySelector('[data-next]');
    if (prev) prev.addEventListener('click', function () { go(i - 1); auto(); });
    if (next) next.addEventListener('click', function () { go(i + 1); auto(); });
    dots.forEach(function (d, k) { d.addEventListener('click', function () { go(k); auto(); }); });
    auto();
  });

  /* Consultation tabs */
  var tabs = document.querySelectorAll('[data-type]');
  var typeInput = document.getElementById('pm-type');
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.toggle('is-on', x === t); });
      if (typeInput) typeInput.value = t.getAttribute('data-type');
    });
  });
})();
