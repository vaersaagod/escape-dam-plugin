/** global: Craft */
/** global: Garnish */
/**
 * Asset index class
 */

(function () {

    if (!Craft || !Craft.BaseElementEditor) {
        return false;
    }

    if (!Craft.EscapeDam) {
        Craft.EscapeDam = {};
    }

    var fnUpdateForm = Craft.AssetEditor.prototype.updateForm;
    Craft.AssetEditor.prototype.updateForm = function () {
        fnUpdateForm.apply(this, arguments);
        console.log(this.$element.data());
        // Init focal point
        var $imageEditor = this.$fieldsContainer.find('> .meta > .image-preview-container.editable');
        if (!$imageEditor.length) {
            return;
        }
        this.removeListener($imageEditor, 'click', 'showImageEditor');
        $imageEditor.css({ cursor: 'default' });
        var $focalPointEditor = $('<div class="escapedam-focalpoint"/>');
        $imageEditor.find('img').wrap($focalPointEditor);

        var $editBtn = $imageEditor.find('.btn');
        this.addListener($editBtn, 'click', 'showImageEditor');

        var assetId = this.$element.data('id');
        Craft.postActionRequest('escapedam/elements/get-asset-focal-point', { assetId: assetId }, $.proxy(function (response, textStatus) {
            if (textStatus !== 'success' || !response || !response.focalPoint) {
                console.warn('Not able to get focal point');
                return;
            }
            this.initFocalPointEditor(response.focalPoint);
        }, this));

        this.$imageEditor = $imageEditor;

    }

    Craft.AssetEditor.prototype.initFocalPointEditor = function (focalPoint) {
        this.$focalPointEditor = this.$imageEditor.find('.escapedam-focalpoint');
        this.$focalPoint = $('<div class="escapedam-focalpoint__point" />');
        this.$focalPoint.css({
            left: (focalPoint.x * 100) + '%',
            top: (focalPoint.y * 100) + '%'
        });
        this.$focalPointEditor.append(this.$focalPoint);
        this.$focalPointEditor.on('click', $.proxy(function (e) {
            this.updateFocalPointPosition(e.pageX, e.pageY);
        }, this));
        this.$focalPointEditor.on('mouseleave', $.proxy(function (e) {
            this.isFocalPointDragging = false;
        }, this));
        this.$focalPoint.on('mousedown', $.proxy(function (e) {
            this.isFocalPointDragging = true;
        }, this));
        this.$focalPoint.on('mouseup', $.proxy(function (e) {
            this.isFocalPointDragging = false;
        }, this));
        $(window).on('mousemove', $.proxy(function (e) {
            if (this.isFocalPointDragging) {
                this.updateFocalPointPosition(e.pageX, e.pageY);
            }
        }, this));
        // Create an hidden input field to save the focal point
        var namespace = this.$fieldsContainer.find('input[type="hidden"][name="namespace"]').val();
        var focalPointInputName = namespace + '[focalPoint]';
        this.$focalPointInput = $('<input type="hidden" name="' + focalPointInputName + '" value="' + [focalPoint.x, focalPoint.y].join(';') + '" />');
        this.$fieldsContainer.append(this.$focalPointInput);
    }

    Craft.AssetEditor.prototype.updateFocalPointPosition = function (pageX, pageY) {
        var parentOffset = this.$focalPointEditor.offset();
        var imageWidth = this.$focalPointEditor.outerWidth();
        var imageHeight = this.$focalPointEditor.outerHeight();
        var posX = pageX - parentOffset.left;
        var posY = pageY - parentOffset.top;

        var precision = Math.pow(10, 2);
        var percentX = Math.round((posX / imageWidth) * 100 * precision) / precision;
        var percentY = Math.round((posY / imageHeight) * 100 * precision) / precision;

        percentX = Math.max(0, Math.min(percentX, 100));
        percentY = Math.max(0, Math.min(percentY, 100));

        this.$focalPoint.css({
            left: percentX + '%',
            top: percentY + '%'
        });

        // Update the input field
        var focalPointX = parseFloat((percentX / 100).toFixed(4));
        var focalPointY = parseFloat((percentY / 100).toFixed(4));
        this.$focalPointInput.val([focalPointX, focalPointY].join(';'));

    }

})();


// Craft.EscapeDam.AssetEditor = Craft.AssetEditor.extend(
//     {
//         reloadIndex: false,
//
//         updateForm: function (response) {
//             this.base(response);
//
//             if (this.$element.data('id')) {
//                 var $imageEditorTrigger = this.$fieldsContainer.find('> .meta > .image-preview-container.editable');
//
//                 if ($imageEditorTrigger.length) {
//                     this.addListener($imageEditorTrigger, 'click', 'showImageEditor');
//                 }
//             }
//
//         },
//
//         showImageEditor: function () {
//             new Craft.AssetImageEditor(this.$element.data('id'), {
//                 onSave: function () {
//                     this.reloadIndex = true;
//                     this.reloadForm();
//                 }.bind(this),
//                 allowDegreeFractions: Craft.isImagick
//             });
//         },
//
//         onHideHud: function () {
//             if (this.reloadIndex && this.settings.elementIndex) {
//                 this.settings.elementIndex.updateElements();
//             }
//
//             this.base();
//         }
//     });

// Register it!
//Craft.registerElementEditorClass('craft\\elements\\Asset', Craft.EscapeDam.AssetEditor);
