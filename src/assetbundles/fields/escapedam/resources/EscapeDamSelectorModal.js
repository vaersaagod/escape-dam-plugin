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

        this.setSettings(settings, Craft.EscapeDam.EscapeDamSelectorModal.defaults);

        // Get DAM url and token
        var damUrl = Craft.EscapeDam.settings.damUrl;
        if (!damUrl) {
            console.error('No DAM URL');
            return;
        }

        // Build the modal
        this.$container = $('<div class="modal elementselectormodal dam-modal"></div>').appendTo(Garnish.$bod);
        this.$body = $('<div class="body"><iframe src="" style="width:100%;height:100%;overflow:hidden;" scrolling="no" frameborder="0" /></div>').appendTo(this.$container);

        this.base(this.$container, this.settings);

        // Cut the flicker, just show the nice person the modal.
        if (this.$container) {
            this.$container.velocity('stop');
            this.$container.show().css('opacity', 1);
            this.$shade.velocity('stop');
            this.$shade.show().css('opacity', 1);
        }

        this.$iframe = this.$body.find('iframe');

        // Create IE + others compatible event handler
        var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
        var eventer = window[eventMethod];
        var messageEvent = eventMethod == "attachEvent" ? "onmessage" : "message";

        // Listen to message from child window
        eventer(messageEvent, $.proxy(function (e) {
            if (!this.visible) {
                return;
            }
            var data = e.data || {};
            var source = e.source || null;
            var action = data.action || null;
            switch (action) {
                case 'select-files':
                    var fileIds = data.fileIds || [];
                    this.selectFiles(fileIds);
                    break;
                case 'refresh-token':
                    $.ajax(Craft.getActionUrl('escapedam/token/get-token'), {
                        success: function (token) {
                            source.window.postMessage({ token: token }, Craft.EscapeDam.settings.damUrl);
                        }
                    });
                    break;
                default:
                    console.warn('Unknown action: ', action);
            }
        }, this), false);

        // Get a fresh token, and then show the DAM in the iframe
        $.ajax(Craft.getActionUrl('escapedam/token/get-token'), {
            success: $.proxy(function (token) {
                this.$iframe.attr('src', damUrl + '?token=' + token + '&context=field&storageKey=' + this.settings.storageKey);
            }, this)
        });
    },

    selectFiles: function (fileIds) {
        if (this.settings.onSelect) {
            this.settings.onSelect(fileIds);
        }
        this.hide();
    },

    /**
     * Override default logic with some extra shenanigans
     */
    updateSizeAndPosition: function () {
        var containerWidth = Garnish.$win.width() - (this.settings.minGutter * 2);
        var containerHeight = Garnish.$win.height() - (this.settings.minGutter * 2);
        this._resizeContainer(containerWidth, containerHeight);
    },

    /**
     * Resize the container to specified dimensions
     * @param containerWidth
     * @param containerHeight
     * @private
     */
    _resizeContainer: function (containerWidth, containerHeight) {
        this.$container.css({
            'width': containerWidth,
            'min-width': containerWidth,
            'max-width': containerWidth,
            'height': containerHeight,
            'min-height': containerHeight,
            'max-height': containerHeight,
            'top': (Garnish.$win.height() - containerHeight) / 2,
            'left': (Garnish.$win.width() - containerWidth) / 2
        });
    }
}, {
    defaults: {
        closeOtherModals: true,
        resizable: false,
        minGutter: 30
    }
});

//Craft.BaseElementSelectorModal.extend();
