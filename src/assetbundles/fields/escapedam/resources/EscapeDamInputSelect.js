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
    disabledFileIds: null,

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
            this.damModal = this.createDamModal({
                storageKey: window.location.href + '.' + this.settings.fieldId,
                onSelect: $.proxy(this.onDamModalSelect, this),
                disabledFileIds: null // TODO
            });
        }
    },

    onDamModalSelect: function (fileIds) {

        /*if (this.settings.limit) {
            // Cut off any excess elements
            var slotsLeft = this.settings.limit - this.$elements.length;
            if (fileIds.length > slotsLeft) {
                fileIds = fileIds.slice(0, slotsLeft);
            }
        }*/

        this._importFiles(fileIds);

        this.progressBar.$progressBar.css({
            top: Math.round(this.$container.outerHeight() / 2) - 6
        });

        this.$container.addClass('uploading');
        this.progressBar.resetProgressBar();
        this.progressBar.showProgressBar();
    },

    _importFiles: function (fileIds) {

        if (!fileIds || !fileIds.length) {
            return;
        }

        this.progressBar.$progressBar.css({
            top: Math.round(this.$container.outerHeight() / 2) - 6
        });

        this.$container.addClass('uploading');
        this.progressBar.resetProgressBar();
        this.progressBar.showProgressBar();

        this.fileIdsToImport = fileIds;
        this._importFile(this.fileIdsToImport.shift());

    },

    _importFile: function (fileId) {

        this.progressBar.setProgressPercentage(0);

        Craft.postActionRequest('escapedam/files/import-file', {
            fileId: fileId,
            fieldId: this.settings.fieldId,
            elementId: this.settings.sourceElementId,
            siteId: this.settings.criteria.siteId
        }, function(response) {
            var assetId = response.success && response.assetId ? parseInt(response.assetId, 10) : null;
            if (assetId) {
                // Check if – somehow – this Asset was already selected
                var selectedAssets = this.$elements.get();
                var isSelected = false;
                for (var i = 0; i < selectedAssets.length; ++i) {
                    if (parseInt($(selectedAssets[i]).data('id'), 10) === assetId) {
                        isSelected = true;
                        break;
                    }
                }
                if (isSelected) {
                    this._onImportComplete();
                    return;
                }
                Craft.postActionRequest('elements/get-element-html', {
                    elementId: assetId,
                    siteId: this.settings.criteria.siteId,
                    size: this.settings.viewMode
                }, function(data) {
                    if (data.error) {
                        alert(data.error);
                    } else {
                        var html = $(data.html);
                        Craft.appendHeadHtml(data.headHtml);
                        this.selectUploadedFile(Craft.getElementInfo(html));
                    }
                    this._onImportComplete();
                }.bind(this));
            } else {
                alert(response.error || 'Something went wrong');
                console.log(response);
                this._onImportComplete();
            }
        }.bind(this));
    },

    _onImportComplete: function () {
        // Last file?
        if (this.fileIdsToImport.length) {
            this._importFile(this.fileIdsToImport.shift());
        } else {
            this.progressBar.hideProgressBar();
            this.$container.removeClass('uploading');
            if (window.draftEditor) {
                window.draftEditor.checkForm();
            }
            try {
                this.$addElementBtn.focus();
            } catch (error) {}
        }
        Craft.cp.runQueue();
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
