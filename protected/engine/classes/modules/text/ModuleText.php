<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

F::includeFile('./parser/ITextParser.php');

/**
 * Модуль обработки текста на основе типографа Jevix/Qevix
 * Позволяет вырезать из текста лишние HTML теги и предотвращает различные попытки внедрить в текст JavaScript
 * <pre>
 * $sText=E::Module('Text')->Parse($sTestSource);
 * </pre>
 * Настройки парсинга находятся в конфиге /config/jevix.php
 *
 * @package engine.modules
 * @since   1.0
 */
class ModuleText extends Module
{
    /**
     * Объект типографа
     *
     * @var ITextParser
     */
    protected $oTextParser;

    protected $aLinks = [];

    protected $aCheckTagLinks = [];

    protected $aSpecialParsers = [];

    protected $aSnippets = [];

    /**
     * Инициализация модуля
     *
     */
    public function init()
    {
        $this->aSnippets = [
            'user'      => ['block' => false],
            'photoset'  => ['block' => true],
            'spoiler'   => ['block' => true],
        ];
        // В каких тегах контролируем ссылки
        $this->aCheckTagLinks = [
            'img' => [
                'link'        => 'src',                             // какой атрибут контролировать
                'type'        => ModuleMedia::TYPE_IMAGE,       // тип медиа-ресурса
                'restoreFunc' => [$this, '_restoreLocalUrl'],  // функция для восстановления URL
                'pairedTag'   => false,                             // короткий тег
            ],
            'a'   => [
                'link'        => 'href',
                'type'        => ModuleMedia::TYPE_HREF,
                'restoreFunc' => [$this, '_restoreLocalUrl'],
                'pairedTag'   => true,
            ],
        ];

        // * Create a typographer and load its configuration
        $this->_createTextParser();
        $this->_loadTextParserConfig();

        $this->addSpecialParser('flash',    [$this, 'flashParamParser']);
        $this->addSpecialParser('snippet',  [$this, 'SnippetParser']);
        $this->addSpecialParser('text',     [$this, 'TextParser']);
        $this->addSpecialParser('video',    [$this, 'VideoParser']);
        $this->addSpecialParser('code',     [$this, 'CodeSourceParser']);
    }

    /**
     * Create a typographer and load its configuration
     */
    protected function _createTextParser()
    {
        $sParser = \C::get('module.text.parser');

        $sClassName = 'TextParser' . $sParser;
        $sFileName = './parser/' . $sClassName . '.php';
         \F::includeFile($sFileName);

        $this->oTextParser = new $sClassName();
    }

    /**
     * Load config for text parser
     *
     * @param string $sType
     * @param bool   $bClear
     */
    protected function _loadTextParserConfig($sType = 'default', $bClear = true)
    {
        $this->oTextParser->loadConfig($sType, $bClear);
        foreach($this->aCheckTagLinks as $sTag => $aParams) {
            $this->oTextParser->tagBuilder($sTag, [$this, 'CallbackCheckLinks']);
        }
        $this->oTextParser->tagBuilder('alto', [$this, 'CallbackTagSnippet']);
    }

    /**
     * @param string $sType
     * @param bool   $bClear
     *
     * @return ITextParser
     */
    public static function newTextParser($sType = 'default', $bClear = true)
    {
        $sParser = \C::get('module.text.parser');

        $sClassName = 'TextParser' . $sParser;
        $sFileName = './parser/' . $sClassName . '.php';
         \F::includeFile($sFileName);

        /** @var ITextParser $oTextParser */
        $oTextParser = new $sClassName();
        $oTextParser->loadConfig($sType, $bClear);

        return $oTextParser;
    }

    /**
     * Add new special parser
     *
     * @param string   $sName
     * @param callback $aCallback
     */
    public function addSpecialParser($sName, $aCallback)
    {
        $this->AppendSpecialParser($sName, $aCallback);
    }

    /**
     * Prepend new special parser into begin of array
     *
     * @param string   $sName
     * @param callback $aCallback
     */
    public function prependSpecialParser($sName, $aCallback)
    {
        $this->aSpecialParsers = [$sName => $aCallback] + $this->aSpecialParsers;
    }

