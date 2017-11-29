<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */
namespace alto\engine\core;

use alto\engine\generic\Singleton;

/**
 * Application class of CMS
 *
 * @package engine
 * @since 1.1
 */
class Application extends Singleton
{
    protected $aParams = [];

    public function __destruct()
    {
        $this->done();
    }

    /**
     * @param array $aParams
     *
     * @return Application
     */
    public static function create($aParams = [])
    {
        $oApp = static::getInstance();
        $oApp->init($aParams);

        return $oApp;
    }

    /**
     * Init application
     *
     * @param array $aParams
     */
    public function init($aParams = [])
    {
        $this->aParams = $aParams;

        if (!defined('ALTO_DEBUG')) {
            define('ALTO_DEBUG', 0);
        }

        if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI']) && $_SERVER['SCRIPT_NAME'] == $_SERVER['REQUEST_URI']) {
            // для предотвращения зацикливания и ошибки 404
            $_SERVER['REQUEST_URI'] = '/';
        }

        if (is_file('./install/index.php') && !defined('ALTO_INSTALL') && (!isset($_SERVER['HTTP_APP_ENV']) || $_SERVER['HTTP_APP_ENV'] !== 'test')) {
            if (isset($_SERVER['REDIRECT_URL'])) {
                $sUrl = trim($_SERVER['REDIRECT_URL'], '/');
            } else {
                $sUrl = \F::urlBase();
                if ($sPath = \F::parseUrl(null, 'path')) {
                    $sUrl .= $sPath;
                } else {
                    $sUrl .= '/';
                }
                $sUrl .= 'install';
            }
            if ($sUrl && $sUrl !== 'install' && substr($sUrl, -8) !== '/install') {
                // Cyclic redirection to .../install/
                die('URL ' . $sUrl . '/ doesn\'t work on your site. Alto CMS v.' . ALTO_VERSION . ' not installed yet');
            }
            // Try to redirect to .../install/
            \F::httpLocation($sUrl, true);
            exit;
        }
    }

    /**
     * Executes application
     */
    public function exec()
    {
        \R::getInstance()->Exec();
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->aParams;
    }

    /**
     * Done application
     */
    public function done()
    {
    }

}

// EOF