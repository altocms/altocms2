Пользователь <a href="{$oUser->getProfileUrl()}">{$oUser->getDisplayName()}</a> оставил сообщение на <a href="{$oUserWall->getProfileUrl()}wall/">вашей стене</a><br/>

Текст сообщения: <i>{$oWall->getText()}</i>

<br/><br/>
С уважением, администрация сайта <a href="{C::get('path.root.url')}">{C::get('view.name')}</a>