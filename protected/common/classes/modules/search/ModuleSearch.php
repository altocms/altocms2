<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

class ModuleSearch extends Module {

    /** @var ModuleSearch_MapperSearch */
    protected $oMapper;

    /** @var ModuleUser_EntityUser */
    protected $oUserCurrent;

    public function init() {

        $this->oMapper = \E::getMapper(__CLASS__);
        $this->oUserCurrent = \E::User();
    }

    /**
     * Получает список топиков по регулярному выражению (поиск)
     *
     * @param $sRegexp
     * @param $iPage
     * @param $iPerPage
     * @param array $aParams
     * @param bool $bAccessible
     *
     * @return array
     */
    public function getTopicsIdByRegexp($sRegexp, $iPage, $iPerPage, $aParams = array(), $bAccessible = false) {

        $s = md5(serialize($sRegexp) . serialize($aParams));
        $sCacheKey = 'search_topics_' . $s . '_' . $iPage . '_' . $iPerPage;
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            if ($bAccessible) {
                $aParams['filter'] = \E::Module('Topic')->getNamedFilter(FALSE, array('accessible' => true));
            }
            $data = array(
                'collection' => $this->oMapper->getTopicsIdByRegexp($sRegexp, $iCount, $iPage, $iPerPage, $aParams),
                'count'      => $iCount,
            );
            \E::Module('Cache')->set($data, $sCacheKey, array('topic_update', 'topic_new'), 'PT1H');
        }
        return $data;
    }

    /**
     * Получает список комментариев по регулярному выражению (поиск)
     *
     * @param       $sRegexp
     * @param       $iPage
     * @param       $iPerPage
     * @param array $aParams
     *
     * @return array
     */
    public function getCommentsIdByRegexp($sRegexp, $iPage, $iPerPage, $aParams = array()) {

        $s = md5(serialize($sRegexp) . serialize($aParams));
        $sCacheKey = 'search_comments_' . $s . '_' . $iPage . '_' . $iPerPage;
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = array(
                'collection' => $this->oMapper->getCommentsIdByRegexp($sRegexp, $iCount, $iPage, $iPerPage, $aParams),
                'count'      => $iCount,
            );
            \E::Module('Cache')->set($data, $sCacheKey, array('topic_update', 'comment_new'), 'PT1H');
        }
        return $data;
    }

    /**
     * Получает список блогов по регулярному выражению (поиск)
     *
     * @param       $sRegexp
     * @param       $iPage
     * @param       $iPerPage
     * @param array $aParams
     *
     * @return array
     */
    public function getBlogsIdByRegexp($sRegexp, $iPage, $iPerPage, $aParams = array()) {

        $s = md5(serialize($sRegexp) . serialize($aParams));
        $sCacheKey = 'search_blogs_' . $s . '_' . $iPage . '_' . $iPerPage;
        if (false === ($data = \E::Module('Cache')->get($sCacheKey))) {
            $data = array(
                'collection' => $this->oMapper->getBlogsIdByRegexp($sRegexp, $iCount, $iPage, $iPerPage, $aParams),
                'count'      => $iCount);
            \E::Module('Cache')->set($data, $sCacheKey, array('blog_update', 'blog_new'), 'PT1H');
        }
        return $data;
    }

}

// EOF