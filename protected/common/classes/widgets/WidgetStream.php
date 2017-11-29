<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * Обработка виджета с комментариями (прямой эфир)
 *
 * @package widgets
 * @since   1.0
 */
class WidgetStream extends Widget
{
    /**
     * @param mixed $sItem
     *
     * @return string
     */
    public function exec($sItem = null)
    {
        $aItems = (array)$this->getParam('items');
        if (null === $sItem || !isset($aItems[$sItem])) {
            $sItem = key($aItems);
        }

        if ($sItem === 'comments') {
            return $this->execComments();
        }

        return '';
    }

    /**
     * @return string
     */
    protected function execComments()
    {
        if ($aComments = \E::Module('Comment')->getCommentsOnline('topic', Config::get('widgets.stream.params.limit'))) {
            $aVars = ['aComments' => $aComments];

            // * Формируем результат в виде шаблона и возвращаем
            return $this->fetch('stream_comments.tpl', $aVars);
        }
        return '';
    }

    protected function execTopics()
    {

    }

}

// EOF