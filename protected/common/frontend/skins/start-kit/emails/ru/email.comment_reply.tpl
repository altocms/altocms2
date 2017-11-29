Пользователь <a href="{$oUserComment->getProfileUrl()}">{$oUserComment->getDisplayName()}</a> ответил на ваш комментарий в топике
<b>«{$oTopic->getTitle()|escape:'html'}»</b>, прочитать его можно перейдя по
<a href="{if C::get('module.comment.nested_per_page')}{router page='comments'}{else}{$oTopic->getUrl()}#comment{/if}{$oComment->getId()}">этой ссылке</a><br>
{if C::get('sys.mail.include_comment')}
	Текст сообщения: <i>{$oComment->getText()}</i>
{/if}
<br><br>
С уважением, администрация сайта <a href="{C::get('path.root.url')}">{C::get('view.name')}</a>