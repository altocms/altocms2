{if $bBlogsUseOrder}
    <th class="hidden-xs">
        <a href="{$sBlogsRootPage}?order=blog_rating&order_way={if $sBlogOrder=='blog_rating'}{$sBlogOrderWayNext}{else}{$sBlogOrderWay}{/if}"
           class="link {if $sBlogOrder=='blog_rating'}{$sBlogOrderWay}{/if}">{$aLang.blogs_rating}</a>
    </th>
{else}
    <th class="hidden-xs">{$aLang.blogs_rating}</th>
{/if}