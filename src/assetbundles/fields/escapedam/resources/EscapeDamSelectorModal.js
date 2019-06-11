/** global: Craft */
/** global: Garnish */
/**
 * DAM Select input
 */

if (!Craft.EscapeDam) {
    Craft.EscapeDam = {};
}

Craft.EscapeDam.EscapeDamSelectorModal = Garnish.Modal.extend({
    init: function (settings) {

        console.log({ settings });

        this.setSettings(settings, Craft.BaseElementSelectorModal.defaults);

        // Build the modal
        var $container = $('<div class="modal elementselectormodal"></div>').appendTo(Garnish.$bod),
            $body = $('<div class="body"><div class="spinner big"></div></div>').appendTo($container);

        this.base($container, this.settings);
        this.$body = $body;

    }
});//Craft.BaseElementSelectorModal.extend();
