;var alto = alto || {};

/**
 * Управление пользователями
 */
alto.user = (function ($) {
    "use strict";
    var $that = this;

    this.jcropImage = null;

    this.options = {};

    /**
     * Initialization
     */
    this.init = function() {

        // Authorization
        alto.ajaxForm(alto.routerUrl('login') + 'ajax-login/', '.js-form-login', function (result, status, xhr, form) {
            if (result && result.sUrlRedirect) {
                alto.progressStart();
                window.location.href = result.sUrlRedirect
            }
        });

        /* Registration */
        alto.ajaxForm(alto.routerUrl('registration') + 'ajax-registration/', '.js-form-registration', function (result, status, xhr, form) {
            if (result && result.sUrlRedirect) {
                alto.progressStart();
                window.location.href = result.sUrlRedirect
            }
        });

        /* Password reset */
        alto.ajaxForm(alto.routerUrl('login') + 'ajax-reminder/', '.js-form-reminder', function (result, status, xhr, form) {
            if (result && result.sUrlRedirect) {
                alto.progressStart();
                window.location.href = result.sUrlRedirect
            }
        });

        /* Request for activation link */
        alto.ajaxForm(alto.routerUrl('login') + 'ajax-reactivation/', '.js-form-reactivation', function (result, status, xhr, form) {
            form.find('input').val('');
            // *depricated* //ls.hook.run('ls_user_reactivation_after', [form, result]);
        });

    };

    /**
     * Валидация полей формы при регистрации
     */
    this.validateRegistrationFields = function (form, fields) {
        var url = alto.routerUrl('registration') + 'ajax-validate-fields/';
        var params = {fields: fields};
        form = $(form);

        $.each(fields, function (i, data) {
            $('[name=' + data.field + ']').addClass('loader');
        });
        alto.ajax(url, params, function (result) {
            $.each(fields, function (i, data) {
                $('[name=' + data.field + ']').removeClass('loader');
                if (result.aErrors && result.aErrors[data.field][0]) {
                    form.find('.validate-error-field-' + data.field).removeClass('validate-error-hide').addClass('validate-error-show').text(result.aErrors[data.field][0]);
                    form.find('.validate-ok-field-' + data.field).hide();
                } else {
                    form.find('.validate-error-field-' + data.field).removeClass('validate-error-show').addClass('validate-error-hide');
                    form.find('.validate-ok-field-' + data.field).show();
                }
            });
            // *depricated* //ls.hook.run('ls_user_validate_registration_fields_after', [fields, form, result]);
        });
    };

    /**
     * Валидация конкретного поля формы
     */
    this.validateRegistrationField = function(form, fieldName, fieldValue, params) {
        var fields = [];
        if (fieldName == 'password') {
            var login = $(form).find('[name=login]').val();
            if (login) {
                params['login'] = login;
            }
        }
        fields.push({field: fieldName, value: fieldValue, params: params || {}});
        this.validateRegistrationFields(form, fields);
    };

    /**
     * Добавление в друзья
     */
    this.addFriend = function (form, idUser, sAction) {
        var sText ='',
            url = '';
        form = $(form);
        if (sAction != 'link' && sAction != 'accept') {
            sText = $('#add_friend_text').val();
            form.children().each(function (i, item) {
                $(item).attr('disabled', 'disabled')
            });
        }

        if (sAction == 'accept') {
            url = alto.routerUrl('profile') + 'ajaxfriendaccept/';
        } else {
            url = alto.routerUrl('profile') + 'ajaxfriendadd/';
        }

        var params = {idUser: idUser, userText: sText};

        alto.progressStart();
        alto.ajax(url, params, function (result) {
            alto.progressDone();
            form.children().each(function (i, item) {
                $(item).removeAttr('disabled')
            });
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(null, result.sMsg);
            } else {
                alto.msg.notice(null, result.sMsg);
                $('#modal-add_friend').modal('hide');
                $('#profile_actions  li:first').html($.trim(result.sToggleText));
                // *depricated* //ls.hook.run('ls_user_add_friend_after', [idUser, sAction, result], form);
            }
        });
        return false;
    };

    /**
     * Удаление из друзей
     */
    this.removeFriend = function (button, idUser, sAction) {
        var url = alto.routerUrl('profile') + 'ajaxfrienddelete/',
            params = {idUser: idUser, sAction: sAction};

        alto.progressStart();
        alto.ajax(url, params, function (result) {
            alto.progressDone();
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(null, result.sMsg);
            } else {
                alto.msg.notice(null, result.sMsg);
                $('#profile_actions li:first').html($.trim(result.sToggleText));
                // *depricated* //ls.hook.run('ls_user_remove_friend_after', [idUser, sAction, result], button);
            }
        });
        return false;
    };

    /**
     * Поиск пользователей
     */
    this.searchUsers = function (form) {
        form = $(form);
        var url = alto.routerUrl('people') + 'ajax-search/';
        var inputSearch = form.find('input');
        inputSearch.addClass('loader');

        alto.ajaxSubmit(url, form, function (result) {
            inputSearch.removeClass('loader');
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                $('#users-list-search').hide();
                $('#users-list-original').show();
            } else {
                $('#users-list-original').hide();
                $('#users-list-search').html(result.sText).show();
                // *depricated* //ls.hook.run('ls_user_search_users_after', [form, result]);
            }
        });
    };

    /**
     * Поиск пользователей по началу логина
     */
    this.searchUsersByPrefix = function (sPrefix, button) {
        var url = alto.routerUrl('people') + 'ajax-search/',
            params = {user_login: sPrefix, isPrefix: 1};

        button = $(button);
        $('#search-user-login').addClass('loader');

        alto.ajax(url, params, function (result) {
            $('#search-user-login').removeClass('loader');
            $('#user-prefix-filter').find('.active').removeClass('active');
            button.parent().addClass('active');
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                $('#users-list-search').hide();
                $('#users-list-original').show();
            } else {
                $('#users-list-original').hide();
                $('#users-list-search').html(result.sText).show();
                // *depricated* //ls.hook.run('ls_user_search_users_by_prefix_after', [sPrefix, button, result]);
            }
        });
        return false;
    };

    /**
     * Подписка
     */
    this.followToggle = function (button, iUserId) {
        button = $(button);
        if (button.hasClass('followed')) {
            alto.stream.unsubscribe(iUserId);
            button.toggleClass('followed').text(alto.lang.get('profile_user_follow')).prepend('<i class="fa fa-star-o"></i>&nbsp;');
        } else {
            alto.stream.subscribe(iUserId);
            button.toggleClass('followed').text(alto.lang.get('profile_user_unfollow')).prepend('<i class="fa fa-star-o"></i>&nbsp;');
        }
        return false;
    };


    return this;
}).call(alto.user || {},jQuery);

$(function() {
    alto.user.init();
});
