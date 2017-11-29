<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
	<ShortName>{C::get('view.name')}</ShortName>
	<Description>{$sHtmlTitle}</Description>
	<Contact>{$sAdminMail}</Contact>
	<Url type="text/html" template="{router page='search'}topics/?q={literal}{searchTerms}{/literal}" />
	<LongName>{$sHtmlDescription}</LongName>
	<Image height="64" width="64" type="{C::get('path.root.url')}{{asset file='assets/images/logo-64x64.png'}|ltrim:'/'}"></Image>
	<Image height="16" width="16" type="{C::get('path.root.url')}{{asset file='assets/images/favicon.ico'}|ltrim:'/'}"></Image>
	<Developer>{C::get('view.name')} ({C::get('path.root.url')})</Developer>
	<Attribution>
		© «{C::get('view.name')}»
	</Attribution>
	<SyndicationRight>open</SyndicationRight>
	<AdultContent>false</AdultContent>
	<Language>ru-ru</Language>
	<OutputEncoding>UTF-8</OutputEncoding>
	<InputEncoding>UTF-8</InputEncoding>
</OpenSearchDescription>