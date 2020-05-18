(function ($) {
  Craft.RemoteBackupUtility = Garnish.Base.extend({
    init: function (id) {
      this.$element = $("#" + id);

      if (this.$element.length <= 0) {
        return;
      }

      this.$form = $("form", this.$element);
      this.$table = $("table", this.$element);
      this.$tbody = $("tbody", this.$table);
      this.$submit = $("input.submit", this.$form);
      this.$loadingOverlay = $(".rb-utilities-overlay", this.$element);

      this.listActionUrl = this.$table.attr("data-list-action");
      this.pushActionUrl = this.$table.attr("data-push-action");

      this.csrfToken = this.$form.find('input[name="CRAFT_CSRF_TOKEN"]').val();

      this.$form.on("submit", this.push.bind(this));
      this.list();
    },

    clearTable: function () {
      this.$tbody
        .find("tr")
        .filter(function (i, row) {
          return !$(row).hasClass("default-row");
        })
        .remove();
    },

    showLoading: function () {
      this.$loadingOverlay.fadeIn();
    },

    hideLoading: function () {
      this.$loadingOverlay.fadeOut();
    },

    hideTableNoResults: function () {
      this.$tbody.find(".no-results-row").hide();
    },

    showTableNoResults: function () {
      this.$tbody.find(".no-results-row").show();
    },

    hideTableErrors: function () {
      this.$tbody.find(".errors-row").hide();
    },

    showTableErrors: function () {
      this.$tbody.find(".errors-row").show();
    },

    updateTable: function (backups, error) {
      if (error) {
        this.showTableErrors();
      } else if (backups.length > 0) {
        for (var i = 0; i < backups.length; i++) {
          var $row = this.$tbody.find(".template-row").clone();
          var $td = $row.find("td");
          $row.removeClass("template-row default-row");
          if (i > 0) {
            $row.removeClass("first");
          }
          $td.text(backups[i].label);
          $td.attr("title", backups[i].title);
          $td.attr("data-filename", backups[i].value);
          if (backups.length > 1 && i === 0) {
            $td.append($("<span>").text("latest"));
          }
          this.$tbody.append($row);
        }
      } else {
        this.showTableNoResults();
      }
    },

    /**
     * Push a database/volume
     */
    push: function (ev) {
      if (ev) {
        ev.preventDefault();
      }
      this.post(this.pushActionUrl);
    },

    /**
     * Get and list database/volumes
     */
    list: function () {
      this.clearTable();
      this.showLoading();
      $.get({
        url: Craft.getActionUrl(this.listActionUrl),
        dataType: "json",
        success: function (response) {
          if (response["success"]) {
            this.updateTable(response["backups"]);
          } else {
            var message = "Error fetching backups";
            if (response["error"]) {
              message = response["error"];
            }
            this.updateTable([], message);
            Craft.cp.displayError(message);
          }
        }.bind(this),
        complete: function () {
          this.hideLoading();
        }.bind(this),
        error: function (error) {
          this.updateTable([], true);
          Craft.cp.displayError("Error fetching backups");
        }.bind(this),
      });
    },

    post: function (action, data = {}) {
      var postData = Object.assign(data, {
        CRAFT_CSRF_TOKEN: this.csrfToken,
      });
      var url = Craft.getActionUrl(action);
      this.showLoading();
      Craft.postActionRequest(
        url,
        postData,
        function (response) {
          if (response["success"]) {
            window.location.reload();
          } else {
            var message = "Error fetching backups";
            if (response["error"]) {
              message = response["error"];
            }
            this.updateTable([], message);
            Craft.cp.displayError(message);
          }
        }.bind(this)
      );
    },
  });
})(jQuery);
