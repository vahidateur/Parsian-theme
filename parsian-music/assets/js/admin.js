/**
 * Teacher meta box media picker.
 */
(function ($) {
  'use strict';

  $(document).on('click', '[data-pm-media]', function (event) {
    event.preventDefault();

    var button = $(this);
    var input = $('#' + button.attr('data-target'));
    var wrapper = button.closest('.pm-media-field');
    var preview = wrapper.find('.pm-media-field__preview');

    if (!input.length || !window.wp || !window.wp.media) return;

    var frame = window.wp.media({
      title: 'انتخاب تصویر',
      button: { text: 'استفاده از این تصویر' },
      multiple: false
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      input.val(attachment.url);
      preview.html('<img src="' + attachment.url + '" alt="" />');
    });

    frame.open();
  });

  $(document).on('click', '[data-pm-media-clear]', function (event) {
    event.preventDefault();
    var button = $(this);
    var wrapper = button.closest('.pm-media-field');
    wrapper.find('input').val('');
    wrapper.find('.pm-media-field__preview').empty();
  });
})(jQuery);
