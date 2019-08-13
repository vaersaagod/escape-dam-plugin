/** global: Craft */
/** global: Garnish */

(function () {

    if (!Craft || !Craft.BaseElementEditor) {
        return false;
    }

    if (!Craft.EscapeDam) {
        Craft.EscapeDam = {};
    }

    var fnLoadHud = Craft.BaseElementEditor.prototype.loadHud;
    Craft.BaseElementEditor.prototype.loadHud = function () {
        var type = this.$element.data('type');
        if (type !== 'craft\\elements\\Asset') {
            fnLoadHud.apply(this, arguments);
            return;
        }
        // Open our custom Asset modal
        this.onBeginLoading();
        var data = this.getBaseData();
        data.includeSites = this.settings.showSiteSwitcher;
        Craft.postActionRequest('escapedam/elements/get-editor-html', data, $.proxy(this, 'showHud'));
    }

    var fnReloadForm = Craft.BaseElementEditor.prototype.reloadForm;
    Craft.BaseElementEditor.prototype.reloadForm = function (data, callback) {

        var type = this.$element.data('type');

        if (type !== 'craft\\elements\\Asset') {
            fnReloadForm.apply(this, arguments);
            return;
        }

        data = $.extend(this.getBaseData(), data);

        Craft.postActionRequest('escapedam/elements/get-editor-html', data, $.proxy(function(response, textStatus) {
            if (textStatus === 'success') {
                this.updateForm(response);
            }

            if (callback) {
                callback(textStatus);
            }
        }, this));
    };

})();
