/**
 * Активность
 */

;var alto = alto || {};

alto.stream = ( function ($) {
    "use strict";

    this.isBusy = false;
    this.sDateLast = null;

    this.options = {
        selectors: {
            userList: 'js-activity-block-users',
            getMoreButton: 'activity-get-more',
            userListId: 'activity-block-users',
            inputId: 'activity-block-users-input',
            noticeId: 'activity-block-users-notice',
            userListItemId: 'activity-block-users-item-'
        },
        elements: {
            userItem: function (element) {
                return $('<li id="' + alto.stream.options.selectors.userListItemId + element.uid + '">' +
                    '<input type="checkbox" ' +
                    'class="input-checkbox" ' +
                    'data-user-id="' + element.uid + '" ' +
                    'checked="checked" />' +
                    '<a href="' + element.user_web_path + '">' + element.user_login + '</a>' +
                    '</li>');
            }
        }
    };

    /**
     * Init
     */
    this.init = function () {
        var self = this;

        $('.' + this.options.selectors.userList).on('change', 'input[type=checkbox]', function () {
            var userId = $(this).data('user-id');

            $(this).prop('checked') ? self.subscribe(userId) : self.unsubscribe(userId);
        });

        $('#' + this.options.selectors.getMoreButton).on('click', function () {
            self.getMore(this);
            return false;
        });

        $('#' + this.options.selectors.inputId).keydown(function (event) {
            event.which == 13 && alto.stream.appendUser();
        });
    };

    /**
     * Подписаться на пользователя
     * @param  {Number} iUserId ID пользователя
     */
    this.subscribe = function (iUserId) {
        var self = this,
            url = alto.routerUrl('stream') + 'subscribe/',
            params = { 'id': iUserId };

        alto.progressStart();
        alto.ajax(url, params, function (result) {
            alto.progressDone();
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(result.sMsgTitle, result.sMsg);
            } else {
                alto.msg.notice(result.sMsgTitle, result.sMsg);
                // *depricated* //ls.hook.run('ls_stream_subscribe_after', [params, result]);
            }
        });
    };

    /**
     * Отписаться от пользователя
     * @param  {Number} iUserId ID пользователя
     * @param bRemove Удалять строку с пользователем или нет (друзей из списка не удаляем)
     */
    this.unsubscribe = function (iUserId, bRemove) {
        var self = this,
            url = alto.routerUrl('stream') + 'unsubscribe/',
            params = { 'id': iUserId };

        alto.progressStart();
        alto.ajax(url, params, function (result) {
            alto.progressDone();
            if (result && !result.bStateError) {
                alto.msg.notice(result.sMsgTitle, result.sMsg);
                var el = $('#strm_u_' + iUserId).parents('li');
                if (bRemove === true) {
                    el.fadeOut(300, function(){
                        el.remove();
                    });
                }
                // *depricated* //ls.hook.run('ls_stream_unsubscribe_after', [params, result]);
            }
        });
    };

    /**
     * Подписаться на пользователя
     */
    this.appendUser = function () {
        var self = this,
            sLogin = $('#' + self.options.selectors.inputId).val();

        if (!sLogin) {
            return;
        }

        alto.progressStart();
        alto.ajax(alto.routerUrl('stream') + 'subscribeByLogin/', { 'login': sLogin }, function (result) {
            alto.progressDone();
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(null, result.sMsg);
            } else {
                var checkbox = $('.' + self.options.selectors.userList).find('input[data-user-id=' + result.uid + ']');

                $('#' + self.options.selectors.noticeId).remove();

                if (checkbox.length) {
                    if (checkbox.prop("checked")) {
                        alto.msg.error(alto.lang.get('error'), alto.lang.get('stream_subscribes_already_subscribed'));
                    } else {
                        checkbox.prop("checked", true);
                        alto.msg.notice(result.sMsgTitle, result.sMsg);
                    }
                } else {
                    $('#' + self.options.selectors.inputId).autocomplete('close').val('');
                    $('#' + self.options.selectors.userListId).show().append(self.options.elements.userItem(result));
                    alto.msg.notice(result.sMsgTitle, result.sMsg);
                }

                // *depricated* //ls.hook.run('ls_stream_append_user_after', [checkbox.length, result]);
            }
        });
    };

    this.switchEventType = function (iType) {
        var url = alto.routerUrl('stream') + 'switchEventType/';
        var params = {'type': iType};

        alto.progressStart();
        alto.ajax(url, params, function (result) {
            alto.progressDone();
            if (result && !result.bStateError) {
                alto.msg.notice(result.sMsgTitle, result.sMsg);
                // *depricated* //ls.hook.run('ls_stream_switch_event_type_after', [params, result]);
            }
        });
    };

    /**
     * Подгрузка событий
     * @param  {Object} oGetMoreButton Кнопка
     */
    this.getMore = function (oGetMoreButton) {

        if (this.isBusy) {
            return;
        }

        var $oGetMoreButton = $(oGetMoreButton);

        this.isBusy = true;

        var params = $.extend({}, {
            'sDateLast': this.sDateLast
        }, alto.tools.getDataOptions($oGetMoreButton, 'param'));

        params.iLastId = params.last_id ? params.last_id : 0;
        if (!params.iLastId) {
            return;
        }

        var url = alto.routerUrl('stream') + 'get_more' + (params.type ? '_' + params.type : '') + '/';

        $oGetMoreButton.addClass('loading');
        alto.progressStart();
        alto.ajax(url, params, function (result) {
            alto.progressDone();
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(null, result.sMsg);
            } else {
                if (result.events_count) {
                    $('#activity-event-list').append(result.result);
                    $oGetMoreButton.data('param-last_id', result.iStreamLastId);
                }

                if (!result.events_count) {
                    $oGetMoreButton.hide();
                }
            }

            $oGetMoreButton.removeClass('loading');

            this.isBusy = false;
        }.bind(this));

        return false;
    };

    return this;
}).call(alto.stream || {}, jQuery);