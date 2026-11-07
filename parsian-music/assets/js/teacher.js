/**
 * Parsian Music Academy – teacher profile interactions.
 */
(function () {
  'use strict';

  var faDigits = '۰۱۲۳۴۵۶۷۸۹';

  function toFa(value) {
    return String(value).replace(/\d/g, function (digit) { return faDigits[digit]; });
  }

  function formatTime(seconds) {
    if (!isFinite(seconds) || seconds < 0) seconds = 0;
    var minutes = Math.floor(seconds / 60);
    var rest = Math.floor(seconds % 60);
    return toFa(String(minutes).padStart(2, '0') + ':' + String(rest).padStart(2, '0'));
  }

  /* Audio player --------------------------------------------------------- */
  var audioRoot = document.querySelector('[data-pm-audio]');
  if (audioRoot) {
    var audio = audioRoot.querySelector('[data-pm-audio-element]');
    var audioButton = audioRoot.querySelector('[data-pm-audio-toggle]');
    var waveform = audioRoot.querySelector('[data-pm-waveform]');
    var audioTime = audioRoot.querySelector('[data-pm-audio-time]');
    var bars = waveform ? Array.prototype.slice.call(waveform.querySelectorAll('i')) : [];

    if (audio && audioButton && waveform && audioTime) {
      var updateBars = function () {
        var duration = audio.duration || 0;
        var progress = duration ? audio.currentTime / duration : 0;
        var played = Math.round(progress * bars.length);
        bars.forEach(function (bar, index) {
          bar.classList.toggle('is-played', index < played);
        });
      };

      var updateTime = function () {
        audioTime.textContent = formatTime(audio.currentTime) + ' / ' + formatTime(audio.duration);
      };

      audio.addEventListener('timeupdate', function () {
        updateBars();
        updateTime();
      });
      audio.addEventListener('loadedmetadata', updateTime);
      audio.addEventListener('ended', function () {
        audioButton.setAttribute('aria-pressed', 'false');
        var icon = audioButton.querySelector('use');
        if (icon) icon.setAttribute('href', '#i-play');
        updateBars();
      });

      audioButton.addEventListener('click', function () {
        if (audio.paused) {
          audio.play();
        } else {
          audio.pause();
        }
      });

      audio.addEventListener('play', function () {
        audioButton.setAttribute('aria-pressed', 'true');
        var icon = audioButton.querySelector('use');
        if (icon) icon.setAttribute('href', '#i-pause');
      });

      audio.addEventListener('pause', function () {
        audioButton.setAttribute('aria-pressed', 'false');
        var icon = audioButton.querySelector('use');
        if (icon) icon.setAttribute('href', '#i-play');
      });

      waveform.addEventListener('click', function (event) {
        if (!audio.duration) return;
        var rect = waveform.getBoundingClientRect();
        var ratio = (event.clientX - rect.left) / rect.width;
        audio.currentTime = Math.max(0, Math.min(1, ratio)) * audio.duration;
        updateBars();
        updateTime();
      });
    }
  }

  /* Video modal ---------------------------------------------------------- */
  var modal = document.getElementById('pm-teacher-video-modal');
  if (!modal) return;

  var modalBody = modal.querySelector('[data-pm-video-body]');
  var modalTitle = document.getElementById('pm-teacher-video-title');
  var closeButton = modal.querySelector('[data-pm-video-close]');
  var lastTrigger = null;

  function embedUrl(url) {
    if (!url) return '';
    try {
      var parsed = new URL(url, window.location.href);
      var host = parsed.hostname.replace(/^www\./, '');

      if (host === 'youtu.be') {
        return 'https://www.youtube.com/embed/' + parsed.pathname.replace(/^\//, '') + '?autoplay=1&rel=0';
      }
      if (host.indexOf('youtube.com') !== -1) {
        if (parsed.pathname.indexOf('/embed/') === 0) {
          return url;
        }
        var id = parsed.searchParams.get('v');
        if (id) return 'https://www.youtube.com/embed/' + id + '?autoplay=1&rel=0';
      }
      if (host.indexOf('vimeo.com') !== -1) {
        if (parsed.pathname.indexOf('/video/') === 0) return url;
        var parts = parsed.pathname.split('/').filter(Boolean);
        if (parts.length) return 'https://player.vimeo.com/video/' + parts[parts.length - 1] + '?autoplay=1';
      }
    } catch (error) {
      return url;
    }
    return url;
  }

  function isVideoFile(url) {
    return /\.(mp4|webm|ogg|ogv)(\?.*)?$/i.test(url);
  }

  function openModal(trigger) {
    var url = trigger.getAttribute('data-pm-video-url') || '';
    var title = trigger.getAttribute('data-pm-video-title') || 'ویدئوی استاد';
    lastTrigger = trigger;

    if (modalTitle) modalTitle.textContent = title;
    if (modalBody) {
      modalBody.innerHTML = '';
      if (!url) {
        var message = document.createElement('div');
        message.className = 'pm-tp-modal__message';
        message.textContent = 'ویدئوی این بخش به‌زودی در سایت منتشر می‌شود.';
        modalBody.appendChild(message);
      } else if (isVideoFile(url)) {
        var video = document.createElement('video');
        video.src = url;
        video.controls = true;
        video.autoplay = true;
        video.playsInline = true;
        modalBody.appendChild(video);
      } else {
        var iframe = document.createElement('iframe');
        iframe.src = embedUrl(url);
        iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen');
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('title', title);
        modalBody.appendChild(iframe);
      }
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (closeButton) closeButton.focus();
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (modalBody) modalBody.innerHTML = '';
    if (lastTrigger && typeof lastTrigger.focus === 'function') lastTrigger.focus();
    lastTrigger = null;
  }

  Array.prototype.slice.call(document.querySelectorAll('[data-pm-video-url]')).forEach(function (trigger) {
    trigger.addEventListener('click', function () { openModal(trigger); });
    if (trigger.tagName.toLowerCase() !== 'button') {
      trigger.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openModal(trigger);
        }
      });
    }
  });

  if (closeButton) closeButton.addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });
})();
