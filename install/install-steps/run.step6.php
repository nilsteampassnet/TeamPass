<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      run.step6.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require '../../vendor/autoload.php';
use TeampassClasses\SuperGlobal\SuperGlobal;
use Defuse\Crypto\Key;
use TiBeN\CrontabManager\CrontabJob;
use TiBeN\CrontabManager\CrontabAdapter;
use TiBeN\CrontabManager\CrontabRepository;

// Get some data
require_once __DIR__.'/../../includes/config/include.php';
// Load functions
include_once(__DIR__ . '/../tp.functions.php');
require_once __DIR__.'/install.functions.php';

$superGlobal = new SuperGlobal();

// Initialize variables
$keys = [
    'action',
    'dbHost',
    'dbName',
    'dbLogin',
    'dbPw',
    'dbPort',
    'tablePrefix',
];

// Initialize arrays
$inputData = [];
$filters = [];

// Loop to retrieve POST variables and build arrays
foreach ($keys as $key) {
    $inputData[$key] = $superGlobal->get($key, 'POST') ?? '';
    $filters[$key] = 'trim|escape';
}
$inputData = dataSanitizerForInstall(
    $inputData,
    $filters
);

// Set the headers
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Perform checks
$databaseStatus = checks($inputData);

// Prepare the response
if ($databaseStatus['success'] === true) {
    $response = [
        'success' => true,
        'message' => '<i class="fa-solid fa-check"></i> Done',
    ];
} else {
    $response = [
        'success' => false,
        'message' => $databaseStatus['message'],
    ];
}

// Send the response
echo json_encode($response);



/**
 * Checks the data
 * 
 * @param array $inputData
 * 
 * @return string
 */
function checks($inputData): array
{
    // Initialize database connection
    DB::$host = $inputData['dbHost'];
    DB::$user = $inputData['dbLogin'];
    DB::$password = $inputData['dbPw'];
    DB::$dbName = $inputData['dbName'];
    DB::$port = $inputData['dbPort'];
    DB::$encoding = 'utf8';
    DB::$ssl = array(
        "key" => "",
        "cert" => "",
        "ca_cert" => "",
        "ca_path" => "",
        "cipher" => ""
    );
    DB::$connect_options = array(
        MYSQLI_OPT_CONNECT_TIMEOUT => 10
    );
    // Force connecting to this database
    DB::disconnect();
    DB::useDB($inputData['dbName']);

    // Get installation variables
    $installData = DB::query("SELECT `key`, `value` FROM _install");

    // Convert to array
    $installConfig = [];
    foreach ($installData as $row) {
        $installConfig[$row['key']] = $row['value'];
    }

    $installer = new teampassInstaller($inputData, $installConfig);
    $response = $installer->handleAction();
    return $response;

}

class teampassInstaller
{
    private $inputData;
    private $installConfig;

    public function __construct($inputData, $installConfig)
    {
        $this->inputData = $inputData;
        $this->installConfig = $installConfig;
    }