    /**
     * Append new special parser to end of array
     *
     * @param string   $sName
     * @param callback $aCallback
     */
    public function AppendSpecialParser($sName, $aCallback)
    {
        $this->aSpecialParsers = $this->aSpecialParsers + [$sName => $aCallback];
    }

    /**
     * Return array of current special parsers
     *
     * @return array
     */
    public function getSpecialParsers()
    {
        return $this->aSpecialParsers;
    }

    /**
     * Set new array of special parser
     *
     * @param array $aSpecialParsers
     */
    public function setSpecialParsers($aSpecialParsers) {

        $this->aSpecialParsers = $aSpecialParsers;
    }

    /**
     * Парсинг текста
     *
     * @param string $sText     Исходный текст
     * @param array  $aError    Возвращает список возникших ошибок
     *
     * @return string
     */
    public function TextParser($sText, &$aError = null) {

        $sResult = $this->oTextParser->parse($sText, $aError);
        return $sResult;
    }

    /**
     * Парсинг текста на предмет видео
     * Находит теги <pre><video></video></pre> и реобразовываетих в видео
     *
     * @param string $sText    Исходный текст
     *
     * @return string
     */
    public function VideoParser($sText) {

        $aConfig = \E::Module('Uploader')->GetConfig('*', 'images.video');
        if (!empty($aConfig['transform']['max_width'])) {
            $iWidth = (int)$aConfig['transform']['max_width'];
        } else {
            $iWidth = 640;
        }
        $nRatio = \E::Module('Uploader')->GetConfigAspectRatio('*', 'video');
        if ($nRatio) {
            $iHeight = $iWidth / $nRatio;
        } else {
            $iHeight = (int)$aConfig['transform']['max_width'];
        }
        if (!empty($aConfig['transform']['max_height'])) {
            if ($iHeight > (int)$aConfig['transform']['max_width']) {
                $iHeight = (int)$aConfig['transform']['max_width'];
            }
        }
        if (!$iHeight) {
            $iHeight = 380;
        }

        $sIframeAttr = 'frameborder="0" webkitAllowFullScreen mozallowfullscreen allowfullscreen="allowfullscreen"';
        /**
         * youtube.com
         */
        $sText = preg_replace(
            '/<video>http(?:s|):\/\/(?:www\.|m.|)youtube\.com\/watch\?v=([a-zA-Z0-9_\-]+)(&.+)?<\/video>/Ui',
            '<iframe src="//www.youtube.com/embed/$1" width="' . $iWidth . '" height="' . $iHeight . '" ' . $sIframeAttr . '></iframe>',
            $sText
        );
        /**
         * youtu.be
         */
        $sText = preg_replace(
            '/<video>http(?:s|):\/\/(?:www\.|m.|)youtu\.be\/([a-zA-Z0-9_\-]+)(&.+)?<\/video>/Ui',
            '<iframe src="//www.youtube.com/embed/$1" width="' . $iWidth . '" height="' . $iHeight . '" ' . $sIframeAttr . '></iframe>',
            $sText
        );
        /**
         * vimeo.com
         */
        $sText = preg_replace(
            '/<video>http(?:s|):\/\/(?:www\.|)vimeo\.com\/(\d+).*<\/video>/i',
            '<iframe src="//player.vimeo.com/video/$1" width="' . $iWidth . '" height="' . $iHeight . '" ' . $sIframeAttr . '></iframe>',
            $sText
        );
        /**
         * rutube.ru
         */
        $sText = preg_replace(
            '/<video>http(?:s|):\/\/(?:www\.|)rutube\.ru\/tracks\/(\d+)\.html.*<\/video>/Ui',
            '<iframe src="//rutube.ru/play/embed/$1" width="' . $iWidth . '" height="' . $iHeight . '" ' . $sIframeAttr . '></iframe>',
            $sText
        );

        $sText = preg_replace(
            '/<video>http(?:s|):\/\/(?:www\.|)rutube\.ru\/video\/(\w+)\/?<\/video>/Ui',
            '<iframe src="//rutube.ru/play/embed/$1" width="' . $iWidth . '" height="' . $iHeight . '" ' . $sIframeAttr . '></iframe>',
            $sText
        );
        /**
         * video.yandex.ru - closed
         */
        return $sText;
    }

