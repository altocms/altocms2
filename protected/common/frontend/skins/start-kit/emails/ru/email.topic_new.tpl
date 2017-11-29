Пользователь <a href="{$oUserTopic->getProfileUrl()}">{$oUserTopic->getDisplayName()}</a> опубликовал в блоге
<b>«{$oBlog->getTitle()|escape:'html'}»</b> новый топик -  <a href="{$oTopic->getUrl()}">{$oTopic->getTitle()|escape:'html'}</a><br>

<br><br>
С уважением, администрация сайта <a href="{C::get('path.root.url')}">{C::get('view.name')}</a>