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

namespace User;

use Engine\AbstractInstaller;

/**
 * User module installer.
 *
 * @category  PhalconEye
 * @package   User
 * @author    Ivan Vorontsov <lantian.ivan@gmail.com>
 * @copyright 2013-2016 PhalconEye Team
 * @license   New BSD License
 * @link      http://phalconeye.com/
 */
class Installer extends AbstractInstaller
{
    CONST
        /**
         * Current package version.
         */
        CURRENT_VERSION = '0.4.0';

    /**
     * Used to install specific database entities or other specific action.
     *
     * @return void
     */
    public function install()
    {

    }

    /**
     * Used before package will be removed from the system.
     *
     * @return void
     */
    public function remove()
    {

    }

    /**
     * Used to apply some updates.
     * Return 'string' (new version) if migration is not finished, 'null' if all updates were applied.
     *
     * @param string $currentVersion Current module version.
     *
     * @return string|null
     */
    public function update($currentVersion)
    {
        return $currentVersion = null;
    }
}