You have sent a request to change user email <a href="{$oUser->getProfileUrl()}">{$oUser->getDisplayName()}</a> on site
<a href="{C::get('path.root.url')}">{C::get('view.name')}</a>.<br/>
Old email: <b>{$oChangemail->getMailFrom()}</b><br/>
New email: <b>{$oChangemail->getMailTo()}</b><br/>

<br/>
To confirm the email change, please click here:
<a href="{router page='profile'}changemail/confirm-from/{$oChangemail->getCodeFrom()}/">{router page='profile'}changemail/confirm-from/{$oChangemail->getCodeFrom()}/</a>

<br/><br/>
Best regards, site administration <a href="{C::get('path.root.url')}">{C::get('view.name')}</a>