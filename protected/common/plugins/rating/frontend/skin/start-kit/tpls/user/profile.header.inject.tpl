{$sClasses = ''}
{if $oUserProfile->getRating()>=0}
    {$sClasses = "$sClasses vote-count-positive "}
{else}
    {$sClasses = "$sClasses vote-count-negative "}
{/if}
{if $oVote AND ($oVote->getDirection()>0)}
    {$sClasses = "$sClasses voted voted-up "}
{elseif $oVote AND ($oVote->getDirection()<0)}
    {$sClasses = "$sClasses voted voted-down "}
{elseif $oVote}
    {$sClasses = "$sClasses voted "}
{/if}
<div class="small pull-right vote js-vote {$sClasses}" data-target-type="user" data-target-id="{$oUserProfile->getId()}">
    <div class="text-muted vote-label">{$aLang.user_rating}</div>

    <a href="#" class="vote-up js-vote-up" ><span class="glyphicon glyphicon-plus-sign"></span></a>

    <div class="vote-count js-vote-rating" title="{$aLang.user_vote_count}: {$oUserProfile->getCountVote()}">
        {if $oUserProfile->getRating() > 0}+{/if}{$oUserProfile->getRating()|number_format:{Config::Get('view.rating_length')}}
    </div>
    {if C::Get('plugin.rating.user.dislike')}
    <a href="#" class="vote-down js-vote-down"><span class="glyphicon glyphicon-minus-sign"></span></a>
    {/if}
</div>

<div class="small pull-right strength">
    <div class="text-muted vote-label">{$aLang.user_skill}</div>
    <div class="text-info count" id="user_skill_{$oUserProfile->getId()}">{$oUserProfile->getSkill()|number_format:{Config::Get('view.skill_length')}}</div>
</div>