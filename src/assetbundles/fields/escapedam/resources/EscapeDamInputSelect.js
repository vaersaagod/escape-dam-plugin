/** global: Craft */
/** global: Garnish */
/**
 * DAM Select input
 */
if (!Craft.EscapeDam) {
    Craft.EscapeDam = {};
}

Craft.EscapeDam.DamSelectInput = Craft.AssetSelectInput.extend({

    super: Craft.AssetSelectInput.prototype,

    damModal: null,
    disabledFileIds: null,

    getAddElementsBtn: function() {
        return this.$container.find('.btn[data-input]');
    },

    disableAddElementsBtn: function() {
        var $btn = this.$container.find('.btn[data-input]');
        if ($btn) {
            $btn.addClass('hidden');
        }
    },

    enableAddElementsBtn: function() {
        var $btn = this.$container.find('.btn[data-input]');
        if ($btn) {
            $btn.removeClass('hidden');
        }
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
            var _this = this;
            if (!this.damModal) {
                this.damModal = this.createDamModal({
                    storageKey: window.location.pathname + '.' + this.settings.fieldId,
                    onSelect: $.proxy(this.onDamModalSelect, this),
                    onHide: function() {
                        _this.$addElementBtn.focus();
                        console.log('modal closed');
                    },
                    disabledFileIds: null, // TODO
                    allowedExtensions: this.settings.allowedExtensions
                });
            } else {
                this.damModal.show();
            }
        }
    },

    onDamModalSelect: function (fileIds) {

        if (this.settings.limit) {
            // Cut off any excess elements
            var slotsLeft = this.settings.limit - this.$elements.length;
            if (fileIds.length > slotsLeft) {
                fileIds = fileIds.slice(0, slotsLeft);
            }
        }

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

        if (!this.progressBar) {
            this.progressBar = new Craft.ProgressBar($('<div class="progress-shade"></div>').appendTo(this.$container));
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

                Craft.sendActionRequest('POST', 'app/render-elements', {
                    data: {
                        elements: [
                            {
                                type: 'craft\\elements\\Asset',
                                id: assetId,
                                siteId: this.settings.criteria.siteId,
                                instances: [
                                    {
                                        context: 'field',
                                        ui: ['list', 'large'].includes(this.settings.viewMode)
                                            ? 'chip'
                                            : 'card',
                                        size: this.settings.viewMode === 'large' ? 'large' : 'small',
                                    },
                                ],
                            },
                        ],
                    },
                })
                    .then(async ({data}) => {
                        const elementInfo = Craft.getElementInfo(
                            data.elements[assetId][0]
                        );
                        this.selectElements([elementInfo]);

                        await Craft.appendHeadHtml(data.headHtml);
                        await Craft.appendBodyHtml(data.bodyHtml);

                        this._onImportComplete();
                    })
                    .catch((error) => {
                        if (error && error.response) {
                            Craft.cp.displayError(response.data.message);
                        } else {
                            Craft.cp.displayError();
                            throw error;
                        }
                    });

                Craft.cp.runQueue();
            } else {
                alert(response.message || 'Something went wrong');
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
            this.$addElementBtn.focus();
        }
        Craft.cp.runQueue();
    },

    createDamModal: function (settings) {
        return new Craft.EscapeDam.EscapeDamSelectorModal(settings);
    }
});
