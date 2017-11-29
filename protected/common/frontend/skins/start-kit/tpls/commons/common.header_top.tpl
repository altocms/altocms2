<header id="header" role="banner">

    {hook run='header_top_begin'}

    <nav class="navbar navbar-inverse navbar-{C::get('view.header.top')}-top">
        <div class="container">

            <div class="navbar-header">
                <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>

                <hgroup class="site-info">
                    {strip}
                        <h1 class="site-name"><a class="navbar-brand" href="{C::get('path.root.url')}">
                        {if C::get('view.header.logo')}
                                    <img src="{asset file=C::get('view.header.logo')}"
                                         alt="{C::get('view.name')}" class="navbar-brand-logo">
                        {/if}
                        {if C::get('view.header.name')}
                            {C::get('view.header.name')}
                        {/if}
                        </a></h1>
                    {/strip}
                </hgroup>
            </div>

            {hook run='userbar_nav'}

            <div class="collapse navbar-collapse navbar-ex1-collapse">
                {include file="menus/menu.main.tpl"}
                    {if E::IsUser()}
                    {menu id='user' class='nav navbar-nav navbar-right'}
                    {else}
                    {menu id='login' class='nav navbar-nav navbar-right'}
                    {/if}
            </div>

        </div>
    </nav>

    {hook run='header_top_end'}

</header>
