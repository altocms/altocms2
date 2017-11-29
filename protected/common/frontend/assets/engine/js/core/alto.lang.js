;var alto = alto || {};

/**
 * Доступ к языковым текстовкам (предварительно должны быть прогружены в шаблон)
 */
alto.lang = (function ($) {
    /**
     * Набор текстовок
     */
    this.msgs = {};

    /**
     * Загрузка текстовок
     */
    this.load = function (msgs) {
        $.extend(true, this.msgs, msgs);
    };

    /**
     * Получить текстовку
     */
    this.get = function (name, replace) {
        if (this.msgs[name]) {
            var value = this.msgs[name];
            if (replace) {
                value = value.substituteOf(replace);
            }
            return value;
        }
        return '';
    };

    return this;
}).call(alto.lang || {}, jQuery);
