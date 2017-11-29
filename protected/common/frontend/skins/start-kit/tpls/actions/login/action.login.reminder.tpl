{extends file="themes/$sSkinTheme/layouts/default_light.tpl"}

{block name="layout_vars"}
    {$noSidebar=true}
{/block}

{block name="layout_content"}
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            $('#reminder-form').bind('submit', function () {
                ls.user.reminder('reminder-form');
                return false;
            });
            $('#reminder-form-submit').attr('disabled', false);
        });
    </script>
    <div class="text-center page-header">
        <h3>{$aLang.password_reminder}</h3>
    </div>
    <form action="{router page='login'}reminder/" method="POST" id="reminder-form">
        <input type="hidden" name="security_key" value="{$ALTO_SECURITY_KEY}"/>
        <div class="form-group">
            <label for="input-reminder-mail">{$aLang.password_reminder_email}</label>
            <input type="text" name="mail" id="input-reminder-mail" class="form-control js-focus-in"/>

            <p class="help-block">
                <small class="text-danger validate-error-hide validate-error-reminder"></small>
            </p>
        </div>

        <p>
            {$aLang.user_password_reminder_note}
        </p>

        <a class="btn btn-default" href="{router page='login'}">{$aLang.user_login_submit}</a>
        <a class="btn btn-default" href="{router page='registration'}">{$aLang.user_sign_up}</a>
        <button type="submit" name="submit_reminder" class="btn btn-success" id="reminder-form-submit" disabled="disabled">
            {$aLang.user_password_reminder}
        </button>
    </form>
{/block}