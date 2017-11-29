/**
 * Топик
 */

;var alto = alto || {};

alto.topic = (function ($) {
    "use strict";

    /**
     * Дефолтные опции
     */
    var defaults = {
        // Роутеры
        routers: {
            preview: alto.routerUrl('ajax') + 'preview/topic/'
        },

        // Селекторы
        selectors: {
            previewPlace: '.js-topic-preview-place',
            previewImage: '.js-topic-preview-image',
            previewImageLoader: '.js-topic-preview-loader',
            previewTopicTextButton: '.js-topic-preview-text-button',
            previewTopicTextHideButton: '.js-topic-preview-text-hide-button',
            addTopicTitle: '.js-topic-add-title'
        }
    };

    /**
     * Инициализация
     *
     * @param  {Object} options Опции
     */
    this.init = function (options) {
        var self = this;

        this.options = $.extend({}, defaults, options);

        // Подгрузка избражений-превью
        $(this.options.selectors.previewImage).each(function () {
            $(this).imagesLoaded(function () {
                var $this = $(this),
                    $preview = $this.closest(self.options.selectors.previewImageLoader).removeClass('loading');

                $this.height() < $preview.height() && $this.css('top', ($preview.height() - $this.height()) / 2);
            });
        });

        // Превью текста
        $(this.options.selectors.previewTopicTextButton).on('click', function (e) {
            self.showPreviewText(this, self.options.selectors.previewPlace);
            return false;
        });

        // Закрытие превью текста
        $(document).on('click', this.options.selectors.previewTopicTextHideButton, function (e) {
            self.hidePreviewText();
            return false;
        });

        // Подгрузка информации о выбранном блоге при создании топика
        $(this.options.selectors.addTopicTitle).on('change', function (e) {
            alto.blog.loadInfo($(this).val());
        });
    };

    /**
     * Превью текста
     *
     * @param form
     * @param previewPlace
     */
    this.showPreviewText = function (form, previewPlace) {
        var more = {};

        if (form) {
            form = $(form).closest('form');
        }
        previewPlace = $(previewPlace);

        if (form.length && previewPlace.length) {
            more.error = function(){
                alto.progressDone();
                alto.msg.error(null, 'System error #1001');
            };

            alto.progressStart();
            alto.ajaxSubmit(this.options.routers.preview, form, function (result) {
                alto.progressDone();
                if (!result) {
                    alto.msg.error(null, 'System error #1001');
                } else if (result.bStateError) {
                    alto.msg.error(null, result.sMsg);
                } else {
                    previewPlace.html(result.sText).show();

                    // *depricated* //ls.hook.run('ls_topic_preview_after', [form, previewPlace, result]);
                }
            }, more);
        }
    };

    /**
     * Закрытие превью
     */
    this.hidePreviewText = function () {
        $('#topic-text-preview').hide();
    };

    this.editUrl = function(button) {
        var parent = $(button).parents('p').first();
        if (!parent.length) {
            parent = $(button).parent();
        }
        parent.find('.b-topic_url_demo-edit').hide();
        parent.find('[name=topic_url]').show().focus();
    };

    this.shortUrl = function(url) {
        prompt('URL:', url);
        return false;
    };

    /**
     * Remove topic with id
     *
     * @param id
     * @param title
     */
    this.remove = function(id, title) {
        var text = alto.lang.get('topic_delete_confirm_text', {title: title});

        if (title) {
            text = alto.lang.get('topic_delete_confirm_text', {title: '"' + title + '"'});
        } else {
            text = alto.lang.get('topic_delete_confirm_text', {title: null})
        }
        alto.modal.confirm({
            title: alto.lang.get('topic_delete_confirm_title'),
            message: text
        }, function(confirm) {
                if (confirm) {
                    location.href = alto.routerUrl('content') + 'delete/' + id + '/?security_key=' + ALTO_SECURITY_KEY;
                }
            }
        );
    };

    $(function(){
        alto.topic.init();
    });

    return this;
}).call(alto.topic || {}, jQuery);