    /**
     * Разбирает текст и анализирует его на наличие сниппетов.
     * Если они найдены, то запускает хуки для их обработки.
     *
     * @version 0.1 Базовый функционал
     * @version 0.2 Добавлены блочный и шаблонный сниппеты
     *
     * @param string $sText
     *
     * @return string
     */
    public function SnippetParser($sText) {

        // Массив регулярки для поиска сниппетов
        $aSnippetRegexp = array(
            // Регулярка блочного сниппета. Сначала ищем по ней, а уже потом по непарному тегу
            // alto:name иначе блочный сниппет будет затираться поскульку регулярка одиночного сниппета
            // будет отхватывать первую его часть.
            '~<alto:(\w+)((?:\s+\w+(?:\s*=\s*(?:(?:"[^"]*")|(?:\'[^\']*\')|[^>\s]+))?)*)\s*>([.\s\S\r\n]*)</alto:\1>~Ui',
            // Регулярка строчного сниппета
            '~<alto:(\w+)((?:\s+\w+(?:\s*=\s*(?:(?:"[^"]*")|(?:\'[^\']*\')|[^>\s]+))?)*)\s*\/*>~Ui',
        );

        // Получим массив: сниппетов, их имён и параметров по каждой регклярке
        // Здесь получаем в $aMatches три/четыре массива из которых первым идет массив найденных сниппетов,
        // который позже будет заменён на результат полученный от хука. Вторым массивом идут имена
        // найденных сниппетов, которые будут использоваться для формирвоания имени хука.
        // Третим массивом будут идти параметры сниппетов. Если сниппет блочный, то четвертым параметром
        // будет текст-содержимое блока.
        foreach ($aSnippetRegexp as $sRegExp) {

            if (preg_match_all($sRegExp, $sText, $aMatches)) {

                // Данные для замены сниппетов на полученный код.
                $aReplaceData = [];

                /**
                 * @var int $iSnippetNum Порядковый номер найденного сниппета
                 * @var string $sSnippetName Имя (идентификатор) сниппета
                 */
                foreach ($aMatches[1] as $iSnippetNum => $sSnippetName) {

                    // Получим атрибуты сниппета в виде массива. Вообще-то их может и не быть воовсе,
                    // но мы всё-таки попробуем это сделать...
                    $aSnippetAttr = [];
                    if (preg_match_all('~([a-zA-Z]+)\s*=\s*[\'"]([^\'"]+)[\'"]~Ui', $aMatches[2][$iSnippetNum], $aMatchesAttr)) {
                        foreach ($aMatchesAttr[1] as $iAttrNum => $sAttrName) {
                            // Имя параметра должно быть буквенноцифровым + подчеркивание + дефис
                            if (!empty($aMatchesAttr[2][$iAttrNum]) && preg_match('/^[\w\-]+$/', $sAttrName)) {
                                $aSnippetAttr[$sAttrName] = $aMatchesAttr[2][$iAttrNum];
                            }
                        }
                    }

                    $sSnippetInnerText = isset($aMatches[3][$iSnippetNum]) ? $aMatches[3][$iSnippetNum] : false;
                    $sSnippetResultTag = $this->Snippet2Html($sSnippetName, $aSnippetAttr, $sSnippetInnerText);

                    $aReplaceData[$iSnippetNum] = $sSnippetResultTag;

                    /*
                    // Добавим в параметры текст, который был в топике, вдруг какой-нибудь сниппет
                    // захочет с ним поработать.
                    $aParams['target_text'] = $sText;

                    // Если это блочный сниппет, то добавим в параметры еще и текст блока
                    $aParams['snippet_text'] = isset($aMatches[3][$iSnippetNum]) ? $aMatches[3][$iSnippetNum] : '';

                    // Добавим в параметры имя сниппета
                    $aParams['snippet_name'] = $sSnippetName;

                    // Попытаемся получить результат от обработчика
                    // Может сниппет уже был в обработке, тогда просто возьмем его из кэша
                    $sCacheKey = $sSnippetName . md5(serialize($aParams));
                    if (FALSE === ($sResult = \E::Module('Cache')->GetTmp($sCacheKey))) {

                        // Определим тип сниппета, может быть шаблонным, а может и исполняемым
                        // по умолчанию сниппет ссчитаем исполняемым. Если шаблонный, то его
                        // обрабатывает предопределенный хук snippet_template_type
                        $sHookName = 'snippet_' . $sSnippetName;
                        $sHookName = \HookManager->IsEnabled($sHookName)
                            ? 'snippet_' . $sSnippetName
                            : 'snippet_template_type';

                        // Установим хук
                        \HookManager::run($sHookName, array(
                            'params' => &$aParams,
                            'result' => &$sResult,
                        ));

                        // Запишем результат обработки в кэш
                        \E::Module('Cache')->SetTmp($sResult, $sCacheKey);

                    }

                    $aReplaceData[$iSnippetNum] = is_string($sResult) ? $sResult : '';
                    */
                }

                // Произведем замену сниппетов на валидный HTML-код
                $sText = str_replace(array_values($aMatches[0]), array_values($aReplaceData), $sText);
            }
        }

        return $sText;
    }

