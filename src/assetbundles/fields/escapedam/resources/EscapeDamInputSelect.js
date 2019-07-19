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
                    onSelect: $.proxy(this.onDamModalSelect, this)
                });
            } else {
                this.damModal.show();
            }
        }
    },

    onDamModalSelect: function (fileIds) {
        this._importFiles(fileIds);

        this.progressBar.$progressBar.css({
            top: Math.round(this.$container.outerHeight() / 2) - 6
        });

        this.$container.addClass('uploading');
        this.progressBar.resetProgressBar();
        this.progressBar.showProgressBar();

        for (var i = 0; i < fileIds.length; ++i) {
            var fileId = parseInt(fileIds[i], 10);
            setTimeout(function () {

            }, 1500);
        }
    },

    _importFiles: function (fileIds) {

        if (!fileIds || !fileIds.length) {
            return;
        }

        this.fileIdsToImport = fileIds;
        this.importedFiles = [];

        this.progressBar.$progressBar.css({
            top: Math.round(this.$container.outerHeight() / 2) - 6
        });

        this.$container.addClass('uploading');
        this.progressBar.resetProgressBar();
        this.progressBar.showProgressBar();

        this._importFile(this.fileIdsToImport.shift());

    },

    _importFile: function (fileId) {
        console.log('import file', this.fileIdsToImport);
        this.progressBar.setProgressPercentage(0);
        Craft.postActionRequest('escapedam/files/import-file', {
            fileId: fileId,
            fieldId: this.settings.fieldId,
            elementId: this.settings.sourceElementId,
            siteId: this.settings.criteria.siteId
        }, $.proxy(function (response) {
            console.log({ response }, this.fileIdsToImport);
            this.importedFiles.push(fileId);
            if (this.fileIdsToImport.length) {
                this._importFile(this.fileIdsToImport.shift());
            } else {
                this.progressBar.hideProgressBar();
                this.$container.removeClass('uploading');
            }
        }, this));
    },

    // onModalSelect: function(elements) {
    //     console.log({ elements });
    //     this.super.onModalSelect.apply(this, arguments);
    //     // if (this.settings.limit) {
    //     //     // Cut off any excess elements
    //     //     var slotsLeft = this.settings.limit - this.$elements.length;
    //     //
    //     //     if (elements.length > slotsLeft) {
    //     //         elements = elements.slice(0, slotsLeft);
    //     //     }
    //     // }
    //     //
    //     // this.selectElements(elements);
    //     // this.updateDisabledElementsInModal();
    // },

    createDamModal: function (settings) {
        return new Craft.EscapeDam.EscapeDamSelectorModal(settings);
    },

    _attachUploader: function () {
        if (!this.settings.assetsEnabled) {
            // Disable the drag'n'drop uploader if native Assets isn't enabled for this field
            this.$container.on('dragover drop', function (e) {
                e.preventDefault();
                return false;
            });
            // ...but we do need the progress bar still
            this.progressBar = new Craft.ProgressBar($('<div class="progress-shade"></div>').appendTo(this.$container));
            return;
        }
        this.super._attachUploader.apply(this, arguments);
    },
});
