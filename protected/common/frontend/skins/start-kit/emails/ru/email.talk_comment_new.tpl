Пользователь <a href="{$oUserFrom->getProfileUrl()}">{$oUserFrom->getDisplayName()}</a> оставил новый комментарий к письму
<b>«{$oTalk->getTitle()|escape:'html'}»</b>, прочитать его можно перейдя по
<a href="{router page='talk'}read/{$oTalk->getId()}/#comment{$oTalkComment->getId()}">этой ссылке</a><br>
{if C::get('sys.mail.include_talk')}
	Текст сообщения: <i>{$oTalkComment->getText()}</i>
    <br>
{/if}
Не забудьте предварительно авторизоваться!
<br><br>
С уважением, администрация сайта <a href="{C::get('path.root.url')}">{C::get('view.name')}</a>