    /**
     * Handles the action
     * 
     * @return array
     */
    public function handleAction(): array
    {
        try {
            if (method_exists($this, $this->inputData['action'])) {
                // Dynamically call the method corresponding to the action
                call_user_func([$this, $this->inputData['action']]);
            } else {
                throw new Exception('Action not recognized: ' . $this->inputData);
            }

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Secure the file
     * 
     * @return array
     */
    private function secureFile(): array
    {
        try {
            // Generale a random file name
            include_once(__DIR__ . '/../tp.functions.php');
            $secureFile = generateRandomKey();
    
            // Generate saltkey
            $key = Key::createNewRandomKey();
            $newSalt = $key->saveToAsciiSafeString();
    
            // Store key in file
            file_put_contents(
                rtrim($this->installConfig['teampassSecurePath'].'/').'/'.$secureFile,
                $newSalt
            );
            
            // Store the secure file name in the database
            DB::insertUpdate('_install', [
                'key' => 'teampassSecureFile',
                'value' => $secureFile,
            ]);
            
            return [
                'success' => true,
            ];
    
        } catch (Exception $e) {
            // Si la connexion échoue, afficher un message d'erreur
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }        
    }

    /**
     * Apply the CHMOD
     * 
     * @return array
     */
    function chmod(): array
    {
        try {
            // Check if the server is Linux (not Windows)
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                return [
                    'success' => true,
                    'message' => "CHMOD changes are not supported on Windows servers.",
                ];
            }

            // Get absolute path to teampass
            $absolutePath = rtrim($this->installConfig['teampassAbsolutePath'], '/');
            if (!is_dir($absolutePath)) {
                return [
                    'success' => false,
                    'message' => "Invalid Teampass absolute path: $absolutePath",
                ];
            }

            // Folders and permissions to apply
            $directories = [
                $absolutePath              => ['dir' => 0770, 'file' => 0740],
                $absolutePath . '/files'   => ['dir' => 0770, 'file' => 0770],
                $absolutePath . '/upload'  => ['dir' => 0770, 'file' => 0770],
            ];

            // Apply permissions
            foreach ($directories as $path => $permissions) {
                $result = recursiveChmodForInstall($path, $permissions['dir'], $permissions['file']);
                if (!$result) {
                    return [
                        'success' => false,
                        'message' => "Failed to change permissions for: $path",
                    ];
                }
            }

            return [
                'success' => true,
                'message' => "Permissions successfully applied to all directories.",
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }


    /**
     * Create the database tables
     * 
     * @return array
     */
    function csrf(): array
    {
        try {
            // Get absolute path to teampass
            $absolutePath = rtrim($this->installConfig['teampassAbsolutePath'], '/');
            $configPath = $absolutePath . '/includes/libraries/csrfp/libs/';
            $csrfpFileSample = $configPath . 'csrfp.config.sample.php';
            $csrfpFile = $configPath . 'csrfp.config.php';

            // Check if the configuration file already exists
            if (file_exists($csrfpFile)) {
                $backupFileName = $csrfpFile . '.' . date('Y_m_d') . '.bak';
                if (!@copy($csrfpFile, $backupFileName)) {
                    return [
                        'success' => false,
                        'message' => "Unable to back up the existing csrfp.config.php file. Please rename or remove it manually.",
                    ];
                }
            }

            // Copy the sample configuration file
            if (!is_readable($csrfpFileSample)) {
                return [
                    'success' => false,
                    'message' => "Sample configuration file not found or not readable: $csrfpFileSample",
                ];
            }
            if (!@copy($csrfpFileSample, $csrfpFile)) {
                return [
                    'success' => false,
                    'message' => "Failed to create csrfp.config.php from sample file. Check write permissions.",
                ];
            }

            // Adapt the configuration file
            $data = file_get_contents($csrfpFile);
            if ($data === false) {
                return [
                    'success' => false,
                    'message' => "Failed to read the configuration file: $csrfpFile",
                ];
            }

            // Generate a CSRF token
            $csrfToken = bin2hex(openssl_random_pseudo_bytes(25));
            $data = str_replace(
                '"CSRFP_TOKEN" => ""',
                '"CSRFP_TOKEN" => "' . $csrfToken . '"',
                $data
            );

            // Add the JS URL
            $jsUrl = './includes/libraries/csrfp/js/csrfprotector.js';
            $data = str_replace(
                '"jsUrl" => ""',
                '"jsUrl" => "' . $jsUrl . '"',
                $data
            );

            // Save the changes
            if (file_put_contents($csrfpFile, $data) === false) {
                return [
                    'success' => false,
                    'message' => "Failed to write changes to the configuration file: $csrfpFile",
                ];
            }

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create the settings file
     * 
     * @return array
     */
    function settingsFile(): array
    {
        include_once(__DIR__ . '/../tp.functions.php');
        
        try {
            // Get expected paths
            $absolutePath = rtrim($this->installConfig['teampassAbsolutePath'], '/');
            $securePath = rtrim($this->installConfig['teampassSecurePath'], '/');
            $settingsFile = $absolutePath . '/includes/config/settings.php';
            $backupFile = $settingsFile . '.' . date('Y_m_d_His') . '.bak';
    
            // Check if the settings file directory is writable
            if (!is_writable($absolutePath . '/includes/config')) {
                return [
                    'success' => false,
                    'message' => "The settings file directory is not writable. Check permissions for: " . $absolutePath . '/includes/config',
                ];
            }
    
            // Save settings.php backup
            if (file_exists($settingsFile) && !@copy($settingsFile, $backupFile)) {
                return [
                    'success' => false,
                    'message' => "Unable to backup the existing settings.php file. Please rename or remove it manually.",
                ];
            }
    
            // Get encryption key
            $secureFilePath = $securePath . '/' . $this->installConfig['teampassSecureFile'];
            if (!file_exists($secureFilePath) || !is_readable($secureFilePath)) {
                return [
                    'success' => false,
                    'message' => "Encryption key file not found or not readable: " . $secureFilePath,
                ];
            }
            $encryptionKey = file_get_contents($secureFilePath);
    
            // Encrypt the database password
            $encryptedPassword = encryptFollowingDefuseForInstall(
                $this->inputData['dbPw'],
                $encryptionKey
            )['string'];
    
            // Create the settings file content
            $settingsContent = '<?php
// DATABASE connexion parameters
define("DB_HOST", "' . $this->inputData['dbHost'] . '");
define("DB_USER", "' . $this->inputData['dbLogin'] . '");
define("DB_PASSWD", "' . str_replace('$', '\$', $encryptedPassword) . '");
define("DB_NAME", "' . $this->inputData['dbName'] . '");
define("DB_PREFIX", "' . $this->inputData['tablePrefix'] . '");
define("DB_PORT", "' . $this->inputData['dbPort'] . '");
define("DB_ENCODING", "utf8");
define("DB_SSL", false); // if DB over SSL then comment this line
// if DB over SSL then uncomment the following lines
//define("DB_SSL", array(
//    "key" => "",
//    "cert" => "",
//    "ca_cert" => "",
//    "ca_path" => "",
//    "cipher" => ""
//));
define("DB_CONNECT_OPTIONS", array(
    MYSQLI_OPT_CONNECT_TIMEOUT => 10
));
define("SECUREPATH", "' . $securePath . '");
define("SECUREFILE", "' . $this->installConfig['teampassSecureFile'] . '");

if (isset($_SESSION[\'settings\'][\'timezone\']) === true) {
    date_default_timezone_set($_SESSION[\'settings\'][\'timezone\']);
}
';
    
            // Write the settings file
            if (file_put_contents($settingsFile, utf8_encode($settingsContent)) === false) {
                return [
                    'success' => false,
                    'message' => "Failed to write settings.php. Check file permissions.",
                ];
            }
    
            // Check if user TP exists
            $tpUserExists = DB::queryFirstField(
                "SELECT COUNT(*) FROM %busers WHERE id = %i",
                $this->inputData['tablePrefix'] . 'users',
                TP_USER_ID
            );
    
            if (intval($tpUserExists) === 0) {
                // Generate a random password for the user
                $userPassword = GenerateCryptKeyForInstall(25, true, true, true, true);
                $encryptedUserPassword = cryptionForInstall(
                    $userPassword,
                    $encryptionKey,
                    'encrypt'
                )['string'];
                $userKeys = generateUserKeysForInstall($userPassword);
    
                // Insert the user into the database
                DB::insert($this->inputData['tablePrefix'] . 'users', [
                    'id'                     => TP_USER_ID,
                    'login'                  => 'TP',
                    'pw'                     => $encryptedUserPassword,
                    'groupes_visibles'       => '',
                    'derniers'               => '',
                    'key_tempo'              => '',
                    'last_pw_change'         => '',
                    'last_pw'                => '',
                    'admin'                  => 1,
                    'fonction_id'            => '',
                    'groupes_interdits'      => '',
                    'last_connexion'         => '',
                    'gestionnaire'           => 0,
                    'email'                  => '',
                    'favourites'             => '',
                    'latest_items'           => '',
                    'personal_folder'        => 0,
                    'public_key'             => $userKeys['public_key'],
                    'private_key'            => $userKeys['private_key'],
                    'is_ready_for_usage'     => 1,
                    'otp_provided'           => 0,
                    'created_at'             => time(),
                ]);
            }
    
            return [
                'success' => true,
                'message' => "Settings file successfully created and user added.",
            ];
    
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "An error occurred: " . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Add a cron job
     * 
     * @return array
     */
    function cronJob(): array
    {
        try {
            // Get the PHP binary location
            include_once(__DIR__ . '/../tp.functions.php');
            $phpLocation = findPhpBinary();
            if ($phpLocation['error'] === true) {
                return [
                    'success' => false,
                    'message' => "Unable to locate PHP binary. Error: " . $phpLocation['message'],
                ];
            }

            // Get absolute path to teampass
            $absolutePath = rtrim($this->installConfig['teampassAbsolutePath'], '/');
            if (!is_dir($absolutePath)) {
                return [
                    'success' => false,
                    'message' => "Invalid Teampass absolute path: $absolutePath",
                ];
            }

            // Initialize the crontab repository
            $crontabRepository = new CrontabRepository(new CrontabAdapter());

            // Check if the cron job already exists
            $existingJobs = $crontabRepository->findJobByRegex('/Teampass\ scheduler/');
            if (count($existingJobs) > 0) {
                return [
                    'success' => true,
                    'message' => "Cron job for Teampass already exists.",
                ];
            }

            // Add the cron job
            $crontabJob = new CrontabJob();
            $crontabJob
                ->setMinutes('*')
                ->setHours('*')
                ->setDayOfMonth('*')
                ->setMonths('*')
                ->setDayOfWeek('*')
                ->setTaskCommandLine($phpLocation['path'] . ' ' . $absolutePath . '/sources/scheduler.php')
                ->setComments('Teampass scheduler');

            // Ajouter et enregistrer la tâche dans le crontab
            $crontabRepository->addJob($crontabJob);
            $crontabRepository->persist();

            return [
                'success' => true,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }


    /**
     * Clean the install folder
     * 
     * @return array
     */
    function cleanInstall(): array
    {
        try {
            // Delete table _install
            DB::query(
                "DROP TABLE IF EXISTS " . $this->inputData['tablePrefix'] . "_install;"
            );

            // Save the installation status
            DB::query(
                "INSERT INTO " . $this->inputData['tablePrefix'] . "misc 
                    (`type`, `intitule`, `valeur`) VALUES 
                    ('install', 'clear_install_folder', 'true');"
            );
            
            return [
                'success' => true,
            ];
    
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}