    /**
     * @param string       $sSnippetName
     * @param array        $aSnippetAttr
     * @param string|false $sSnippetText
     *
     * @return string
     */
    public function Snippet2Html($sSnippetName, $aSnippetAttr, $sSnippetText) {

        // Определяем строчный сниппет или блочный
        if (!empty($this->aSnippets[$sSnippetName]['block'])) {
            // Явно задано, что сниппет блочный
            $bBlockSnippet = true;
        } elseif (isset($this->aSnippets[$sSnippetName]['block'])) {
            // Сниппет строчный
            $bBlockSnippet = false;
        } else {
            // В настройках не задана блочность
            // В таких случаях считаем блочными сниппеты с парным закрывающим тегом
            $bBlockSnippet = is_string($sSnippetText);
        }

        $sDataAltoTagAttr = '';
        foreach ($aSnippetAttr as $sAttrName => $sAttrValue) {
            $sDataAltoTagAttr .= $sAttrName . ':' . $sAttrValue . ';';
        }

        // Преобразуем сниппет в HTML-тег
        $sSnippetResultTag = '<alto'
            . ' style="' . ($bBlockSnippet ? 'display:block' : '') . '"'
            . ' data-alto-tag-name="' . $sSnippetName . '"'
            . ' data-alto-tag-attr="' . $sDataAltoTagAttr . '">'
            . $sSnippetText . '</alto>';

        return $sSnippetResultTag;
    }

    /**
     * Парсит текст, применя все парсеры
     *
     * @param string $sText Исходный текст
     *
     * @return string
     */
    public function parse($sText)
    {
        if (!is_string($sText)) {
            return '';
        }
        $this->aLinks = [];

        foreach($this->aSpecialParsers as $sName => $aCallback) {
            $sText = call_user_func($aCallback, $sText);
        }

        return $sText;
    }

    /**
     * Заменяет все вхождения короткого тега <param/> на длиную версию <param></param>
     * Заменяет все вхождения короткого тега <embed/> на длиную версию <embed></embed>
     *
     * @param string $sText Исходный текст
     *
     * @return string
     */
    public function flashParamParser($sText)
    {
        if (preg_match_all(
            "@(<\s*param\s*name\s*=\s*(?:\"|').*(?:\"|')\s*value\s*=\s*(?:\"|').*(?:\"|'))\s*/?\s*>(?!</param>)@Ui",
            $sText, $aMatch
        )
        ) {
            foreach ($aMatch[1] as $key => $str) {
                $str_new = $str . '></param>';
                $sText = str_replace($aMatch[0][$key], $str_new, $sText);
            }
        }
        if (preg_match_all("@(<\s*embed\s*.*)\s*/?\s*>(?!</embed>)@Ui", $sText, $aMatch)) {
            foreach ($aMatch[1] as $key => $str) {
                $str_new = $str . '></embed>';
                $sText = str_replace($aMatch[0][$key], $str_new, $sText);
            }
        }
        /**
         * Удаляем все <param name="wmode" value="*"></param>
         */
        if (preg_match_all("@(<param\s.*name=(?:\"|')wmode(?:\"|').*>\s*</param>)@Ui", $sText, $aMatch)) {
            foreach ($aMatch[1] as $key => $str) {
                $sText = str_replace($aMatch[0][$key], '', $sText);
            }
        }
        /**
         * А теперь после <object> добавляем <param name="wmode" value="opaque"></param>
         * Решение не фантан, но главное работает :)
         */
        if (preg_match_all("@(<object\s.*>)@Ui", $sText, $aMatch)) {
            foreach ($aMatch[1] as $key => $str) {
                $sText = str_replace(
                    $aMatch[0][$key], $aMatch[0][$key] . '<param name="wmode" value="opaque"></param>', $sText
                );
            }
        }
        return $sText;
    }

