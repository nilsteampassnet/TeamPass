<?php

require '../../vendor/autoload.php';
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\RandomGenerator\Php7RandomGenerator;
use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception as CryptoException;

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Initialize variables
$rootPath = __DIR__.'/../../';
$settingsFile = $rootPath.'includes/config/settings1.php';
$settingsFileStatus = createSettingsFile($rootPath, $settingsFile);

if ($settingsFileStatus['success'] === true) {
    $response = [
        'success' => true,
        'message' => '<i class="fa-solid fa-check"></i> Done',
        'data' => [
            'rootPath' => $rootPath,
            'status' => $settingsFileStatus['data']['status'],
            'securePath' => $settingsFileStatus['data']['securePath'],
            'secureFile' => $settingsFileStatus['data']['secureFile'],
            'randomInstalldKey' => generateRandomKey(),
        ],
    ];
} else {
    $response = [
        'success' => false,
        'message' => $settingsFileStatus['message'],
    ];
}

echo json_encode($response);

/**
 * Create settings.php file
 * 
 * @param string $rootPath
 * 
 * @return array
 */
function createSettingsFile($rootPath, $settingsFile): array
{
    // Ensure that the file settings.php exists
    if (file_exists($settingsFile) === false) {
        $settingsSampleFile = $rootPath.'includes/config/settings.sample.php';

        if (copy($settingsSampleFile, $settingsFile) === false) {

            return [
                'success' => false,
                'message' => 'File <i>' . $settingsFile . '</i> could not be copied from <i>'.$settingsSampleFile.'</i>. You have 2 possible actions:<br>'.
                '1- Manually perform a copy of file <i>' . $settingsSampleFile . '</i> and rename it as <i>'.$settingsFile.'</i>.<br>'.
                'or 2- Change the user rights to 0755 on <i>includes/config/</i> and its content.',
            ];
        }

        $SECUREPATH = $rootPath.'includes/config';
        $SECUREFILE = generateRandomKey();

        // 1- generate saltkey
        $key = Key::createNewRandomKey();
        $new_salt = $key->saveToAsciiSafeString();

        // 2- store key in file
        file_put_contents(
            $SECUREPATH.'/'.$SECUREFILE,
            $new_salt
        );

        //3 - add to settings
        $newLine = '
define("SECUREPATH", "' . $SECUREPATH. '");
define("SECUREFILE", "' . $SECUREFILE. '");
    ';
        file_put_contents($settingsFile, $newLine, FILE_APPEND);
    } else {
        // Read the file
        $fileContent = file_get_contents($settingsFile);

        if ($fileContent === false) {
            die("Impossible de lire le fichier $settingsFile.");
        }

        // Expression régulière pour extraire la valeur de la constante SECUREFILE
        $pattern = '/define\s*\(\s*["\']SECUREFILE["\']\s*,\s*["\']([^"\']+)["\']\s*\)\s*;/';
        if (preg_match($pattern, $fileContent, $matches)) {
            $SECUREFILE = $matches[1];
        }

        $pattern = '/define\s*\(\s*["\']SECUREPATH["\']\s*,\s*["\']([^"\']+)["\']\s*\)\s*;/';
        if (preg_match($pattern, $fileContent, $matches)) {
            $SECUREPATH = $matches[1];
        }
    }

    return [
        'success' => true,
        'data' => [
            'status' => isset($SECUREPATH) ? 'created' : 'exists',
            'securePath' => isset($SECUREPATH) ? $SECUREPATH : '',
            'secureFile' => isset($SECUREFILE) ? $SECUREFILE : '',
        ],
    ];
}


/**
 * Generate a random key
 * 
 * @return string
 */
function generateRandomKey(): string
{
    $generator = new ComputerPasswordGenerator();
    $generator->setRandomGenerator(new Php7RandomGenerator());
    $generator->setLength(40);
    $generator->setSymbols(false);
    $generator->setLowercase(true);
    $generator->setUppercase(true);
    $generator->setNumbers(true);

    $key = $generator->generatePasswords();

    return $key[0];
}

/**
 * Permits to encrypt a message using Defuse.
 *
 * @param string $message   Message to encrypt
 * @param string $ascii_key Key to hash
 *
 * @return array String + Error
 */
function encryptFollowingDefuse($message, $ascii_key): array
{
    // convert KEY
    $key = Key::loadFromAsciiSafeString($ascii_key);

    try {
        $text = Crypto::encrypt($message, $key);
    } catch (CryptoException\WrongKeyOrModifiedCiphertextException $ex) {
        $err = 'an attack! either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.';
    } catch (CryptoException\BadFormatException $ex) {
        $err = $ex;
    } catch (CryptoException\EnvironmentIsBrokenException $ex) {
        $err = $ex;
    } catch (CryptoException\CryptoException $ex) {
        $err = $ex;
    } catch (CryptoException\IOException $ex) {
        $err = $ex;
    }

    return array(
        'string' => isset($text) ? $text : '',
        'error' => $err,
    );
}