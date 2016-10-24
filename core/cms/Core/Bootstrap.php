<?php
/*
  +------------------------------------------------------------------------+
  | PhalconEye CMS                                                         |
  +------------------------------------------------------------------------+
  | Copyright (c) 2013-2016 PhalconEye Team (http://phalconeye.com/)       |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file LICENSE.txt.                             |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to license@phalconeye.com so we can send you a copy immediately.       |
  +------------------------------------------------------------------------+
  | Author: Ivan Vorontsov <lantian.ivan@gmail.com>                 |
  +------------------------------------------------------------------------+
*/

namespace Core;

use Core\Model\LanguageModel;
use Core\Model\LanguageTranslationModel;
use Core\Model\SettingsModel;
use Core\Model\WidgetModel;
use Engine\AbstractBootstrap;
use Engine\Behaviour\DIBehaviour;
use Engine\Cache\System;
use Engine\Config;
use Engine\Translation\Db as TranslationDb;
use Phalcon\DI;
use Phalcon\DiInterface;
use Phalcon\Events\Manager;
use Phalcon\Translate\Adapter\NativeArray as TranslateArray;
use User\Model\UserModel;

/**
 * Core Bootstrap.
 *
 * @category  PhalconEye
 * @package   Core
 * @author    Ivan Vorontsov <lantian.ivan@gmail.com>
 * @copyright 2013-2016 PhalconEye Team
 * @license   New BSD License
 * @link      http://phalconeye.com/
 */
class Bootstrap extends AbstractBootstrap
{
    /**
     * Current module name.
     *
     * @var string
     */
    protected $_moduleName = __NAMESPACE__;

    /**
     * Bootstrap construction.
     *
     * @param DIBehaviour|DI $di Dependency injection.
     * @param Manager        $em Events manager object.
     */
    public function __construct($di, $em)
    {
        parent::__construct($di, $em);

        /**
         * Attach this bootstrap for all application initialization events.
         */
        $em->attach('init', $this);
    }

    /**
     * Init some subsystems after engine initialization.
     */
    public function afterEngine()
    {
        $di = $this->getDI();
        $config = $this->getConfig();

        $this->_initI18n($di, $config);
        if (!$config->installed) {
            return;
        }

        // Remove profiler for non-user.
        if (!UserModel::getViewer()->id) {
            $di->remove('profiler');
        }

        // Init widgets system.
        $this->_initWidgets($di);

        /**
         * Listening to events in the dispatcher using the Acl.
         */
        if ($config->installed) {
            $this->getEventsManager()->attach('dispatch', $di->get('core')->acl());
        }

        // Install assets if required.
        if ($config->application->debug) {
            $di->get('assets')->installAssets(PUBLIC_PATH . '/themes/' . SettingsModel::getValue('system', 'theme'));
        }
    }

    /**
     * Init locale.
     *
     * @param DIBehaviour|DI $di     Dependency injection.
     * @param Config         $config Dependency injection.
     *
     * @return void
     */
    protected function _initI18n($di, $config)
    {
        if ($di->get('app')->isConsole()) {
            return;
        }

        $languageObject = null;
        if (!$di->get('session')->has('language')) {
            /** @var LanguageModel $languageObject */
            if ($config->installed) {
                $language = SettingsModel::getValue('system', 'default_language');
                if ($language == 'auto') {
                    $locale = \Locale::acceptFromHttp($_SERVER["HTTP_ACCEPT_LANGUAGE"]);
                    $languageObject = LanguageModel::findFirst(
                        "language = '" . $locale . "' OR locale = '" . $locale . "'"
                    );
                } else {
                    $languageObject = LanguageModel::findFirst("language = '" . $language . "'");
                }
            }

            if ($languageObject) {
                $di->get('session')->set('language', $languageObject->language);
                $di->get('session')->set('locale', $languageObject->locale);
            } else {
                $di->get('session')->set('language', Config::CONFIG_DEFAULT_LANGUAGE);
                $di->get('session')->set('locale', Config::CONFIG_DEFAULT_LOCALE);
            }
        }

        $language = $di->get('session')->get('language');
        $translate = null;

        if (!$config->application->debug || !$config->installed) {
            $messages = [];
            $directory = $config->application->languages->cacheDir;
            $extension = ".php";

            if (file_exists($directory . $language . $extension)) {
                require $directory . $language . $extension;
            } else {
                if (file_exists($directory . Config::CONFIG_DEFAULT_LANGUAGE . $extension)) {
                    // fallback to default
                    require $directory . Config::CONFIG_DEFAULT_LANGUAGE . $extension;
                }
            }

            $translate = new TranslateArray(
                [
                    "content" => $messages
                ]
            );
        } else {
            if (!$languageObject) {
                $languageObject = LanguageModel::findFirst(
                    [
                        'conditions' => 'language = :language:',
                        'bind' => (
                        [
                            "language" => $language
                        ]
                        )
                    ]
                );

                if (!$languageObject) {
                    $languageObject = LanguageModel::findFirst("language = '" . Config::CONFIG_DEFAULT_LANGUAGE . "'");
                }
            }

            $translate = new TranslationDb($di, $languageObject->getId(), new LanguageTranslationModel());
        }

        $di->set('i18n', $translate);
    }

    /**
     * Prepare widgets metadata for Engine.
     *
     * @param DIBehaviour|DI $di Dependency injection.
     *
     * @return void
     */
    protected function _initWidgets($di)
    {
        if ($di->get('app')->isConsole()) {
            return;
        }

        $cache = $di->get('cacheData');
        $widgets = $cache->get(System::CACHE_KEY_WIDGETS_METADATA);

        if ($widgets === null) {
            $widgets = [];
            foreach (WidgetModel::find() as $object) {
                $widgets[] = [$object->id, $object->getKey(), $object];
            }

            $cache->save(System::CACHE_KEY_WIDGETS_METADATA, $widgets, 0); // Unlimited.
        }
        $di->get('widgets')->addWidgets($widgets);
    }
}