    /**
     * Подсветка исходного кода
     *
     * @param string $sText Исходный текст
     *
     * @return string
     */
    public function CodeSourceParser($sText) {

        $sText = str_replace("<code>", '<pre class="prettyprint"><code>', $sText);
        $sText = str_replace("</code>", '</code></pre>', $sText);
        return $sText;
    }

    /**
     * Производить резрезание текста по тегу cut.
     * Возвращаем массив вида:
     * <pre>
     * array(
     *        $sTextShort - текст до тега <cut>
     *        $sTextNew   - весь текст за исключением удаленного тега
     *        $sTextCut   - именованное значение <cut>
     * )
     * </pre>
     *
     * @param  string $sText Исходный текст
     *
     * @return array
     */
    public function Cut($sText)
    {
        $sTextShort = $sText;
        $sTextNew = $sText;
        $sTextCut = null;

        $sTextTemp = str_replace("\r\n", '[<rn>]', $sText);
        $sTextTemp = str_replace("\n", '[<n>]', $sTextTemp);

        if (preg_match("/^(.*)<cut(.*)>(.*)$/Ui", $sTextTemp, $aMatch)) {
            $aMatch[1] = str_replace('[<rn>]', "\r\n", $aMatch[1]);
            $aMatch[1] = str_replace('[<n>]', "\r\n", $aMatch[1]);
            $aMatch[3] = str_replace('[<rn>]', "\r\n", $aMatch[3]);
            $aMatch[3] = str_replace('[<n>]', "\r\n", $aMatch[3]);
            $sTextShort = $aMatch[1];
            $sTextNew = $aMatch[1] . ' <a name="cut"></a> ' . $aMatch[3];
            if (preg_match('/^\s*name\s*=\s*"(.+)"\s*\/?$/Ui', $aMatch[2], $aMatchCut)) {
                $sTextCut = trim($aMatchCut[1]);
            }
        }

        return array($sTextShort, $sTextNew, $sTextCut ? htmlspecialchars($sTextCut) : null);
    }

    /**
     * @param string $sString
     *
     * @return string
     */
    public function callbackTagAt($sString)
    {
        $sText = '';
        if ($sString && ($oUser = \E::Module('User')->getUserByLogin($sString))) {
            if (\E::Module('Viewer')->templateExists('tpls/snippets/snippet.user.tpl')) {
                // Получим html-код сниппета
                $aVars = array('oUser' => $oUser);
                $sText = trim(\E::Module('Viewer')->fetch('tpls/snippets/snippet.user.tpl', $aVars));
            } else {
                $sText = "<a href=\"{$oUser->getProfileUrl()}\">{$oUser->getDisplayName()}</a> ";
            }
        }
        return $sText;
    }

