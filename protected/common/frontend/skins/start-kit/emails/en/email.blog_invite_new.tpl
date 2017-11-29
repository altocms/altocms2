The user «<a href="{$oUserFrom->getProfileUrl()}">{$oUserFrom->getDisplayName()}</a>»</b> invites you to join the blog
<a href="{$oBlog->getUrlFull()}">"{$oBlog->getTitle()|escape:'html'}"</a>.
<br/><br/>
<a href='{$sPath}'>Have a look at the invitation</a> (Don't forget to register before!)
<br/>
Best regards, site administration <a href="{C::get('path.root.url')}">{C::get('view.name')}</a>