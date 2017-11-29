The user <a href="{$oUserComment->getProfileUrl()}">{$oUserComment->getDisplayName()}</a> left a new comment to topic
<b>«{$oTopic->getTitle()|escape:'html'}»</b>, you can read it by clicking on
<a href="{if C::get('module.comment.nested_per_page')}{router page='comments'}{else}{$oTopic->getUrl()}#comment{/if}{$oComment->getId()}">this link</a>
<br>
{if C::get('sys.mail.include_comment')}
    Message:
    <i>{$oComment->getText()}</i>
{/if}

{if $sSubscribeKey}
    <br>
    <br>
    <a href="{router page='subscribe'}unsubscribe/{$sSubscribeKey}/">Unsubscribe from new comments to this topic</a>
{/if}

<br><br>
Best regards, site administration <a href="{C::get('path.root.url')}">{C::get('view.name')}</a>