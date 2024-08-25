<?php
/**
 * An example file demonstrating the generation of random strings.
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib-Examples
 * @package    Random
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @license    http://www.gnu.org/licenses/lgpl-2.1.html LGPL v 2.1
 */

namespace PasswordLibExamples\Random;

/**
 * Let's generate some random strings!  For this, we'll use the
 * PasswordLib\Random\Factory class to build a random number generator to suit
 * our needs here.
 */

//We first load the bootstrap file so we have access to the library
require_once dirname(dirname(__DIR__)) . '/lib/PasswordLib/bootstrap.php';

//Now, let's get a random number factory
$factory = new \PasswordLib\Random\Factory;

/**
 * Now, since we want a low strength random number, let's get a low strength
 * generator from the factory.
 *
 * If we wanted stronger random numbers, we could change this to medium or high
 * but both use significantly more resources to generate, so let's just stick
 * with low for the purposes of this example:
 */
$generator = $factory->getLowStrengthGenerator();

/**
 * We can now start generating our random strings.  The generator by default
 * outputs full-byte strings (character 0 - 255), so it's not safe to display
 * them directly.  Instead, let's convert them to hex to show the string.
 */
$number = $generator->generate(8);

printf("\nHere's our first random string: %s\n", bin2hex($number));

/**
 * We can also base64 encode it to display the string
 */
$number = $generator->generate(8);

printf("\nHere's a base64 encoded random string: %s\n", base64_encode($number));

/**
 * But, we can also generate random strings against a list of characters.  That
 * way we can use the random string in user-facing situations:  (this can be for
 * one-time-use passwords, CRSF tokens, etc).
 *
 * Now, let's define a string of allowable characters to use for token
 * generation.
 */
$characters = '0123456789abcdefghijklmnopqrstuvwxyz' .
              'ABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%&;<>?';

/**
 * After that, we can generate the random string
 */

$string = $generator->generateString(16, $characters);

printf("\nHere's our token: %s\n", $string);
