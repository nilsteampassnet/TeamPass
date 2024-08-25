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
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    Build @@version@@
 */

namespace PasswordLib;

require_once __DIR__ . '/Core/AutoLoader.php';

$autoloader = new Core\AutoLoader(__NAMESPACE__, dirname(__DIR__));

$autoloader->register();