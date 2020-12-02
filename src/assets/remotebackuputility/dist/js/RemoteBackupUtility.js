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
      this.$showAllRow = $(".show-all-row", this.$tbody);
      this.$submit = $("input.submit", this.$form);
      this.$loadingOverlay = $(".rb-utilities-overlay", this.$element);

      this.listActionUrl = this.$table.attr("data-list-action");
      this.pushActionUrl = this.$table.attr("data-push-action");

      this.csrfToken = this.$form.find('input[name="CRAFT_CSRF_TOKEN"]').val();

      this.$form.on("submit", this.push.bind(this));

      // Show all rows
      this.$showAllRow.find("a").on(
        "click",
        function (e) {
          this.$showAllRow.hide();
          this.$table.removeClass("rb-utilities-table--collapsed");
          e.preventDefault();
        }.bind(this)
      );

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

    updateTable: function (options, error) {
      if (error) {
        this.showTableErrors();
        return false;
      }

      if (options.length <= 0) {
        this.showTableNoResults();
        return false;
      }

      // Backups are ordered newest to oldest ([0] = most recent) but we
      // prepend them instead of append them to make it easier to style
      for (var i = options.length - 1; i >= 0; i--) {
        var $row = this.$tbody
          .find(".template-row")
          .clone()
          .removeClass("template-row default-row");

        var $td = $row.find("td:first");
        $td.text(options[i].label);
        $td.attr("title", options[i].value);
        $td.attr("data-filename", options[i].value);

        if (i === 0) {
          $td.append($("<span>").text("latest"));
        } else {
          $row.removeClass("first");
        }

        this.$tbody.prepend($row);
      }

      if (options.length > 3) {
        this.$showAllRow.show();
      }

      return true;
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
            this.updateTable(response["options"]);
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
