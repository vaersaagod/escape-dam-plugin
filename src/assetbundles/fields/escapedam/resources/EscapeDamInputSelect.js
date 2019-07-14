/** global: Craft */
/** global: Garnish */
/**
 * DAM Select input
 */
if (!Craft.EscapeDam) {
    Craft.EscapeDam = {};
}

// TODO wtf to do about drag-to-upload

Craft.EscapeDam.DamSelectInput = Craft.AssetSelectInput.extend({

    super: Craft.AssetSelectInput.prototype,

    damModal: null,

    init: function () {
        this.super.init.apply(this, arguments);
    },

    showModal: function (e) {
        if (!this.canAddMoreElements()) {
            return;
        }
        var $target = $(e.target);
        if ($target.data('input') === 'assets') {
            // Show default Assets modal
            this.super.showModal.apply(this, arguments);
        } else {
            // Show super-awesome DAM modal
            if (!this.damModal) {
                this.damModal = this.createDamModal({
                    onSelect: function (fileIds) {
                        console.log('oh I selected some IDs, sick!', { fileIds });
                    }
                });
            } else {
                this.damModal.show();
            }
        }
    },

    createDamModal: function (settings) {
        return new Craft.EscapeDam.EscapeDamSelectorModal(settings);
    }
});
