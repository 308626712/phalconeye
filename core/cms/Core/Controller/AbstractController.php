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
  | Author: Piotr Gasiorowski <p.gasiorowski@vipserv.org>                  |
  +------------------------------------------------------------------------+
*/

namespace Core\Controller;

use Core\Behaviour\JsTranslationBehaviour;
use Core\Helper\RendererHelper;
use Core\Model\PageModel;
use Engine\Asset\Manager as AssetManager;
use Engine\Behaviour\DIBehaviour;
use Phalcon\Db\Column;
use Phalcon\DI;
use Phalcon\Mvc\Controller as PhalconController;

/**
 * Base controller.
 *
 * @category  PhalconEye
 * @package   Core\Controller
 * @author    Ivan Vorontsov <lantian.ivan@gmail.com>
 * @author    Piotr Gasiorowski <p.gasiorowski@vipserv.org>
 * @copyright 2013-2016 PhalconEye Team
 * @license   New BSD License
 * @link      http://phalconeye.com/
 *
 * @property \Phalcon\Db\Adapter\Pdo    $db
 * @property \Phalcon\Cache\Backend     $cacheData
 * @property \Engine\Application        $app
 * @property \Engine\View               $view
 * @property \Engine\Asset\Manager      $assets
 * @property \Engine\Config             $config
 * @property \Phalcon\Translate\Adapter $i18n
 * @property DIBehaviour|DI             $di
 *
 * @method \Engine\DIBehaviour|\Phalcon\DI getDI()
 */
abstract class AbstractController extends PhalconController
{
    use JsTranslationBehaviour;

    /**
     * Disable header rendering.
     *
     * @var bool
     */
    private $_hideHeader = false;

    /**
     * Disable footer rendering.
     *
     * @var bool
     */
    private $_hideFooter = false;

    /**
     * Initializes the controller.
     *
     * @return void
     */
    public function initialize()
    {
        if ($this->di->has('profiler')) {
            $this->profiler->start();
        }

        if (!$this->request->isAjax()) {
            $this->_setupAssets();
        }

        // Run init function.
        if (method_exists($this, 'init')) {
            $this->init();
        }
    }

    /**
     * After route execution.
     *
     * @return void
     */
    public function afterExecuteRoute()
    {
        if ($this->di->has('profiler')) {
            $this->profiler->stop(get_called_class(), 'controller');
        }

        $this->view->pickDefaultView();
    }

    /**
     * Render some content from database layout.
     *
     * @param null|string $url        Url definition of page.
     * @param null|string $controller Related controller name.
     * @param null|string $type       Page type.
     *
     * @return mixed
     */
    public function renderContent($url = null, $controller = null, $type = null)
    {
        $page = null;
        if ($url !== null) {
            $page = PageModel::find(
                [
                    'conditions' => 'url=:url1: OR url=:url2: OR id = :url3:',
                    'bind' => ["url1" => $url, "url2" => '/' . $url, "url3" => $url],
                    'bindTypes' => [
                        "url1" => Column::BIND_PARAM_STR,
                        "url2" => Column::BIND_PARAM_STR,
                        "url3" => Column::BIND_PARAM_INT
                    ]
                ]
            )->getFirst();

        } elseif ($controller !== null) {
            $page = PageModel::find(
                [
                    'conditions' => 'controller=:controller:',
                    'bind' => ["controller" => $controller],
                    'bindTypes' => ["controller" => Column::BIND_PARAM_STR]
                ]
            )->getFirst();
        } elseif ($type !== null) {
            $page = PageModel::find(
                [
                    'conditions' => 'type=:type:',
                    'bind' => ["type" => $type],
                    'bindTypes' => ["type" => Column::BIND_PARAM_STR]
                ]
            )->getFirst();
        }


        if (!$page || !$page->isAllowed()) {
            return $this->dispatcher->forward(
                [
                    'controller' => 'Error',
                    'action' => 'show404'
                ]
            );
        }

        // Resort content by sides.
        $content = [];
        $renderer = RendererHelper::getInstance($this->getDI());
        foreach ($page->getWidgets() as $widget) {
            $content[$widget->layout][] = $renderer->renderWidgetId($widget->widget_id, $widget->getParams());
        }

        $this->renderParts($renderer);

        $this->view->content = $content;
        $this->view->page = $page;

        $this->view->pick('layouts/page', 'core', true);
    }

