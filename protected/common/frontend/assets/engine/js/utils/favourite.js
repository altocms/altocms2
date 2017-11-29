;var alto = alto || {};

/**
 * Добавление в избранное
 */
ls.favourite = (function ($) {
    "use strict";
    /**
     * Опции
     */
    this.options = {
        active: 'active',
        type: {
            topic: {
                url: alto.routerUrl('ajax') + 'favourite/topic/',
                targetName: 'idTopic'
            },
            talk: {
                url: alto.routerUrl('ajax') + 'favourite/talk/',
                targetName: 'idTalk'
            },
            comment: {
                url: alto.routerUrl('ajax') + 'favourite/comment/',
                targetName: 'idComment'
            }
        }
    };

    /**
     * Переключение избранного
     */
    this.toggle = function (idTarget, objFavourite, type) {
        if (!this.options.type[type]) {
            return false;
        }

        this.objFavourite = $(objFavourite);

        var params = {};
        params['type'] = !this.objFavourite.hasClass(this.options.active);
        params[this.options.type[type].targetName] = idTarget;

        alto.progressStart();
        alto.ajax(this.options.type[type].url, params, function (result) {
            alto.progressDone();
            $(this).trigger('toggle', [idTarget, objFavourite, type, params, result]);
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(null, result.sMsg);
            } else {
                var counter = $('#fav_count_' + type + '_' + idTarget);

                alto.msg.notice(null, result.sMsg);
                this.objFavourite.removeClass(this.options.active);

                if (result.bState) {
                    this.objFavourite.addClass(this.options.active).attr('title', alto.lang.get('talk_favourite_del'));
                    this.showTags(type, idTarget);
                } else {
                    this.objFavourite.attr('title', alto.lang.get('talk_favourite_add'));
                    this.hideTags(type, idTarget);
                }

                result.iCount > 0 ? counter.show().text(result.iCount) : counter.hide();

                // *depricated* //ls.hook.run('ls_favourite_toggle_after', [idTarget, objFavourite, type, params, result], this);
            }
        }.bind(this));
        return false;
    };

    this.showEditTags = function (idTarget, type, obj) {
        // selector #favourite-form-tags for old skin compatibility
        var form = $('#modal-favourite_tags');
        if (!form.length) {
            form = $('#favourite-form-tags');
        }
        var targetType = $('#favourite-form-tags-target-type').val(type),
            targetId = $('#favourite-form-tags-target-id').val(idTarget),
            tags = $('.js-favourite-tags-' + targetType.val() + '-' + targetId.val()),
            text = '';

        tags.find('.js-favourite-tag-user a').each(function (k, tag) {
            if (text) {
                text = text + ', ' + $(tag).text();
            } else {
                text = $(tag).text();
            }
        });
        $('#favourite-form-tags-tags').val(text);
        //$(obj).parents('.js-favourite-insert-after-form').after(form);
        form.modal('show');

        return false;
    };

    this.hideEditTags = function () {
        var form = $('#modal-favourite_tags');

        if (!form.length) {
            form = $('#favourite-form-tags');
        }
        form.modal('hide');

        return false;
    };

    this.saveTags = function (form) {
        var url = alto.routerUrl('ajax') + 'favourite/save-tags/';

        alto.progressStart();
        ls.ajaxSubmit(url, $(form), function (result) {
            alto.progressDone();
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(null, result.sMsg);
            } else {
                var type = $('#favourite-form-tags-target-type').val(),
                    tags = $('.js-favourite-tags-' + type + '-' + $('#favourite-form-tags-target-id').val());

                this.hideEditTags();
                tags.find('.js-favourite-tag-user').detach();
                var edit = tags.find('.js-favourite-tag-edit');
                $.each(result.aTags, function (k, v) {
                    edit.before('<li class="' + type + '-tags-user js-favourite-tag-user">, <a rel="tag" href="' + v.url + '">' + v.tag + '</a></li>');
                });

                // *depricated* //ls.hook.run('ls_favourite_save_tags_after', [form, result], this);
            }
        }.bind(this));
        return false;
    };

    this.hideTags = function (targetType, targetId) {
        var tags = $('.js-favourite-tags-' + targetType + '-' + targetId);

        tags.find('.js-favourite-tag-user').detach();
        tags.find('.js-favourite-tag-edit').hide();
        this.hideEditTags();
    };

    this.showTags = function (targetType, targetId) {
        $('.js-favourite-tags-' + targetType + '-' + targetId).find('.js-favourite-tag-edit').show();
    };

    return this;
}).call(alto.favourite || {}, jQuery);