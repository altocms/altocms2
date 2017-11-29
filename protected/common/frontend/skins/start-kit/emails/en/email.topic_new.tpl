The user <a href="{$oUserTopic->getProfileUrl()}">{$oUserTopic->getDisplayName()}</a> posted a new topic -
<a href="{$oTopic->getUrl()}">{$oTopic->getTitle()|escape:'html'}</a><br> in a blog <b>«{$oBlog->getTitle()|escape:'html'}»</b>

<br><br>
Best regards, site administration <a href="{C::get('path.root.url')}">{C::get('view.name')}</a>