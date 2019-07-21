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
        // TODO check if element is an asset, if it's got a DAM file ID – if it does, load our augmented element editor. If not, proceed as usual
        console.log(this.$element.data());
        fnLoadHud.apply(this, arguments);
    }

})();
