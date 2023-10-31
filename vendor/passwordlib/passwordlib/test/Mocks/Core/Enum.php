<?php
/**
 * The interface that all hash implementations must implement
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib
 * @package    Hash
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @license    http://www.gnu.org/licenses/lgpl-2.1.html LGPL v 2.1
 */

namespace PasswordLibTest\Mocks\Core;

/**
 * The interface that all hash implementations must implement
 *
 * @category   PHPPasswordLib
 * @package    Hash
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 */
class Enum extends \PasswordLib\Core\Enum {

    const Value1 = 1;
    const Value2 = 2;
    const Value3 = 3;
    const Value4 = 4;

}
