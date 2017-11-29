;var alto = alto || {};

/**
 * Подписка
 */
alto.subscribe = (function ($) {

    /**
     * Подписка/отписка
     */
    this.toggle = function (sTargetType, iTargetId, sMail, iValue) {
        var url = alto.routerUrl('subscribe') + 'ajax-subscribe-toggle/';
        var params = {target_type: sTargetType, target_id: iTargetId, mail: sMail, value: iValue};

        alto.progressStart();
        alto.ajax(url, params, function (result) {
            alto.progressDone();
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(null, result.sMsg);
            } else {
                alto.msg.notice(null, result.sMsg);
                // *depricated* //ls.hook.run('ls_subscribe_toggle_after', [sTargetType, iTargetId, sMail, iValue, result]);
            }
        });
        return false;
    };

    /**
     * Подписка/отписка
     */
    this.tracktoggle = function(sTargetType, iTargetId, iValue) {
        var url = alto.routerUrl('subscribe') + 'ajax-track-toggle/';
        var params = {target_type: sTargetType, target_id: iTargetId, value: iValue};

        alto.progressStart();
        alto.ajax(url, params, function(result) {
            alto.progressDone();
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(null, result.sMsg);
            } else {
                alto.msg.notice(null, result.sMsg);
                // *depricated* //ls.hook.run('ls_track_toggle_after',[sTargetType, iTargetId, iValue, result]);
            }
        });
        return false;
    };

    return this;
}).call(alto.subscribe || {}, jQuery);