(function ($) {
  /** global: Craft */
  /** global: Garnish */
  Craft.RemoteBackupSettings = Garnish.Base.extend({
    init: function (formId) {
      this.$form = $("#" + formId);
      this.$providerSections = $(".provider", this.$form);
      this.$providerSelect = $("#settings-cloudProvider", this.$form);
      this.$testProviderButton = $("#settings-rc-test-provider", this.$form);

      this.$providerSelect.on('change', function(e) {
        this.showProvider($(e.target).val());
      }.bind(this));

      // Test the provider on-click
      this.$testProviderButton.on('click', this.testProvider.bind(this));

      // On load
      this.showProvider(this.$providerSelect.val());
    },

    showProvider: function(slug) {
      this.$providerSections.hide();
      this.$providerSections.filter(function(i, el) {
        return $(el).hasClass('provider-' + slug);
      }).show();
    },

    testProvider: function(e) {

      var TestFailedModal = Garnish.Modal.extend({
        desiredWidth: 1200,
        hideOnEsc: true,
        hideOnShadeClick: true,
        init: function (response) {
          var $container = $(
            '<div class="settings-rc-test-provider-modal modal fitted"></div>'
          )
          var $body = $('<div class="body"><pre class="settings-rc-test-provider-modal-pre">' + response.message + '</pre></div>')
          var $buttons = $('<div class="buttons right"/>').appendTo($body);
          var $closeBtn = $('<button/>', {
            type: 'button',
            class: 'btn submit',
            text: Craft.t('app', 'Close'),
          })

          $closeBtn.appendTo($buttons);
          $buttons.appendTo($body);
          $body.appendTo($container);
          $container.appendTo(Garnish.$bod);

          this.addListener($closeBtn, 'click', 'hide');
          this.base($container);
        }
      });

      this.$testProviderButton.attr("disabled", true).css("opacity", 0.5);

      var pluginHandle = this.$testProviderButton.attr("data-plugin-handle");
      var url = Craft.getActionUrl(pluginHandle + "/" + pluginHandle + "/test-provider");
      $.get({
        url: url,
        dataType: "json"
      }, function() {
        Craft.cp.displayNotice(Craft.t('remote-backup', 'Test succeeded'));
      }.bind(this))
        .fail(function(xhr) {
          Craft.cp.displayError(Craft.t('remote-backup', 'Test failed'));
          new TestFailedModal(xhr.responseJSON);
        }.bind(this))
        .always(function() {
          this.$testProviderButton.attr("disabled", false).css("opacity", 1);
        }.bind(this))

      e.preventDefault();
    }
  });
})(jQuery);
