(function ($) {
  Craft.RemoteBackupSettings = Garnish.Base.extend({
    init: function (formId) {
      this.$form = $("#" + formId);

      this.$providerSections = $(".provider", this.$form);
      this.$providerSelect = $("#settings-cloudProvider", this.$form);

      this.$providerSelect.on('change', function(e) {
        this.showProvider($(e.target).val());
      }.bind(this));

      // On load
      this.showProvider(this.$providerSelect.val());
    },

    showProvider: function(slug) {
      this.$providerSections.hide();
      this.$providerSections.filter(function(i, el) {
        return $(el).hasClass('provider-' + slug);
      }).show();
    }
  });
})(jQuery);