    /**
     * @param string $sTagName
     * @param array  $aTagAttributes
     * @param string $sСontent
     *
     * @return string
     */
    public function CallbackTagSnippet($sTagName, $aTagAttributes, $sСontent)
    {
        if (isset($aTagAttributes['data-alto-tag-name'])) {
            $sSnippetName = $aTagAttributes['data-alto-tag-name'];
        } elseif (isset($aTagAttributes['name'])) {
            $sSnippetName = $aTagAttributes['name'];
        } else {
            // Нет имени сниппета, оставляем, как есть
            return $this->_buildTag($sTagName, $aTagAttributes, $sСontent);
        }

        // Имя сниппета есть, обрабатываем его
        $sCacheKey = serialize([$sTagName, $aTagAttributes, $sСontent]);
        // Может сниппет уже был в обработке, тогда просто возьмем его из кэша
        $sResult = \E::Module('Cache')->getTmp($sCacheKey);
        // Если результата в кеше нет, то обрабатываем
        if (FALSE === $sResult) {
            $aParams = [];
            if (!empty($aTagAttributes['data-alto-tag-attr'])) {
                $aTagAttr = explode(';', $aTagAttributes['data-alto-tag-attr']);
                foreach($aTagAttr as $sAttr) {
                    if ($sAttr) {
                        list($sAttrName, $sAttrValue) = explode(':', $sAttr, 2);
                        $aParams[$sAttrName] = $sAttrValue;
                    }
                }
            }
            // Добавим в параметры текст, который был в топике, вдруг какой-нибудь сниппет
            // захочет с ним поработать.
            //$aSnippetParams['target_text'] = $sText;

            // Добавим контент сниппета
            $aParams['snippet_text'] = $sСontent;

            // Добавим в параметры имя сниппета
            $aParams['snippet_name'] = $sSnippetName;

            // Попытаемся получить результат от обработчика

            // Определим тип сниппета, может быть шаблонным, а может и исполняемым
            // по умолчанию сниппет ссчитаем исполняемым. Если шаблонный, то его
            // обрабатывает предопределенный хук snippet_template_type
            $sHookName = 'snippet_' . $sSnippetName;
            $sHookName = \HookManager->isEnabled($sHookName)
                ? 'snippet_' . $sSnippetName
                : 'snippet_template_type';
            // Вызовем хук
            \HookManager::run($sHookName, [
                'params' => &$aParams,
                'result' => &$sResult,
            ]);
            if ($sHookName === 'snippet_template_type' && $sResult === false) {
                // Шаблонный хук не отработал, оставлям тег, как есть
                $sResult = $this->_buildTag($sTagName, $aTagAttributes, $sСontent);
            }
            // Запишем результат обработки в кэш
            \E::Module('Cache')->setTmp($sResult, $sCacheKey);
        }

        return $sResult;
    }

    protected function _buildTag($sTag, $aParams, $sСontent)
    {
        $sResult = '<' . $sTag;
        foreach($aParams as $sAttrName => $sAttrValue) {
            $sResult .= ' ' . $sAttrName . '="' . $sAttrValue . '"';
        }
        $sResult .= '>';
        if ($sСontent !== false) {
            $sResult .= $sСontent . '</' . $sTag . '>';
        }

        return $sResult;
    }

    /**
     * @param string $sUrl
     *
     * @return string
     */
    public function _restoreLocalUrl($sUrl)
    {
        if ($sUrl[0] === '@') {
            $sUrl = '/' . substr($sUrl, 1);
        }
        return $sUrl;
    }

    /**
     * Учет ссылок в тексте
     *
     * @param string $sTag
     * @param array  $aParams
     * @param string $sContent
     * @param string $sText
     *
     * @return string
     */
    public function callbackCheckLinks($sTag, $aParams, $sContent, $sText = null)
    {
        if (isset($this->aCheckTagLinks[$sTag], $aParams[$this->aCheckTagLinks[$sTag]['link']])) {
            $sLinkAttr = $this->aCheckTagLinks[$sTag]['link'];
            $sLink = \E::Module('Media')->normalizeUrl($aParams[$sLinkAttr]);
            $nType = $this->aCheckTagLinks[$sTag]['type'];
            $this->aLinks[] = [
                'type' => $nType,
                'link' => $sLink,
            ];
            $sText = '<' . $sTag . ' ';
            if (isset($aParams['rel']) && $aParams['rel'] === 'nofollow' &&  \F::File_LocalUrl($aParams[$sLinkAttr])) {
                unset($aParams['rel']);
            }
            foreach ($aParams as $sKey => $sVal) {
                if ($sKey === $sLinkAttr && $this->aCheckTagLinks[$sTag]['restoreFunc']) {
                    $sVal = call_user_func($this->aCheckTagLinks[$sTag]['restoreFunc'], $sLink);
                }
                $sText .= $sKey . '="' . $sVal . '" ';
            }
            if (null === $sContent || empty($this->aCheckTagLinks[$sTag]['pairedTag'])) {
                $sText = trim($sText) . '>';
            } else {
                $sText = trim($sText) . '>' . $sContent . '</' . $sTag . '>';
            }
        }
        return $sText;
    }

