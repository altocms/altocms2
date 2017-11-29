;var alto = alto || {};

jQuery(document).ready(function ($) {
    "use strict";
    var html = $('html'),
        body = $('body');

    html.removeClass('no-js');

    // Определение браузера
    if ($.browser.opera) {
        body.addClass('opera opera' + parseInt($.browser.version));
    }
    if ($.browser.mozilla) {
        body.addClass('mozilla mozilla' + parseInt($.browser.version));
    }
    if ($.browser.webkit) {
        body.addClass('webkit webkit' + parseInt($.browser.version));
    }
    if ($.browser.msie) {
        body.addClass('ie');
        if (parseInt($.browser.version) > 8) {
            body.addClass('ie' + parseInt($.browser.version));
        }
    }

    // Фикс бага с z-index у встроенных видео
    $('iframe').each(function () {
        var ifr_source = $(this).attr('src'),
            wmode = 'wmode=opaque';

        if (ifr_source) {
            if (ifr_source.indexOf('?') != -1) {
                $(this).attr('src', ifr_source + '&' + wmode);
            } else {
                $(this).attr('src', ifr_source + '?' + wmode);
            }
        }
    });

    /**
     * Tag search
     */
    $('.js-tag-search-form').submit(function () {
        var val = $(this).find('.js-tag-search').val();
        if (val) {
            window.location = alto.routerUrl('tag') + encodeURIComponent(val) + '/';
        }
        return false;
    });

    /**
     * Постраничная навигация, переходы вперёд-назад по Ctrl + →/←
     */
    alto.pagination.init({
        selectors: {
            pagination: '.js-pagination',
            next:       '.js-paging-next-page',
            prev:       '.js-paging-prev-page'
        }
    });

    // эмуляция placeholder'ов в IE
    if (html.hasClass('oldie')) {
        $('input[type=text], textarea').placeholder();
    }
});
