(function ($) {
  Craft.RemoteBackupUtility = Garnish.Base.extend({
    init: function (id) {
      this.$element = $("#" + id);
      this.$form = $("form", this.$element);
      this.$table = $("table", this.$element);
      this.$tbody = $("tbody", this.$table);
      this.$trigger = $("input.submit", this.$form);
      this.$status = $(".utility-status", this.$form);

      this.listActionUrl = this.$table.find("input[name='action']").val();
      this.createActionUrl = this.$form.find('input[name="action"]').val();
      this.addListener(this.$form, "submit", "onSubmit");

      this.getBackups();
    },

    getBackups: function () {
      this.clearTable();
      this.showTableLoading();
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
          this.hideTableLoading();
        }.bind(this),
        error: function (error) {
          this.updateTable([], true);
          Craft.cp.displayError("Error fetching backups");
        }.bind(this),
      });
    },

    clearTable: function () {
      this.$tbody
        .find("tr")
        .filter(function (i, row) {
          return !$(row).hasClass("default-row");
        })
        .remove();
    },

    hideTableLoading: function () {
      this.$tbody.find(".loading-row").hide();
    },

    showTableLoading: function () {
      this.$tbody.find(".loading-row").show();
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
          var $template = this.$tbody.find(".template-row").clone();
          $template.removeClass("template-row default-row");
          if (i > 0) {
            $template.removeClass("first");
          }
          $template.find("td").text(backups[i].label);
          $template.find("td").attr("title", backups[i].value);
          this.$tbody.append($template);
        }
      } else {
        this.showTableNoResults();
      }
    },

    onSubmit: function (ev) {
      ev.preventDefault();

      if (!this.$trigger.hasClass("disabled")) {
        if (!this.progressBar) {
          this.progressBar = new Craft.ProgressBar(this.$status);
        } else {
          this.progressBar.resetProgressBar();
        }

        this.progressBar.$progressBar.removeClass("hidden");

        this.progressBar.$progressBar.velocity("stop").velocity(
          {
            opacity: 1,
          },
          {
            complete: $.proxy(function () {
              var postData = Garnish.getPostData(this.$element),
                params = Craft.expandPostArray(postData);

              var data = this.$element.serializeArray().reduce(
                function (obj, item) {
                  obj[item.name] = item.value;
                  return obj;
                },
                {
                  caches: params.caches,
                }
              );

              Craft.postActionRequest(
                params.action,
                data,
                $.proxy(function (response, textStatus) {
                  if (response && response.error) {
                    alert(response.error);
                  }

                  this.updateProgressBar();

                  setTimeout($.proxy(this, "onComplete", response), 300);
                }, this),
                {
                  complete: $.noop,
                }
              );
            }, this),
          }
        );

        if (this.$allDone) {
          this.$allDone.css("opacity", 0);
        }

        this.$trigger.addClass("disabled");
        this.$trigger.trigger("blur");
      }
    },

    updateProgressBar: function () {
      var width = 100;
      this.progressBar.setProgressPercentage(width);
    },

    onComplete: function (response) {
      if (!this.$allDone) {
        this.$allDone = $('<div class="alldone" data-icon="done" />').appendTo(
          this.$status
        );
        this.$allDone.css("opacity", 0);
      }

      this.progressBar.$progressBar.velocity(
        { opacity: 0 },
        {
          duration: "fast",
          complete: $.proxy(function () {
            this.$allDone.velocity({ opacity: 1 }, { duration: "fast" });
            this.$trigger.removeClass("disabled");
            this.$trigger.trigger("focus");
          }, this),
        }
      );

      window.location.reload();
    },
  });
})(jQuery);
