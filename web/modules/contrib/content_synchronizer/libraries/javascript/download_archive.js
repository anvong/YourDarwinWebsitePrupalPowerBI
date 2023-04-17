(function (drupalSettings, $) {
  'use strict';
  Drupal.behaviors.content_synchronizer_download_archive = {
    attach: function (context, settings) {
      const param = 'cs_archive=';
      if (window.location.hash.indexOf(param) > -1) {
        const path = decodeURIComponent(window.location.hash.split(param)[1]);
        let location = drupalSettings.path.baseUrl;
        location += location[location.length - 1] === '/' ? '':'/';
        location += drupalSettings.content_synchronizer.download_archive_path + '?' + param + path;
        const $iframe = $('<iframe src="' + location + '"></iframe>');
        $iframe.hide();

        $('body').append($iframe);
        window.location.hash = '';
      }
    }
  };
})(drupalSettings, jQuery);
