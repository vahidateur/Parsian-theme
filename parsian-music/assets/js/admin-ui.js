/**
 * Parsian admin UI: tabs, repeatable rows and media pickers.
 */
(function ($) {
  'use strict';

  var faDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

  function toFa(value) {
    return String(value).replace(/\d/g, function (digit) {
      return faDigits[Number(digit)];
    });
  }

  function syncIconPreview($scope) {
    $scope.find('[data-pm-icon-select]').each(function () {
      var $select = $(this);
      var $preview = $select.closest('.pm-icon-field').find('[data-pm-icon-preview] use');
      $preview.attr('href', '#i-' + $select.val());
    });
  }

  function renumber($rows) {
    $rows.find('[data-pm-row]').each(function (index) {
      $(this).find('.pm-row__num').text('ردیف ' + toFa(index + 1));
    });
  }

  function nextIndex($rows) {
    var max = -1;
    $rows.find('[name]').each(function () {
      var match = this.name.match(/\[(\d+)\]\[/);
      if (match) {
        max = Math.max(max, parseInt(match[1], 10));
      }
    });
    return max + 1;
  }

  function activateTab(key) {
    var $tabs = $('[data-pm-tab]');
    var $panels = $('[data-pm-panel]');
    var $savebar = $('[data-pm-savebar]');

    if (!$tabs.filter('[data-pm-tab="' + key + '"]').length) {
      key = $tabs.first().attr('data-pm-tab');
    }

    $tabs.removeClass('is-active').filter('[data-pm-tab="' + key + '"]').addClass('is-active');
    $panels.attr('hidden', true).filter('[data-pm-panel="' + key + '"]').removeAttr('hidden');
    $savebar.toggle(key !== 'tools');

    try {
      window.localStorage.setItem('pmSettingsTab', key);
    } catch (error) {
      // Storage can be unavailable in private modes; tabs still work.
    }
  }

  $(function () {
    var $tabs = $('[data-pm-tab]');
    if (!$tabs.length) {
      return;
    }

    var initial = $tabs.first().attr('data-pm-tab');
    try {
      var stored = window.localStorage.getItem('pmSettingsTab');
      if (stored && $tabs.filter('[data-pm-tab="' + stored + '"]').length) {
        initial = stored;
      }
    } catch (error) {
      // Ignore storage errors.
    }
    try {
      var requested = new URLSearchParams(window.location.search).get('pm_tab');
      if (requested && $tabs.filter('[data-pm-tab="' + requested + '"]').length) {
        initial = requested;
      }
    } catch (error) {
      // Ignore URL parsing errors.
    }
    activateTab(initial);

    $tabs.on('click', function () {
      activateTab($(this).attr('data-pm-tab'));
    });

    $(document).on('click', '[data-pm-add-row]', function () {
      var $rows = $(this).closest('[data-pm-rows]');
      var $body = $rows.find('[data-pm-rows-body]');
      var template = $rows.find('[data-pm-row-template]').html();

      if (!template) {
        return;
      }

      var index = nextIndex($rows);
      var $row = $(template.replace(/__INDEX__/g, index));
      $body.append($row);
      renumber($rows);
      syncIconPreview($row);
      $row.find('input, select, textarea').first().trigger('focus');
    });

    $(document).on('click', '[data-pm-row-remove]', function () {
      var $rows = $(this).closest('[data-pm-rows]');
      $(this).closest('[data-pm-row]').remove();
      renumber($rows);
    });

    $(document).on('click', '[data-pm-row-up]', function () {
      var $row = $(this).closest('[data-pm-row]');
      var $previous = $row.prev('[data-pm-row]');
      if ($previous.length) {
        $row.insertBefore($previous);
        renumber($row.closest('[data-pm-rows]'));
      }
    });

    $(document).on('click', '[data-pm-row-down]', function () {
      var $row = $(this).closest('[data-pm-row]');
      var $next = $row.next('[data-pm-row]');
      if ($next.length) {
        $row.insertAfter($next);
        renumber($row.closest('[data-pm-rows]'));
      }
    });

    $(document).on('change', '[data-pm-icon-select]', function () {
      syncIconPreview($(this).closest('.pm-icon-field'));
    });

    $(document).on('click', '[data-pm-media-select]', function (event) {
      event.preventDefault();

      var $wrap = $(this).closest('[data-pm-media]');
      var $input = $wrap.find('.pm-media__input');
      var $preview = $wrap.find('[data-pm-media-preview]');

      if (!window.wp || !window.wp.media) {
        return;
      }

      var frame = window.wp.media({
        title: 'انتخاب تصویر',
        button: { text: 'استفاده از این تصویر' },
        multiple: false
      });

      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        $input.val(attachment.url);
        $preview.empty().append(
          $('<img>').attr('src', attachment.url).attr('alt', '')
        );
      });

      frame.open();
    });

    $(document).on('click', '[data-pm-media-clear]', function (event) {
      event.preventDefault();
      var $wrap = $(this).closest('[data-pm-media]');
      $wrap.find('.pm-media__input').val('');
      $wrap.find('[data-pm-media-preview]').empty();
    });

    syncIconPreview($(document));
  });
})(jQuery);
