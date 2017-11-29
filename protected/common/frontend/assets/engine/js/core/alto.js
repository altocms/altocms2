/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */
;var alto = alto || {};

(function(){
    "use strict";
    // bind function for old browsers
    if (!Function.prototype.bind) {
        Function.prototype.bind = function (context) {
            var fn = this;

            if (jQuery.type(fn) !== 'function') {
                throw new TypeError('Function.prototype.bind: call on non-function');
            }
            if (jQuery.type(context) === 'null') {
                throw new TypeError('Function.prototype.bind: cant be bound to null');
            }

            return function () {
                return fn.apply(context, arguments);
            };
        };
        alto.nativeBind = false;
    } else {
        alto.nativeBind = true;
    }

    /**
     *
     * @param replacement
     * @param p
     *
     * @returns {String}
     */
    String.prototype.substituteOf = function (replacement, p) {
        var thisString = this;

        p = typeof(p) === 'string' ? p : '';

        jQuery.each(replacement, function (index) {
            var tk = p ? p.split('/') : [];
            tk[tk.length] = index;
            var tp = tk.join('/');
            if (typeof(replacement[index]) === 'object') {
                thisString = thisString.substituteOf(replacement[index], tp);
            } else {
                thisString = thisString.replace((new RegExp('%%' + tp + '%%', 'g')), replacement[index]);
            }
        });
        return thisString;
    };

    // Create method outerHTML()
    if (!('outerHTML' in document.documentElement)) {
        Object.defineProperty(Element.prototype, 'outerHTML', {
            get: function() {
                return new XMLSerializer().serializeToString(this);
            }
        });
    }

})();

alto = (function ($) {
    "use strict";
    var $that = this,
        registryData = {},
        readyChain = jQuery.Deferred();

    this.ready = function(fn) {
        readyChain.then(fn);
        return this;
    };

    this.set = function(name, value) {
        registryData[name] = value;
        return this;
    };

    this.get = function(name) {
        if(name in registryData) {
            return registryData[name];
        }
        return null;
    };

    /**
     * Log info
     */
    this.log = function () {
        // Modern browsers
        if (typeof console !== 'undefined' && typeof console.log === 'function') {
            Function.prototype.bind.call(console.log, console).apply(console, arguments);
        } else
        // IE8
        if (!alto.nativeBind && typeof console !== 'undefined' && typeof console.log === 'object') {
            Function.prototype.call.call(console.log, console, Array.prototype.slice.call(arguments));
        } else {
            //alert(msg);
        }
    };

    /**
     * Debug info
     */
    this.debug = function () {
        if (alto.options.debug) {
            alto.log.apply(this, arguments);
        }
    };

    this.registry = {
        set: function(name, value) {
            return $that.set(name, value);
        },
        get: function(name) {
            return $that.get(name);
        }
    };
    return this;
}).call(alto || {}, jQuery);

/**
 * Управление всплывающими сообщениями
 */
alto.msg = (function ($) {
    /**
     * Опции
     */
    this.options = {
        classNotice: 'n-notice',
        classError: 'n-error'
    };

    /**
     * Отображение информационного сообщения
     */
    this.notice = function (title, msg) {
        $.notifier.broadcast(title, msg, this.options.classNotice);
    };

    /**
     * Отображение сообщения об ошибке
     */
    this.error = function (title, msg) {
        $.notifier.broadcast(title, msg, this.options.classError);
    };

    return this;
}).call(alto.msg || {}, jQuery);


/**
 * Методы таймера например, запуск функии через интервал
 */