    /**
     * Disable header rendering.
     *
     * @return $this
     */
    public function hideHeader()
    {
        $this->view->hideHeader = $this->_hideHeader = true;
        return $this;
    }

    /**
     * Disable footer rendering.
     *
     * @return $this
     */
    public function hideFooter()
    {
        $this->view->hideFooter = $this->_hideFooter = true;

        return $this;
    }

    /**
     * Resolve modal window result.
     *
     * @param array $params Modal params.
     *
     * @return void
     */
    public function resolveModal(array $params = [])
    {
        if (empty($params)) {
            $params['hide'] = true;
        }

        $this->view->setVars($params, false);
        $this->view->hideSave = true;
        $this->view->pick('utils/modal', 'core', true);
    }

    /**
     * Setup assets.
     *
     * @return void
     */
    protected function _setupAssets()
    {
        $this->assets->set(
            AssetManager::DEFAULT_COLLECTION_CSS,
            $this->assets->getEmptyCssCollection()
                ->addCss('libs/jquery/jquery-ui.css')
                ->addCss('application/css/constants.css')
                ->addCss('application/css/theme.css')
        );

        $this->assets->set(
            AssetManager::DEFAULT_COLLECTION_JS,
            $this->assets->getEmptyJsCollection()
                ->addJs('libs/jquery/jquery-2.1.0.js')
                ->addJs('libs/jquery/jquery-ui-1.10.4.js')
                ->addJs('libs/jquery/jquery.cookie.js')
                ->addJs('application/js/core/core.js')
                ->addJs('application/js/core/i18n.js')
                ->addJs('application/js/core/form.js')
                ->addJs('application/js/core/form/dynamic-field.js')
                ->addJs('application/js/core/form/remote-file.js')
                ->addJs('application/js/core/widgets/grid.js')
                ->addJs('application/js/core/widgets/autocomplete.js')
                ->addJs('application/js/core/widgets/modal.js')
                ->addJs('application/js/core/widgets/ckeditor.js')
        );

        if ($this->di->has('profiler')) {
            $this->di->get('assets')
                ->collection(AssetManager::DEFAULT_COLLECTION_CSS)
                ->addCss('application/css/core/profiler.css');

            $this->di->get('assets')
                ->collection(AssetManager::DEFAULT_COLLECTION_JS)
                ->addCss('application/js/core/profiler.js');
        }

        $this->addDefaultJsTranslations();
    }

    /**
     * Render left parts of the page (header and footer).
     *
     * @return void
     */
    public function renderParts()
    {
        $renderer = RendererHelper::getInstance($this->getDI());

        // Store result, and only after finishing the render process - assign.
        $contentHeader = $this->getHeader($renderer);
        $contentFooter = $this->getFooter($renderer);

        $this->view->contentHeader = $contentHeader;
        $this->view->contentFooter = $contentFooter;
    }

    /**
     * Render header part.
     *
     * @param RendererHelper|null $renderer Renderer helper object.
     *
     * @return string
     */
    public function getHeader($renderer = null)
    {
        if ($this->_hideHeader) {
            return '';
        }

        if (!$renderer) {
            $renderer = RendererHelper::getInstance($this->getDI());
        }

        return $renderer->renderContent(
            PageModel::PAGE_TYPE_HEADER, $this->view->resolveView("partials/layout", 'core')
        );
    }

    /**
     * Render footer part.
     *
     * @param RendererHelper|null $renderer Renderer helper object.
     *
     * @return string
     */
    public function getFooter($renderer = null)
    {
        if ($this->_hideFooter) {
            return '';
        }

        if (!$renderer) {
            $renderer = RendererHelper::getInstance($this->getDI());
        }

        return $renderer->renderContent(
            PageModel::PAGE_TYPE_FOOTER, $this->view->resolveView("partials/layout", 'core')
        );
    }
}