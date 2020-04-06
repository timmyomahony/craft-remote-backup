(function ($) {
  Craft.RemoteBackupSettings = Garnish.Base.extend({
    init: function (formId) {
      this.$form = $("#" + formId);
    },
  });
})(jQuery);
