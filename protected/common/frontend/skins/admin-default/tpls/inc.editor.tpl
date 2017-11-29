{if Config::Get('view.wysiwyg')}
    {if !$sImgToLoad}
        {assign var="sImgToLoad" value="topic_text"}
    {/if}
    {if !$sSettingsTinymce}
        {assign var="sSettingsTinymce" value="ls.settings.getTinymce()"}
    {/if}
    <script type="text/javascript">
        jQuery(function(){
            ls.lang.load({lang_load name="panel_title_h4,panel_title_h5,panel_title_h6,panel_spoiler_title,panel_spoiler_text,panel_user,panel_user_promt,panel_photoset,panel_spoiler,panel_photoset_from,panel_photoset_to,panel_photoset_align,panel_photoset_align_left,panel_photoset_align_right,panel_photoset_align_both,panel_photoset_topic"});
        });
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
    </script>
{else}
    {if !$sImgToLoad}
        {assign var="sImgToLoad" value="topic_text"}
    {/if}
    {*{include_once file="modals/modal.upload_img.tpl" sToLoad=$sImgToLoad}*}

    {if !$sSettingsTinymce}
        {$sSettingsMarkitup="ls.settings.getMarkitup()"}
    {/if}
    <script type="text/javascript">
        jQuery(function ($) {
            ls.lang.load({lang_load name="panel_photoset,panel_spoiler,panel_b,panel_i,panel_u,panel_s,panel_url,panel_url_promt,panel_code,panel_video,panel_image,panel_cut,panel_quote,panel_list,panel_list_ul,panel_list_ol,panel_title,panel_clear_tags,panel_video_promt,panel_list_li,panel_image_promt,panel_user,panel_user_promt"});
            $('.js-editor-markitup').markItUp({$sSettingsMarkitup});
        });
    </script>
{/if}

{include_once file="modals/modal.insert_img.tpl" sToLoad=$sImgToLoad}