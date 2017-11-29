/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 */
;
"use strict";

var alto = alto || {};
/**
 * Автокомплитер
 */
alto.autocomplete = (function ($) {

    this.stripHTML = function(oldString) {
        return oldString.replace(/<\/?[^>]+>/g,'');
    };

    this.ajaxFunc = function(element, url, params, callback, more) {
        alto.ajax(url, params, callback, more);
    };

    /**
     * Добавляет автокомплитер к полю ввода
     */
    this.add = function (element, sPath, multiple, ajaxFunc) {
        if (!ajaxFunc) {
            ajaxFunc = this.ajaxFunc;
        }
        if (multiple) {
            element.bind('keydown', function (event) {
                if (event.keyCode === $.ui.keyCode.TAB && $(this).data('autocomplete').menu.active) {
                    event.preventDefault();
                }
            })
                .autocomplete({
                    source: function (request, response) {
                        ajaxFunc(element, sPath, {value: alto.autocomplete.extractLast(request.term)}, function (data) {
                            response(data.aItems);
                        });
                    },
                    search: function () {
                        var term = alto.autocomplete.extractLast(this.value);
                        if (term.length < 2) {
                            return false;
                        }
                    },
                    focus: function () {
                        return false;
                    },
                    select: function (event, ui) {
                        var terms = alto.autocomplete.split(this.value);
                        terms.pop();
                        terms.push(ui.item.value);
                        terms.push("");
                        this.value = terms.join(", ");
                        return false;
                    }
                }).bind(
                'autocompleteclose',
                function(){
                    $(this).val(alto.autocomplete.stripHTML($(this).val()));
                });
        } else {
            element.autocomplete({
                source: function (request, response) {
                    ajaxFunc(element, sPath, {value: alto.autocomplete.extractLast(request.term)}, function (data) {
                        response(data.aItems);
                    });
                }
            }).bind(
                'autocompleteclose',
                function(){
                    $(this).val(alto.autocomplete.stripHTML($(this).val()));
                });
        }
    };

    this.split = function (val) {
        return val.split(/,\s*/);
    };

    this.extractLast = function (term) {
        return alto.autocomplete.split(term).pop();
    };

    return this;
}).call(alto.autocomplete || {}, jQuery);

// EOF