alto.timer = (function ($) {

    this.aTimers = {};

    /**
     * Запуск метода через определенный период, поддерживает пролонгацию
     *
     * @param sUniqKey
     * @param fMethod
     * @param aParams
     * @param iSeconds
     */
    this.run = function (sUniqKey, fMethod, aParams, iSeconds) {
        var timer = {
            id: alto.uniqId(),
            callback: null,
            params: [],
            timeout: 1500
        };

        if (typeof sUniqKey === 'function') {
            // sUniqKey is missed
            timer.id = alto.uniqId();
            timer.callback = sUniqKey;
            timer.params = fMethod ? fMethod : timer.params;
            timer.timeout = parseFloat(aParams) > 0 ? parseFloat(aParams) * 1000 : timer.timeout;
        } else {
            timer.id = sUniqKey;
            timer.callback = fMethod;
            timer.params = aParams ? aParams : timer.params;
            timer.timeout = parseFloat(iSeconds) > 0 ? parseFloat(iSeconds) * 1000 : timer.timeout;
        }

        if (this.aTimers[timer.id]) {
            clearTimeout(this.aTimers[timer.id]);
            this.aTimers[timer.id] = null;
        }
        this.aTimers[timer.id] = setTimeout(function () {
            clearTimeout(this.aTimers[timer.id]);
            this.aTimers[timer.id] = null;
            timer.callback.apply(this, timer.params);
        }.bind(this), timer.timeout);
    };

    return this;
}).call(alto.timer || {}, jQuery);

/**
 * Функционал хранения js данных
 */
alto.registry = (function ($) {

    this.data = {};

    /**
     * Сохранение
     */
    this.set = function (name, value) {
        this.data[name] = value;
    };

    /**
     * Получение
     */
    this.get = function (name) {
        return this.data[name];
    };

    return this;
}).call(alto.registry || {}, jQuery);

/**
 * Вспомогательные функции
 */
