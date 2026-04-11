<?php
/**
 * An example file demonstrating the generation and validation of Drupal
 * Passwords (new Style)
 *
 * PHP version 5.3
 *
 * @category   PHPPasswordLib-Examples
 * @package    Password
 * @author     Anthony Ferrara <ircmaxell@ircmaxell.com>
 * @copyright  2011 The Authors
 * @license    http://opensource.org/licenses/bsd-license.php New BSD License
 * @license    http://www.gnu.org/licenses/lgpl-2.1.html LGPL v 2.1
 */

namespace PasswordLibExamples\Password;

//We first load the bootstrap file so we have access to the library
require_once dirname(dirname(__DIR__)) . '/lib/PasswordLib/bootstrap.php';

/**
 * Now, let's create a password that's compatible with Drupal
 */

// First, let's create an instance of Drupal, where 15 is the iteration count for D7
$hasher = new \PasswordLib\Password\Implementation\Drupal(15);

$password = 'FooBarBaz';

// Now, let's create a password hash of the password "FooBarBaz"
$hash = $hasher->create($password);

//It's safe to print, so let's output it:
printf(
    "Password: %s\nHash: %s\n\n",
    $password,
    $hash
);

/**
 * Now, we can also verify any passwords created by Drupal. First, let's load a hash.
 *
 * This works, because it detects the iteration count that's stored in the hash, and
 * pre-configures our instance for us.
 */
$hasher2 = \PasswordLib\Password\Implementation\Drupal::loadFromHash($hash);

/**
 * Next, we verify the hash with the expected password.
 */
$test = $hasher2->verify($password, $hash);

/**
 * $test should now contain a boolean value as to the validity of the hash
 */
printf(
    "Verification was %s\n\n",
    $test ? "Successful!" : "Failed!"
);