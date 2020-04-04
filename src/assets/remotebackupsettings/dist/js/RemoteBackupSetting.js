(function ($) {
  Craft.RemoteBackupSettings = Garnish.Base.extend({
    init: function (formId) {
      this.$form = $("#" + formId);
      this.$pruneLightswitch = $("#settings-prune");
      this.addListener(this.$pruneLightswitch, "change", "updatePruneSettings");
    },

    updatePruneSettings: function (ev) {
      var $lightswitch = this.$pruneLightswitch.data("lightswitch");
      var $settings = $(document).find(".remote-backup-prune-settings");
      if ($lightswitch.on) {
        $settings.show();
      } else {
        $settings.hide();
      }
    },
  });
})(jQuery);
