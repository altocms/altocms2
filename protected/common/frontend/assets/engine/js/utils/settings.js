/**
 * Различные настройки
 */

;var ls = ls || {};

ls.settings = (function ($) {

    this.presets = {};

    this.get = function (editor, preset) {
        if (this.presets[editor]) {
            if (preset && this.presets[editor][preset]) {
                return this.presets[editor][preset]()
            }
            return this.presets[editor]['default']()
        } else {
            return {};
        }
    };

    // *** markitup *** //
    this.presets.markitup = {};
    this.presets.markitup['default'] = function(){
        return     {
            onShiftEnter:   {keepDefault: false, replaceWith: '<br />\n'},
            onCtrlEnter:    {keepDefault: false, openWith: '\n<p>', closeWith: '</p>'},
            onTab:          {keepDefault: false, replaceWith: '    '},
            markupSet: [
                {name: alto.lang.get('panel_title_h4'), className: 'editor-h4', openWith: '<h4>', closeWith: '</h4>' },
                {name: alto.lang.get('panel_title_h5'), className: 'editor-h5', openWith: '<h5>', closeWith: '</h5>' },
                {name: alto.lang.get('panel_title_h6'), className: 'editor-h6', openWith: '<h6>', closeWith: '</h6>' },
                {separator: '---------------' },
                { name:'snippets',  className: 'snippet-menu', dropMenu: [
                    {name: alto.lang.get('panel_user'),   className: 'editor-user', replaceWith: '<alto:user login="[![' + alto.lang.get('panel_user_promt') + ']!]"/>' },
                    {name: alto.lang.get('panel_photoset'),   className: 'editor-photoset', replaceWith: '<alto:photoset/>' },
                    {name: alto.lang.get('panel_spoiler'),  className: 'editor-spoiler', openWith: '<alto:spoiler title="">', closeWith: '</alto:spoiler>' }
                ]
                },
                {separator: '---------------' },
                {name: alto.lang.get('panel_b'),  className: 'editor-bold', key: 'B', openWith: '(!(<strong>|!|<b>)!)', closeWith: '(!(</strong>|!|</b>)!)' },
                {name: alto.lang.get('panel_i'),  className: 'editor-italic', key: 'I', openWith: '(!(<em>|!|<i>)!)', closeWith: '(!(</em>|!|</i>)!)'  },
                {name: alto.lang.get('panel_s'),  className: 'editor-stroke', key: 'S', openWith: '<s>', closeWith: '</s>' },
                {name: alto.lang.get('panel_u'),  className: 'editor-underline', key: 'U', openWith: '<u>', closeWith: '</u>' },
                {name: alto.lang.get('panel_quote'), className: 'editor-quote', key: 'Q', replaceWith: function (m) {
                    if (m.selectionOuter) return '<blockquote>' + m.selectionOuter + '</blockquote>'; else if (m.selection) return '<blockquote>' + m.selection + '</blockquote>'; else return '<blockquote></blockquote>'
                } },
                {name: alto.lang.get('panel_code'),   className: 'editor-code', openWith: '<(!(code|!|codeline)!)>', closeWith: '</(!(code|!|codeline)!)>' },
                {separator: '---------------' },
                {name: alto.lang.get('panel_list'),   className: 'editor-ul', openWith: '    <li>', closeWith: '</li>', multiline: true, openBlockWith: '<ul>\n', closeBlockWith: '\n</ul>' },
                {name: alto.lang.get('panel_list'),   className: 'editor-ol', openWith: '    <li>', closeWith: '</li>', multiline: true, openBlockWith: '<ol>\n', closeBlockWith: '\n</ol>' },
                {name: alto.lang.get('panel_list_li'), className: 'editor-li', openWith: '<li>', closeWith: '</li>' },
                {separator: '---------------' },
                //{name: alto.lang.get('panel_image'),  className: 'editor-picture', key: 'P', beforeInsert: function (h) {
                //    jQuery('#modal-upload_img').modal();
                //} },
                {name: alto.lang.get('panel_insert_image'),  className: 'editor-insert-picture', key: 'K', beforeInsert: function (h) {
                    jQuery('#js-alto-image-manager').modal();
                } },
                {name: alto.lang.get('panel_video'),  className: 'editor-video', replaceWith: '<video>[![' + alto.lang.get('panel_video_promt') + ':!:http://]!]</video>' },
                {name: alto.lang.get('panel_url'),    className: 'editor-link', key: 'L', openWith: '<a href="[![' + alto.lang.get('panel_url_promt') + ':!:http://]!]"(!( title="[![Title]!]")!)>', closeWith: '</a>', placeHolder: 'Your text to link...' },

                {separator: '---------------' },
                {name: alto.lang.get('panel_clear_tags'), className: 'editor-clean', replaceWith: function (markitup) {
                    return markitup.selection.replace(/<(.*?)>/g, "")
                } },
                {name: alto.lang.get('panel_cut'), className: 'editor-cut', replaceWith: function (markitup) {
                    if (markitup.selection) return '<cut name="' + markitup.selection + '">'; else return '<cut>'
                }}
            ]
        };
    };

    this.presets.markitup['comment'] = function(){
        return {
            onShiftEnter:   {keepDefault: false, replaceWith: '<br />\n'},
            onTab:          {keepDefault: false, replaceWith: '    '},
            markupSet: [
                {name: alto.lang.get('panel_b'), className: 'editor-bold', key: 'B', openWith: '(!(<strong>|!|<b>)!)', closeWith: '(!(</strong>|!|</b>)!)' },
                {name: alto.lang.get('panel_i'), className: 'editor-italic', key: 'I', openWith: '(!(<em>|!|<i>)!)', closeWith: '(!(</em>|!|</i>)!)'  },
                {name: alto.lang.get('panel_s'), className: 'editor-stroke', key: 'S', openWith: '<s>', closeWith: '</s>' },
                {name: alto.lang.get('panel_u'), className: 'editor-underline', key: 'U', openWith: '<u>', closeWith: '</u>' },
                {separator: '---------------' },
                {name: alto.lang.get('panel_quote'), className: 'editor-quote', key: 'Q', replaceWith: function (m) {
                    if (m.selectionOuter) return '<blockquote>' + m.selectionOuter + '</blockquote>'; else if (m.selection) return '<blockquote>' + m.selection + '</blockquote>'; else return '<blockquote></blockquote>'
                } },
                {name: alto.lang.get('panel_code'), className: 'editor-code', openWith: '<(!(code|!|codeline)!)>', closeWith: '</(!(code|!|codeline)!)>' },
                //{name: alto.lang.get('panel_image'), className: 'editor-picture', key: 'P', beforeInsert: function (h) {
                //    jQuery('#modal-upload_img').modal();
                //} },
                {name: alto.lang.get('panel_insert_image'),  className: 'editor-insert-picture', key: 'K', beforeInsert: function (h) {
                    jQuery('#js-alto-image-manager').modal();
                } },
                {name: alto.lang.get('panel_url'), className: 'editor-link', key: 'L', openWith: '<a href="[![' + alto.lang.get('panel_url_promt') + ':!:http://]!]"(!( title="[![Title]!]")!)>', closeWith: '</a>', placeHolder: 'Your text to link...' },
                {name: alto.lang.get('panel_user'), className: 'editor-user', replaceWith: '<ls user="[![' + alto.lang.get('panel_user_promt') + ']!]" />' },
                {separator: '---------------' },
                {name: alto.lang.get('panel_clear_tags'), className: 'editor-clean', replaceWith: function (markitup) {
                    return markitup.selection.replace(/<(.*?)>/g, "")
                } }
            ]
        }
    };

    // *** tinimce *** //
    this.presets.tinymce = {};
    this.presets.tinymce['default'] = function(){
        return {
            selector:           '.js-editor-wysiwyg',
            theme:              'modern',
            relative_urls:      false,
            menubar:            false,
            toolbar: "undo styleselect | bold italic strikethrough underline blockquote alto_snippets | alignleft aligncenter alignright | bullist numlist table | link unlink | alto_image media | code pagebreak ",
            toolbar_items_size: 'small',
            image_advtab: false,
            style_formats: [
                {title: alto.lang.get('panel_title_h4'), block: 'h4'},
                {title: alto.lang.get('panel_title_h5'), block: 'h5'},
                {title: alto.lang.get('panel_title_h6'), block: 'h6'}
            ],
            object_resizing:    true,
            forced_root_block:  '', // Needed for 3.x
            force_p_newlines:   true,
            custom_elements : "alto:photoset",
            self_closing_elements: "alto:photoset",
            force_br_newlines:  false,
            plugins: "advlist autolink autosave link lists media pagebreak autoresize table code alto_image alto_snippets alto_photoset alto_username alto_spoiler",
            //convert_urls: false,
            extended_valid_elements: "embed[src|type|allowscriptaccess|allowfullscreen|width|height],alto:photoset[from|to|style|position|topic],alto:user[login|style],alto:spoiler[style|title]",
            pagebreak_separator: "<cut>",
            media_strict: false,
            language: TINYMCE_LANG ? TINYMCE_LANG : 'ru',
            inline_styles: false,
            setup: function (editor) {
                editor.on('change', function() {
                    tinyMCE.triggerSave();
                });
            },
            formats: {
                underline:      {inline: 'u', exact: true},
                strikethrough:  {inline: 's', exact: true}
            }
        }
    };

    this.presets.tinymce['comment'] = function(){
        return {
            selector:           '.js-editor-wysiwyg',
            theme:              'modern',
            relative_urls:      false,
            menubar:            false,
            toolbar: "undo redo | styleselect | bold italic strikethrough underline blockquote | alignleft aligncenter alignright | bullist numlist table | link unlink | altoimage media | code ",
            toolbar_items_size: 'small',
            image_advtab: false,
            style_formats: [
                {title: 'Head 1', block: 'h4'},
                {title: 'Head 2', block: 'h5'},
                {title: 'Head 3', block: 'h6'}
            ],
            object_resizing:    'table,img.mce-object-video,div',
            forced_root_block:  '', // Needed for 3.x
            force_p_newlines:   true,
            force_br_newlines:  false,
            plugins: "advlist autolink autosave link lists media pagebreak autoresize table code altoimage",
            extended_valid_elements: "embed[src|type|allowscriptaccess|allowfullscreen|width|height]",
            pagebreak_separator: "<cut>",
            media_strict: false,
            language: TINYMCE_LANG ? TINYMCE_LANG : 'ru',
            inline_styles: false,
            formats: {
                underline:      {inline: 'u', exact: true},
                strikethrough:  {inline: 's', exact: true}
            },
            autoresize_min_height: '80px',
            setup: function (editor) {
                editor.on('keyup', function (e) {
                    var key = e.keyCode || e.which;
                    if (e.ctrlKey && (key == 13)) {
                        $('#comment-button-submit').click();
                    }
                    return false;
                });
            }
        }
    };

    this.getMarkitup = function() {
        return this.get('markitup');
    };

    this.getMarkitupComment = function() {
        return this.get('markitup', 'comment');
    };

    this.getTinymce = function() {
        return this.get('tinymce');
    };

    this.getTinymceComment = function() {
        return this.get('tinymce', 'comment');
    };

    return this;
}).call(alto.settings || {},jQuery);