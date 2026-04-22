<?php
/**
 * Bootstrap the library.  This registers a simple autoloader for autoloading
 * classes
 *
 * If you are using this library inside of another that uses a similar
 * autoloading system, you can use that autoloader instead of this file.
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib
 * @package    Core
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @license    http://www.gnu.org/licenses/lgpl-2.1.html LGPL v 2.1
 */

namespace PasswordLib;

\Phar::mapPhar('PasswordLib.phar');
\Phar::interceptFileFuncs();

require_once 'phar://PasswordLib.phar/PasswordLib/Core/AutoLoader.php';

$autoloader = new \PasswordLib\Core\AutoLoader(__NAMESPACE__, 'phar://PasswordLib.phar');

$autoloader->register();

__HALT_COMPILER();
