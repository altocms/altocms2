The user <a href="{$oUserFrom->getProfileUrl()}">{$oUserFrom->getDisplayName()}</a> left a new comment to the letter
<b>«{$oTalk->getTitle()|escape:'html'}»</b>, you can read it by clicking on
<a href="{router page='talk'}read/{$oTalk->getId()}/#comment{$oTalkComment->getId()}">this link</a><br>
{if C::get('sys.mail.include_talk')}
    Message:
    <i>{$oTalkComment->getText()}</i>
    <br>
{/if}
Do not forget to register before!
<br><br>
Best regards, site administration <a href="{C::get('path.root.url')}">{C::get('view.name')}</a>