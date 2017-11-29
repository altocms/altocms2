<!DOCTYPE html>

{block name="layout_vars"}{/block}

<!--[if lt IE 7]>
<html class="no-js ie6 oldie" lang="{C::get('i18n.lang')}" dir="{C::get('i18n.dir')}"> <![endif]-->
<!--[if IE 7]>
<html class="no-js ie7 oldie" lang="{C::get('i18n.lang')}" dir="{C::get('i18n.dir')}"> <![endif]-->
<!--[if IE 8]>
<html class="no-js ie8 oldie" lang="{C::get('i18n.lang')}" dir="{C::get('i18n.dir')}"> <![endif]-->
<!--[if gt IE 8]><!-->
<html class="no-js" lang="{C::get('i18n.lang')}" dir="{C::get('i18n.dir')}"> <!--<![endif]-->

<head>
{block name="layout_head"}
{hook run='layout_head_begin'}

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{$sHtmlTitle}</title>

    <meta name="description" content="{$sHtmlDescription}">
    <meta name="keywords" content="{$sHtmlKeywords}">

    {hook run="html_head_tags"}

    {$aHtmlHeadFiles.css}

    <link href='//fonts.googleapis.com/css?family=Roboto:300,400,500,700&subset=latin,cyrillic' rel='stylesheet' type='text/css'>

    <link href="{asset file="img/favicon.png" theme=true}?v1" rel="shortcut icon"/>
    <link rel="search" type="application/opensearchdescription+xml" href="{router page='search'}opensearch/" title="{C::get('view.name')}"/>

    {if $aHtmlRssAlternate}
        <link rel="alternate" type="application/rss+xml" href="{$aHtmlRssAlternate.url}" title="{$aHtmlRssAlternate.title}">
    {/if}

    {if $sHtmlCanonical}
        <link rel="canonical" href="{$sHtmlCanonical}"/>
    {/if}

    {if $bRefreshToHome}
        <meta HTTP-EQUIV="Refresh" CONTENT="3; URL={C::get('path.root.url')}/">
    {/if}

    <script type="text/javascript">
        {strip}
        var ALTO_SECURITY_KEY = '{$ALTO_SECURITY_KEY}';

        var tinyMCE = tinymce = false;
        var TINYMCE_LANG = {if E::Module('Lang')->getLang() == 'ru'}'ru'{else}'en'{/if};

        var aRouter = [];
        {foreach $aRouter as $sController=>$sPath} aRouter['{$sController}']='{$sPath}';{/foreach}
        {/strip}

        window.FileAPI = {
            debug: false, // debug mode
            media: true,
            staticPath: "{E::Module('ViewerAsset')->assetFilePathUrl("{C::get('path.frontend.dir')}libs/vendor/jquery.fileapi/FileAPI/")}" // path to *.swf
        };
    </script>

    {hook run='layout_head_assets'}

    {foreach $aHtmlHeaScripts as $sHtmlHeaScript}
        {$sHtmlHeaScript}
    {/foreach}

    <script type="text/javascript">
        ls.lang.load({json var = $aLangJs});
        ls.registry.set('comment_max_tree', {json var=C::get('module.comment.max_tree')});
        ls.registry.set('widget_stream_show_tip', {json var=C::get('block.stream.show_tip')});
    </script>

    <!--[if lt IE 9]>
    <script src="{asset file='assets/js/respond.min.js'}"></script>
    <![endif]-->

    <!--[if IE 7]>
    <link rel="stylesheet" href="{C::get('path.static.skin')}/themes/default/icons/css/fontello-ie7.css">
    <![endif]-->
    <script>
        function toggleCodes(on) {
            var obj = document.getElementById('icons');
            if (on) {
                obj.className += ' codesOn';
            } else {
                obj.className = obj.className.replace(' codesOn', '');
            }
        }
    </script>

    {if C::get('view.header.top')=='fixed'}
        {$body_classes=$body_classes|cat:' i-fixed_navbar'}
    {/if}

    {if E::IsUser()}
        {$body_classes=$body_classes|cat:' alto-user-role-user'}

        {if E::IsAdmin()}
            {$body_classes=$body_classes|cat:' alto-user-role-admin'}
        {/if}
    {else}
        {$body_classes=$body_classes|cat:' alto-user-role-guest'}
    {/if}

    {if !\E::IsAdmin()}
        {$body_classes=$body_classes|cat:' alto-user-role-not-admin'}
    {/if}

