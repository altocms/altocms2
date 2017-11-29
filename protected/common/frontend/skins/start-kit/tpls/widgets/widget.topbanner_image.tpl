<div id="topbanner" role="banner" class="b-header-banner">

    {hook run='header_banner_begin'}

    <div class="container">
        {$sBackgroundImage=$aWidgetParams.image}
        {if $sBackgroundImage}
            {$sBackgroundImage="background:url({asset file=$sBackgroundImage});"}
        {/if}
        <div class="b-header-banner-inner jumbotron" style="{$sBackgroundImage}{$aWidgetParams.style}">
            <h1><a href="{C::get('path.root.url')}">{$aWidgetParams.title}</a></h1>
        </div>
    </div>

    {hook run='header_banner_end'}

</div>
