<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 * Based on
 *   LiveStreet Engine Social Networking by Mzhelskiy Maxim
 *   Site: www.livestreet.ru
 *   E-mail: rus.engine@gmail.com
 *----------------------------------------------------------------------------
 */

/**
 * Блок настройки списка блогов в ленте
 *
 * @package widgets
 * @since   1.0
 */
class WidgetUserfeedBlogs extends Widget
{
    public function exec()
    {
        // For authorized users only
        if ($oUserCurrent = \E::User()) {
            $aUserSubscribes = \E::Module('Userfeed')->getUserSubscribes($oUserCurrent->getId());

            // Get ID list of blogs to which you subscribe
            $aBlogsId = \E::Module('Blog')->getBlogUsersByUserId(
                $oUserCurrent->getId(),
                array(
                    ModuleBlog::BLOG_USER_ROLE_MEMBER,
                    ModuleBlog::BLOG_USER_ROLE_MODERATOR,
                    ModuleBlog::BLOG_USER_ROLE_ADMINISTRATOR
                ),
                true
            );

            // Get ID list of blogs where the user is the owner
            $aBlogsOwnerId = \E::Module('Blog')->getBlogsByOwnerId($oUserCurrent->getId(), true);
            $aBlogsId = array_merge($aBlogsId, $aBlogsOwnerId);

            $aBlogs = \E::Module('Blog')->getBlogsAdditionalData(
                $aBlogsId, ['owner' => []], array('blog_title' => 'asc')
            );
            /**
             * Выводим в шаблон
             */
            \E::Module('Viewer')->assign('aUserfeedSubscribedBlogs', $aUserSubscribes['blogs']);
            \E::Module('Viewer')->assign('aUserfeedBlogs', $aBlogs);
        }
    }
}

// EOF