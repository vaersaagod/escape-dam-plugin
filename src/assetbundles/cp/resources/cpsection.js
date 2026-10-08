/** global: Craft */
/** global: Garnish */
/** global: $ */

$(function () {

    if (!Craft.EscapeDam) {
        return;
    }

    var $iframe = $('iframe#escapedam-frame');

    if (!$iframe.length) {
        return;
    }

    var damOrigin = new URL(Craft.EscapeDam.settings.damUrl, window.location.href).origin;

    window.addEventListener('message', function (e) {
        // Only listen to the DAM in our own iframe
        if (e.origin !== damOrigin || e.source !== $iframe[0].contentWindow) {
            return;
        }
        var source = e.source || null;
        var data = e.data || {};
        var action = data.action || null;
        if (action === 'refresh-token') {
            $.ajax(Craft.getActionUrl('escapedam/token/get-token'), {
                success: function (token) {
                    source.window.postMessage({ token: token }, Craft.EscapeDam.settings.damUrl);
                }
            });
        }
    });

});