alto.tools = (function ($) {

    /**
     * Переводит первый символ в верхний регистр
     */
    this.ucfirst = function (str) {
        var f = str.charAt(0).toUpperCase();
        return f + str.substr(1, str.length - 1);
    };

    /**
     * Выделяет все chekbox с определенным css классом
     */
    this.checkAll = function (cssclass, checkbox, invert) {
        $('.' + cssclass).each(function (index, item) {
            if (invert) {
                $(item).attr('checked', !$(item).attr("checked"));
            } else {
                $(item).attr('checked', $(checkbox).attr("checked"));
            }
        });
    };

    /**
     * Предпросмотр
     */
    this.textPreview = function (textSelector, save, previewArea) {
        var text = alto.cfg.wysiwyg ? tinyMCE.activeEditor.getContent() : $(textSelector).val(),
            ajaxUrl = alto.routerUrl('ajax') + 'preview/text/',
            ajaxOptions = {text: text, save: save};

        alto.progressStart();
        alto.ajax(ajaxUrl, ajaxOptions, function (result) {
            alto.progressDone();
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                alto.msg.error(result.sMsgTitle || 'Error', result.sMsg || 'Please try again later');
            } else {
                if (!previewArea) {
                    previewArea = '#text_preview';
                } else {
                    if ((typeof previewArea === 'string') && (previewArea.substr(0, 1) !== '#')) {
                        previewArea = '#' + previewArea;
                    }
                }
                var elementPreview = $(previewArea);
                if (elementPreview.length) {
                    elementPreview.html(result.sText);
                }
            }
        });
    };

    /**
     * Возвращает выделенный текст на странице
     */
    this.getSelectedText = function () {
        var text = '';
        if (window.getSelection) {
            text = window.getSelection().toString();
        } else if (window.document.selection) {
            var sel = window.document.selection.createRange();
            text = sel.text || sel;
            if (text.toString) {
                text = text.toString();
            } else {
                text = '';
            }
        }
        return text;
    };

    /**
     * Получает значение атрибута data
     */
    this.getOption = function (element, data, defaultValue) {
        var option = element.data(data);

        switch (option) {
            case 'true':
                return true;
            case 'false':
                return false;
            case undefined:
                return defaultValue;
            default:
                return option;
        }
    };

    this.getDataOptions = function (element, prefix) {
        var resultOptions = {},
            dataOptions = typeof element === 'string' ? $(element).data() : element.data(),
            str = '';

        prefix = prefix || 'option';
        for (var option in dataOptions) {
            // Remove 'option' prefix
            if (dataOptions.hasOwnProperty(option) && option.substring(0, prefix.length) === prefix) {
                str = option.substring(prefix.length);
                resultOptions[str.charAt(0).toLowerCase() + str.substring(1)] = dataOptions[option];
            }
        }

        return resultOptions;
    };

    this.timeRest = function (time) {
        var d, h, m, s;
        if (time < 60) {
            return this.sprintf('%2d sec', time);
        }
        s = time % 60;
        m = (time - s) / 60;
        if (m < 60) {
            return this.sprintf('%2d:%02d', m, s);
        }
        time = m;
        m = time % 60;
        h = (time - m) / 60;
        if (h < 24) {
            return this.sprintf('%2d:%02d:%02d', h, m, s);
        }
        time = h;
        h = time % 24;
        d = (time - h) / 24;
        return this.sprintf('%3d, %2d:%02d:%02d', d, h, m, s);
    };

    /**
     * Return a formatted string
     *
     * @returns {string}
     */
    this.sprintf = function () {
        //
        // +   original by: Ash Searle (http://hexmen.com/blog/)
        // + namespaced by: Michael White (http://crestidg.com)

        var regex = /%%|%(\d+\$)?([-+#0 ]*)(\*\d+\$|\*|\d+)?(\.(\*\d+\$|\*|\d+))?([scboxXuidfegEG])/g;
        var a = arguments, i = 0, format = a[i++];

        // pad()
        var pad = function (str, len, chr, leftJustify) {
            var padding = (str.length >= len) ? '' : new Array(1 + len - str.length >>> 0).join(chr);
            return leftJustify ? str + padding : padding + str;
        };

        // justify()
        var justify = function (value, prefix, leftJustify, minWidth, zeroPad) {
            var diff = minWidth - value.length;
            if (diff > 0) {
                if (leftJustify || !zeroPad) {
                    value = pad(value, minWidth, ' ', leftJustify);
                } else {
                    value = value.slice(0, prefix.length) + pad('', diff, '0', true) + value.slice(prefix.length);
                }
            }
            return value;
        };

        // formatBaseX()
        var formatBaseX = function (value, base, prefix, leftJustify, minWidth, precision, zeroPad) {
            // Note: casts negative numbers to positive ones
            var number = value >>> 0;
            prefix = prefix && number && {'2': '0b', '8': '0', '16': '0x'}[base] || '';
            value = prefix + pad(number.toString(base), precision || 0, '0', false);
            return justify(value, prefix, leftJustify, minWidth, zeroPad);
        };

        // formatString()
        var formatString = function (value, leftJustify, minWidth, precision, zeroPad) {
            if (precision !== null) {
                value = value.slice(0, precision);
            }
            return justify(value, '', leftJustify, minWidth, zeroPad);
        };

        // finalFormat()
        var doFormat = function (substring, valueIndex, flags, minWidth, _, precision, type) {
            var number = 0;
            var prefix = '';

            if (substring === '%%') return '%';

            // parse flags
            var leftJustify = false, positivePrefix = '', zeroPad = false, prefixBaseX = false;
            for (var j = 0; flags && j < flags.length; j++) {
                switch (flags.charAt(j)) {
                    case ' ':
                        positivePrefix = ' ';
                        break;
                    case '+':
                        positivePrefix = '+';
                        break;
                    case '-':
                        leftJustify = true;
                        break;
                    case '0':
                        zeroPad = true;
                        break;
                    case '#':
                        prefixBaseX = true;
                        break;
                    default:
                        break;
                }
            }

            // parameters may be null, undefined, empty-string or real valued
            // we want to ignore null, undefined and empty-string values
            if (!minWidth) {
                minWidth = 0;
            } else if (minWidth === '*') {
                minWidth = +a[i++];
            } else if (minWidth.charAt(0) === '*') {
                minWidth = +a[minWidth.slice(1, -1)];
            } else {
                minWidth = +minWidth;
            }

            // Note: undocumented perl feature:
            if (minWidth < 0) {
                minWidth = -minWidth;
                leftJustify = true;
            }

            if (!isFinite(minWidth)) {
                throw new Error('sprintf: (minimum-)width must be finite');
            }

            if (!precision) {
                precision = 'fFeE'.indexOf(type) > -1 ? 6 : (type === 'd') ? 0 : void(0);
            } else if (precision === '*') {
                precision = +a[i++];
            } else if (precision.charAt(0) === '*') {
                precision = +a[precision.slice(1, -1)];
            } else {
                precision = +precision;
            }

            // grab value using valueIndex if required?
            var value = valueIndex ? a[valueIndex.slice(0, -1)] : a[i++];

            switch (type) {
                case 's':
                    return formatString(String(value), leftJustify, minWidth, precision, zeroPad);
                case 'c':
                    return formatString(String.fromCharCode(+value), leftJustify, minWidth, precision, zeroPad);
                case 'b':
                    return formatBaseX(value, 2, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
                case 'o':
                    return formatBaseX(value, 8, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
                case 'x':
                    return formatBaseX(value, 16, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
                case 'X':
                    return formatBaseX(value, 16, prefixBaseX, leftJustify, minWidth, precision, zeroPad).toUpperCase();
                case 'u':
                    return formatBaseX(value, 10, prefixBaseX, leftJustify, minWidth, precision, zeroPad);
                case 'i':
                case 'd':
                {
                    number = parseInt(+value);
                    prefix = number < 0 ? '-' : positivePrefix;
                    value = prefix + pad(String(Math.abs(number)), precision, '0', false);
                    return justify(value, prefix, leftJustify, minWidth, zeroPad);
                }
                case 'e':
                case 'E':
                case 'f':
                case 'F':
                case 'g':
                case 'G':
                {
                    number = +value;
                    prefix = number < 0 ? '-' : positivePrefix;
                    var method = ['toExponential', 'toFixed', 'toPrecision']['efg'.indexOf(type.toLowerCase())];
                    var textTransform = ['toString', 'toUpperCase']['eEfFgG'.indexOf(type) % 2];
                    value = prefix + Math.abs(number)[method](precision);
                    return justify(value, prefix, leftJustify, minWidth, zeroPad)[textTransform]();
                }
                default:
                    return substring;
            }
        };

        return format.replace(regex, doFormat);
    };

    return this;
}).call(alto.tools || {}, jQuery);


/**
 * Дополнительные функции
 */
alto = (function ($) {
    var $that = this;

    /**
     * Глобальные опции
     */
    this.options = this.options || {};

    this.options.progressInit = false;
    this.options.progressType = 'syslabel';
    this.options.progressCnt  = 0;

    /**
     * Выполнение AJAX запроса, автоматически передает security key
     */
    this.ajax = function (url, params, callback, more) {
        more = more || {};
        params = params || {};
        params.security_key = alto.cfg.security_key;

        $.each(params, function (k, v) {
            if (typeof(v) === "boolean") {
                params[k] = v ? 1 : 0;
            }
        });

        if (url.indexOf('/') === 0) {
            url = alto.cfg.url.root + url;
        } else if (url.indexOf('http://') !== 0 && url.indexOf('https://') !== 0) {
            url = alto.routerUrl('ajax') + url ;
        }
        if (url.substring(url.length-1) !== '/') {
            url += '/';
        }

        var ajaxOptions = $.extend({}, {
            type: 'POST',
            url: url,
            data: params,
            dataType: 'json',
            success: callback || function () {
                alto.debug("ajax success: ");
                alto.debug.apply(this, arguments);
            }.bind(this),
            error: function (msg) {
                alto.debug("ajax error: ");
                alto.debug.apply(this, arguments);
                alto.msg.error(null, 'System error #1002'); // may be json parser error
                alto.progressDone(true);
            }.bind(this),
            complete: function (msg) {
                alto.debug("ajax complete: ");
                alto.debug.apply(this, arguments);
            }.bind(this)
        }, more);

        var beforeSendFunc = ajaxOptions.beforeSend ? ajaxOptions.beforeSend : null;
        ajaxOptions.beforeSend = function (xhr) {
            xhr.setRequestHeader('X-Powered-By', 'Alto CMS');
            xhr.setRequestHeader('X-Alto-Ajax-Key', alto.cfg.security_key);
            if (beforeSendFunc) {
                beforeSendFunc(xhr);
            }
        };

        return $.ajax(ajaxOptions);
    };

    this.ajaxGet = function (url, params, callback, more) {
        more = more || {};
        params = params || {};
        more.type = 'GET';
        return this.ajax(url, params, callback, more);
    };

    this.ajaxPost = function (url, params, callback, more) {
        more = more || {};
        params = params || {};
        more.type = 'POST';
        return this.ajax(url, params, callback, more);
    };

    /**
     * Выполнение AJAX отправки формы, включая загрузку файлов
     */
    this.ajaxSubmit = function (url, form, callback, more) {
        var success = null,
            progressDone = function() { }; // empty function for progress vizualization

        form = $(form);
        more = more || {};
        if (more && more.progress) {
            progressDone = alto.progressDone;
        }

        if (!url) {
            url = form.attr('action');
        }

        if (url.indexOf('http://') !== 0 && url.indexOf('https://') !== 0 && url.indexOf('/') !== 0) {
            url = alto.routerUrl('ajax') + url + '/';
        }

        var options = {
            type: 'POST',
            url: url,
            dataType: more.dataType || 'json',
            data: {
                security_key: alto.cfg.security_key
            },
            beforeSubmit: function (arr, form, options) {
                form.find('[type=submit]').prop('disabled', true).addClass('loading');
            }
        };

        if (typeof callback !== 'function') {
            callback = null;
        }
        options.success = function (result, status, xhr, form) {
            alto.debug("ajax success: ");
            alto.debug.apply(this, arguments);
            progressDone();
            form.find('[type=submit]').prop('disabled', false).removeClass('loading');
            if (callback) {
                callback(result, status, xhr, form);
            } else {
                if (!result) {
                    alto.msg.error(null, 'System error #1001');
                } else if (result.bStateError) {
                    alto.msg.error(null, result.sMsg);

                    if (more && more.warning) {
                        more.warning(result, status, xhr, form);
                    }
                } else {
                    if (result.sMsg) {
                        alto.msg.notice(null, result.sMsg);
                    }
                }
            }
        }.bind(this);

        options.error = function () {
            form.find('[type=submit]').prop('disabled', false).removeClass('loading');
            if (more.progress) {
                alto.progressDone();
            }
            alto.debug("ajax error: ");
            alto.debug.apply(this, arguments);
            if ($.type(more.error) === 'function') {
                more.error();
            }
        }.bind(this);

        if (more.progress) {
            alto.progressStart();
        }
        form.ajaxSubmit(options);
    };

    /**
     * Создание ajax формы
     *
     * @param  {string}          url      Ссылка
     * @param  {jquery, string}  form     Селектор формы либо объект jquery
     * @param  {Function}        callback Success callback (if result and not result.bStateError)
     * @param  {type}            [more]   Дополнительные параметры
     */
    this.ajaxForm = function (url, form, callback, more) {
        form = typeof form === 'string' ? $(form) : form;
        more = $.extend({ progress: true }, more);

        form.on('submit', function (e) {
            alto.ajaxSubmit(url, form, function(result, status, xhr, form){
                if (!result) {
                    alto.msg.error(null, 'System error #1001');
                } else if (result.bStateError) {
                    alto.msg.error(null, result.sMsg);

                    if (more && more.warning) {
                        more.warning(result, status, xhr, form);
                    }
                } else {
                    if (result.sMsg) {
                        alto.msg.notice(null, result.sMsg);
                    }
                    if ($.type(callback) === 'function') {
                        callback(result, status, xhr, form);
                    }
                }
            }, more);
            e.preventDefault();
        });
    };

    /**
     * Uploads image
     */
    this.ajaxUploadImg = function (form, sToLoad) {
        form = $(form).closest('form');
        var modalWin = form.parents('.modal').first();
        alto.progressStart();
        $that.ajaxSubmit('upload/image/', form, function (result) {
            alto.progressDone();
            if (!result) {
                alto.msg.error(null, 'System error #1001');
            } else if (result.bStateError) {
                $that.msg.error(result.sMsgTitle, result.sMsg);
            } else {
                $that.insertToEditor(result.sText);
                modalWin.find('input[type="text"], input[type="file"]').val('');
                modalWin.modal('hide');
            }
        });
    };

    this.insertImageToEditor = function(button) {
        var form = $(button).is('form') ? $(button) : $(button).parents('form').first(),
            url = form.find('[name=img_url]').val(),
            align = form.find('[name=align]').val(),
            title = form.find('[name=title]').val(),
            size = parseInt(form.find('[name=img_width]').val(), 10),
            html = '';

        if (url && url !== 'http://' && url !== 'https://') {
            align = (align === 'center') ? 'class="image-center"' : 'align="' + align + '"';
            size = (size === 0) ? '' : 'width="' + size + '%"';
            html = '<img src="' + url + '" title="' + title + '" ' + align + ' ' + size + ' />';
            form.find('[name=img_url]').val('');
            title = form.find('[name=title]').val('');

            alto.insertToEditor(html);
            form.parents('.modal').first().modal('hide');
        }
        return false;
    };

    /**
     * Insert html
     *
     * @param html
     */
    this.insertToEditor = function(html) {
        $.markItUp({replaceWith: html});
    };

    /**
     * Saves config data
     *
     * @param params
     * @param callback
     * @param more
     * @returns {*}
     */
    this.ajaxConfig = function(params, callback, more) {
        var url = alto.routerUrl('admin') + '/ajax/config/';
        var args = params;
        params = {
            keys: []
        };
        $.each(args, function(key, val) {
            key = key.replace(/\./g, '--');
            params.keys.push(key);
            params[key] = val;
        });
        return alto.ajaxPost(url, params, callback, more);
    };

    /**
     * Returns URL of action
     *
     * @param action
     */
    this.routerUrl = function(action) {
        /*
        if (window.aRouter && window.aRouter[action]) {
            return window.aRouter[action];
        } else {
            return alto.cfg.url.root + action + '/';
        }
        */
        return alto.cfg.url.ajax + action + '/';
    };

    /**
     * Returns asset url
     *
     * @param asset
     * @returns {*}
     */
    this.getAssetUrl = function(asset) {
        if (this.cfg && this.cfg.assets && this.cfg.assets[asset]) {
            return this.cfg.assets[asset];
        }
    };

    /**
     * Returns path of asset
     *
     * @param asset
     * @returns {string}
     */
    this.getAssetPath = function(asset) {
        var url = this.getAssetUrl(asset);
        if (url) {
            return url.substring(0, url.lastIndexOf('/'));
        }
    };

    /**
     * Loads asset script
     *
     * @param asset
     * @param success
     */
    this.loadAssetScript = function (asset, success) {
        var url = alto.getAssetUrl(asset);
        if (!url) {
            alto.debug('error: [asset "' + asset + '"] not defined');
        } else {
            $.ajax({
                url: url,
                dataType: 'script'
            })
                .done(function () {
                    alto.debug('success: [asset "' + asset + '"] ajax loaded');
                    success();
                })
                .fail(function () {
                    alto.debug('error: [asset "' + asset + '"] ajax not loaded');
                });
        }
    };

    /**
     * Begins to show progress
     */
    this.progressStart = function() {

        if (!$that.options.progressInit) {
            $that.options.progressInit = true;
            if ($that.options.progressType === 'syslabel') {
                $.SysLabel.init({
                    css: {
                        'z-index': $that.maxZIndex('.modal')
                    }
                });
            }
        }
        if (++$that.options.progressCnt === 1) {
            if ($that.options.progressType === 'syslabel') {
                $.SysLabel.show();
            } else {
                NProgress.start();
            }
        }
    };

    /**
     * Ends to show progress
     */
    this.progressDone = function(final) {

        if ((--$that.options.progressCnt <= 0) || final) {
            if ($that.options.progressType === 'syslabel') {
                $.SysLabel.hide();
            } else {
                NProgress.done();
            }
            $that.options.progressCnt = 0;
        }
    };

    /**
     * Create unique ID
     *
     * @returns {string}
     */
    this.uniqId = function () {
        return 'id-' + new Date().valueOf() + '-' + Math.floor(Math.random() * 1000000000);
    };

    /**
     * Calculate max z-index
     *
     * @param selector
     * @returns {number}
     */
    this.maxZIndex = function(selector) {
        var elements = $.makeArray(selector ? $(selector) : document.getElementsByTagName("*"));
        var max = 0;
        $.each(elements, function(index, item){
            var val = parseFloat($(item).css('z-index')) || 0;
            if (val > max) {
                max = val;
            }
        });
        return max;
    };

    return this;
}).call(alto || {}, jQuery);

(alto.options || {}).debug = 1;

var ALTO_SECURITY_KEY = ALTO_SECURITY_KEY || null;

alto.cfg = alto.cfg || { };
alto.cfg.security_key = alto.cfg.security_key || ALTO_SECURITY_KEY;

var ls = alto || { };

// EOF