    /**
     * @param bool $bUrlOnly
     *
     * @return array
     */
    public function getLinks($bUrlOnly = false)
    {
        if ($bUrlOnly) {
            return  \F::Array_Column($this->aLinks, 'link');
        } else {
            return $this->aLinks;
        }
    }

    /**
     * Truncates text with word wrapping
     *
     * @param string $sText
     * @param int    $iMaxLen
     *
     * @return string
     */
    public function truncateText($sText, $iMaxLen)
    {
        $sResult = $sText;
        if (strpos($sText, '<') === false) {
            // no tags
            $sResult = \F::TruncateText($sText, $iMaxLen, '', true);
        } else {
            $iLen = mb_strlen(strip_tags($sText), 'UTF-8');
            if ($iLen > $iMaxLen) {
                if (preg_match_all('/\<\/?\w+[^>]*>/siu', $sResult, $aM, PREG_OFFSET_CAPTURE)) {
                    $aTags = $aM[0];
                    $iOffset = 0;
                    foreach($aTags as $iTagIdx => $aTag) {
                        $iLen = strlen($aTag[0]);
                        $sResult = substr($sResult, 0, $aTag[1] + $iOffset) . substr($sResult, $aTag[1] + $iOffset + $iLen);
                        $iOffset -= $iLen;
                        $aTag['tag'] = $aTag[0];
                        $aTag['pos'] = $aTag[1];
                        $aTag['pair'] = null;
                        if ($aTag['tag'][1] !== '/') {
                            $aTag['open'] = true;
                        } else {
                            $aTag['open'] = false;
                            $sTagName = '<' . substr($aTag['tag'], 2, strlen($aTag['tag']) - 3);
                            // seek open tag
                            foreach($aTags as $iOpenIdx => $aOpenTag) {
                                if (!isset($aOpenTag['pair']) && strpos($aOpenTag['tag'], $sTagName) === 0) {
                                    // link from open tag to closing
                                    $aTags[$iOpenIdx]['pair'] = $iTagIdx;
                                    // link from close tag to openning
                                    $aTag['pair'] = $iOpenIdx;
                                    break;
                                }
                            }
                        }
                        $aTags[$iTagIdx] = $aTag;
                    }
                    $sResult =  \F::TruncateText($sResult, $iMaxLen, '', true);
                    $aClosingTags = [];
                    foreach ($aTags as $iIdx => $aTag) {
                        if (strlen($sResult) < $aTag['pos']) {
                            break;
                        }
                        $sResult = substr($sResult, 0, $aTag['pos']) . $aTag['tag'] . substr($sResult, $aTag['pos']);
                        if ($aTag['open']) {
                            // open tag
                            if ($aTag['pair']) {
                                $aClosingTags[$aTag['pair']] = $aTags[$aTag['pair']];
                            }
                        } else {
                            // close tag
                            if ((null !== $aTag['pair']) && isset($aClosingTags[$iIdx])) {
                                unset($aClosingTags[$iIdx]);
                            }
                        }
                    }
                    if ($aClosingTags) {
                        // need to close open tags
                        ksort($aClosingTags);
                        foreach ($aClosingTags as $aTag) {
                            $sResult .= $aTag['tag'];
                        }
                    }
                }
            }
        }
        return $sResult;
    }

    /**
     * Удаляет из строки все теги, используется как аналог strip_tags там,
     * где последний удаляет часть текста вместе с не валидными тегами, например
     * при обработке строки ">>> Hello <<<". Подробнее в задаче #151
     * {@see https://github.com/altocms/altocms/issues/151}
     *
     * @param $sText
     *
     * @return string
     */
    public function removeAllTags($sText)
    {
        return htmlspecialchars_decode($this->parse($sText));
    }

}

// EOF
