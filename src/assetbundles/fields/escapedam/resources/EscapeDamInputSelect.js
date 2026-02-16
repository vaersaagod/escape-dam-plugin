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

    init: function() {
        this.super.init.apply(this, arguments);

        const $addNativeAssetsBtn = this.getAddNativeAssetsBtn();
        if ($addNativeAssetsBtn.length) {
            this.addListener($addNativeAssetsBtn, 'activate', 'showModal');
        }
    },

    getAddElementsBtn: function() {
        return this.$container.find('.btn[data-input="dam"]:first');
    },

    getAddNativeAssetsBtn: function() {
        return this.$container.find('.btn[data-input="assets"]:first');
    },

    disableAddElementsBtn: function() {
        this.$container.find('.btn[data-input]').each(function () {
            $(this).addClass('hidden');
        });

        this.updateButtonContainer();
    },

    enableAddElementsBtn: function() {
        if (this.settings.allowAdd) {
            this.$container.find('.btn[data-input]').each(function () {
                $(this).removeClass('hidden');
            });
        }
        this.updateButtonContainer();
    },

    focusNextLogicalElement: function () {
        if (!this.canAddMoreElements()) {
            // If can add more elements, focus on add button
            if (
                this.$addElementBtn.length &&
                !this.$addElementBtn.hasClass('hidden')
            ) {
                this.$addElementBtn.focus();
            }
        } else {
            // If can't add more elements, focus on the final remove
            this.focusLastRemoveBtn();
        }
    },

    defineElementActions: function($element) {
        // Remove the "Replace" action from assets in Escape DAM fields, because it doesn't work.
        const actions = this.super.defineElementActions.apply(this, arguments)
            .filter(action => action.label !== Craft.t('app', 'Replace'));

        if (this.settings.showActionMenu) {
            // ...and replace it with our own actions
            actions.push({
                icon: async () => await Craft.ui.icon('arrows-rotate'),
                label: Craft.t('escapedam', 'Replace from DAM'),
                callback: () => {
                    this._$replaceElement = $element;
                    this.showDamModal();
                }
            });

            if (this.settings.enableAssetsInput) {
                actions.push({
                    icon: async () => await Craft.ui.icon('arrows-rotate'),
                    label: Craft.t('escapedam', 'Replace from assets'),
                    callback: () => {
                        this._$replaceElement = $element;
                        this.showNativeModal();
                    }
                });
            }
        }

        return actions;
    },

    showModal: function (e) {
        var $target = $(e.target);
        if ($target.data('input') === 'assets') {
            // Show default Assets modal
            this.showNativeModal();
        } else {
            // Show super-awesome DAM modal
            this.showDamModal();
        }
    },

    showNativeModal() {
        // Make sure we haven't reached the limit
        if (!this._$replaceElement && !this.canAddMoreElements() || !this.settings.enableAssetsInput) {
            return;
        }
        this.super.showModal.apply(this, arguments);
    },

    showDamModal() {
        // Make sure we haven't reached the limit
        if (!this._$replaceElement && !this.canAddMoreElements()) {
            return;
        }
        if (!this.damModal) {
            this.damModal = this.createDamModal({
                storageKey: `${window.location.pathname}.${this.settings.fieldId}`,
                onSelect: $.proxy(this.onDamModalSelect, this),
                onHide: () => {
                    this.$addElementBtn.focus({ preventScroll: true });
                    if (!this.fileIdsToImport) {
                        this._$replaceElement = null;
                    }
                },
                disabledFileIds: null, // TODO
                allowedExtensions: this.settings.allowedExtensions
            });
        } else {
            this.damModal.show();
        }
    },

    onDamModalSelect: function (fileIds) {

        if (this.settings.limit) {
            // Cut off any excess elements
            var slotsLeft = this.settings.limit - this.$elements.length;
            if (this._$replaceElement) {
                slotsLeft += 1;
            }
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
                                        ui: ['list', 'list-inline', 'large', 'thumbs'].includes(this.settings.viewMode)
                                            ? 'chip'
                                            : 'card',
                                        size: ['large', 'thumbs'].includes(this.settings.viewMode)
                                          ? 'large'
                                          : 'small',
                                        showActionMenu: true
                                    }
                                ]
                            }
                        ]
                    }
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
        if (this._$replaceElement) {
            this.removeElement(this._$replaceElement);
        }

        this._$replaceElement = null;

        // Last file?
        if (this.fileIdsToImport.length) {
            this._importFile(this.fileIdsToImport.shift());
        } else {
            this.progressBar.hideProgressBar();
            this.$container.removeClass('uploading');
            this.$addElementBtn.focus({ preventScroll: true });
        }
        Craft.cp.runQueue();
    },

    createDamModal: function (settings) {
        return new Craft.EscapeDam.EscapeDamSelectorModal(settings);
    }
});
