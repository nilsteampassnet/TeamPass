<?php
/**
 * An example file demonstrating the generation of random numbers.
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
 * Let's generate some random integers!  For this, we'll use the
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
 * Now, let's start generating our random numbers!  To start off with, let's
 * pick a few random number between 1 and 10
 */
$numbers = array();
for ($i = 0; $i < 5; $i++) {
    $numbers[] = $generator->generateInt(1, 10);
}
vprintf("\nRandom Numbers between 1 and 10: %d, %d, %d, %d, %d \n", $numbers);

/**
 * Now, since we have that down, let's have some fun:
 */
printf("\nA negative random number: %d\n", $generator->generateInt(-100, 0));

printf("\nA really big random number: %d\n", $generator->generateInt(1234567, 12345678));

printf("\nA not-so-random number: %d\n", $generator->generateInt(42, 42));

/**
 * And that's all there is to it!
 */
