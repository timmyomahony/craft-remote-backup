(function($) {
  Craft.BackupUtility = Garnish.Base.extend({
    $trigger: null,
    $form: null,

    init: function(formId, reload) {
      this.reload = reload;
      this.$form = $("#" + formId);
      this.$trigger = $("input.submit", this.$form);
      this.$status = $(".utility-status", this.$form);

      this.addListener(this.$form, "submit", "onSubmit");
    },

    onSubmit: function(ev) {
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
            opacity: 1
          },
          {
            complete: $.proxy(function() {
              var postData = Garnish.getPostData(this.$form),
                params = Craft.expandPostArray(postData);

              var data = this.$form.serializeArray().reduce(
                function(obj, item) {
                  obj[item.name] = item.value;
                  return obj;
                },
                {
                  caches: params.caches
                }
              );

              Craft.postActionRequest(
                params.action,
                data,
                $.proxy(function(response, textStatus) {
                  if (response && response.error) {
                    alert(response.error);
                  }

                  this.updateProgressBar();

                  setTimeout($.proxy(this, "onComplete", response), 300);
                }, this),
                {
                  complete: $.noop
                }
              );
            }, this)
          }
        );

        if (this.$allDone) {
          this.$allDone.css("opacity", 0);
        }

        this.$trigger.addClass("disabled");
        this.$trigger.trigger("blur");
      }
    },

    updateProgressBar: function() {
      var width = 100;
      this.progressBar.setProgressPercentage(width);
    },

    onComplete: function(response) {
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
          complete: $.proxy(function() {
            this.$allDone.velocity({ opacity: 1 }, { duration: "fast" });
            this.$trigger.removeClass("disabled");
            this.$trigger.trigger("focus");
          }, this)
        }
      );

      if (this.reload) {
        window.location.reload();
      }
    }
  });
})(jQuery);
