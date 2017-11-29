{if !$sSettingsTinymce}
    {assign var="sSettingsTinymce" value="ls.settings.getTinymce()"}
{/if}
<script type="text/javascript">
    $(function(){
        ls.lang.load({lang_load name="panel_spoiler_title,panel_spoiler_text,panel_user,panel_user_promt,panel_photoset,panel_spoiler,panel_photoset_from,panel_photoset_to,panel_photoset_align,panel_photoset_align_left,panel_photoset_align_right,panel_photoset_align_both,panel_photoset_topic"});
    });
    ls.lang.load({lang_load name="panel_title_h4,panel_title_h5,panel_title_h6"});
    if (!tinymce) {
        ls.loadAssetScript('tinymce_4', function(){
            jQuery(function(){
                tinymce.init({$sSettingsTinymce});
            });
        });
    } else {
        jQuery(function(){
            tinymce.init({$sSettingsTinymce});
        });
    }

    {if C::get('view.float_editor')}
    $(function(){
        ls.editor.float({
            topStep: {if C::get('view.header.top') == 'fixed'}60{else}0{/if},
            dif: 0,
            textareaClass: '.js-editor-wysiwyg',
            editorClass: '.mce-tinymce',
            headerClass: '.mce-toolbar-grp'
        });
    });
    {/if}
</script>
