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

    window.addEventListener('message', function (e) {
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