{hook run='layout_head_end'}
{/block}
</head>
<body class="{$body_classes}">
{block name="layout_body"}
{hook run='layout_body_begin'}

{if E::IsUser()}
    {include file='modals/modal.write.tpl'}
    {include file='modals/modal.favourite_tags.tpl'}
{else}
    {include file='modals/modal.auth.tpl'}
{/if}
{include file='modals/modal.empty.tpl'}

{include file='commons/common.header_top.tpl'}

{if C::get('view.header.banner')}
    {wgroup group="topbanner"}
{/if}

{include file='commons/common.header_nav.tpl'}

<section id="container" class="{hook run='container_class'}">
    <div id="wrapper" class="container {hook run='wrapper_class'}">
        <div class="row">

            {if !$noSidebar AND $sidebarPosition == 'left'}
                {include file='commons/common.sidebar.tpl'}
            {/if}

            <div id="content" role="main" class="{if $noSidebar}col-md-12 col-lg-12{else}col-md-8 col-lg-8{/if} content{if $sidebarPosition == 'left'} content-right{/if}"
                 {if $sMenuItemSelect=='profile'}itemscope itemtype="http://data-vocabulary.org/Person"{/if}>

                {include file='commons/common.messages.tpl'}

                <div class="content-inner action-{$sAction}{if $sEvent} event-{$sEvent}{/if}{if $aParams[0]} params-{$aParams[0]}{/if}">
                    {include file='menus/menu.content.tpl'}

                    {block name="layout_content"}
                    {hook run='content_begin'}

                    {hook run='content_end'}
                    {/block}
                </div>
                <!-- /content-inner -->
            </div>
            <!-- /content -->

            {if !$noSidebar AND $sidebarPosition != 'left'}
                {include file='commons/common.sidebar.tpl'}
            {/if}

        </div>
        <!-- /row -->
    </div>
    <!-- /wrapper -->
</section>
<!-- /container -->

<footer id="footer" class="footer">
    <div class="container">
        <div class="row">

            <div class="col-sm-4 col-md-4 col-lg-3">
                <ul class="list-unstyled footer-list">
                    <li class="footer-list-header">{$aLang.footer_menu_navigate_title}</li>
                    {menu id='footer_site_menu' hideul=true}
                </ul>
            </div>

            <div class="col-sm-4 col-md-4 col-lg-3">
                <ul class="list-unstyled footer-list">
                    <li class="footer-list-header">{$aLang.footer_menu_navigate_info}</li>
                    {menu id='footer_info' hideul=true}
                </ul>
            </div>

            <div class="col-sm-4 col-md-4 col-lg-3">

            </div>

        </div>
        {hook run='footer_end'}
    </div>

    <div class="footer-bottom">
        <div class="container">
            <div class="row">
                <div class="col-sm-3 col-md-3 col-lg-3 footer-bottom-copyright" >
                    <img src="{asset file="img/alto.png" theme=true}" />
                    {hook run='copyright'}
                </div>

                <div class="col-sm-3 col-md-3 col-lg-3 text-right pull-right footer-bottom-copyright" >
                    Design by
                    <a href="http://creatime.org" target="_blank" >
                        <img src="{asset file="img/creatime.png" theme=true}" alt="дизайн студии Creatime.org" />
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>

{include file='commons/common.toolbar.tpl'}

{hook run='layout_body_end'}
{/block}
</body>
</html>
