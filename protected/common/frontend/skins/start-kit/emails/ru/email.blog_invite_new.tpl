Пользователь «<a href="{$oUserFrom->getProfileUrl()}">{$oUserFrom->getDisplayName()}</a>»</b> приглашает вас вступить в блог
<a href="{$oBlog->getUrlFull()}">"{$oBlog->getTitle()|escape:'html'}"</a>.
<br /><br />
<a href='{$sPath}'>Посмотреть приглашение</a> (Не забудьте предварительно авторизоваться!)
<br />
С уважением, администрация сайта <a href="{C::get('path.root.url')}">{C::get('view.name')}</a>