<?php

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

// Initialiser les tableaux
$inputData = [];
$filters = [];

// Boucle pour récupérer les variables POST et constituer les tableaux
foreach ($keys as $key) {
    $inputData[$key] = $superGlobal->get($key, 'POST') ?? '';
    $filters[$key] = 'trim|escape';
}
$inputData = dataSanitizer(
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

    // Convertir en tableau associatif
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
            // Vérifiez si le serveur est Linux (pas Windows)
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                return [
                    'success' => true,
                    'message' => "CHMOD changes are not supported on Windows servers.",
                ];
            }

            // Obtenir le chemin absolu de Teampass
            $absolutePath = rtrim($this->installConfig['teampassAbsolutePath'], '/');
            if (!is_dir($absolutePath)) {
                return [
                    'success' => false,
                    'message' => "Invalid Teampass absolute path: $absolutePath",
                ];
            }

            // Dossiers et permissions à appliquer
            $directories = [
                $absolutePath              => ['dir' => 0770, 'file' => 0740],
                $absolutePath . '/files'   => ['dir' => 0770, 'file' => 0770],
                $absolutePath . '/upload'  => ['dir' => 0770, 'file' => 0770],
            ];

            // Appliquer les permissions
            foreach ($directories as $path => $permissions) {
                $result = recursiveChmod($path, $permissions['dir'], $permissions['file']);
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
            // Obtenir le chemin absolu
            $absolutePath = rtrim($this->installConfig['teampassAbsolutePath'], '/');
            $configPath = $absolutePath . '/includes/libraries/csrfp/libs/';
            $csrfpFileSample = $configPath . 'csrfp.config.sample.php';
            $csrfpFile = $configPath . 'csrfp.config.php';

            // Étape 1 : Vérifier l'existence et sauvegarder le fichier actuel
            if (file_exists($csrfpFile)) {
                $backupFileName = $csrfpFile . '.' . date('Y_m_d') . '.bak';
                if (!@copy($csrfpFile, $backupFileName)) {
                    return [
                        'success' => false,
                        'message' => "Unable to back up the existing csrfp.config.php file. Please rename or remove it manually.",
                    ];
                }
            }

            // Étape 2 : Copier le fichier d'exemple vers la configuration
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

            // Étape 3 : Modifier les paramètres du fichier de configuration
            $data = file_get_contents($csrfpFile);
            if ($data === false) {
                return [
                    'success' => false,
                    'message' => "Failed to read the configuration file: $csrfpFile",
                ];
            }

            // Générer un nouveau token CSRF sécurisé
            $csrfToken = bin2hex(openssl_random_pseudo_bytes(25));
            $data = str_replace(
                '"CSRFP_TOKEN" => ""',
                '"CSRFP_TOKEN" => "' . $csrfToken . '"',
                $data
            );

            // Ajouter l'URL du script JS
            $jsUrl = './includes/libraries/csrfp/js/csrfprotector.js';
            $data = str_replace(
                '"jsUrl" => ""',
                '"jsUrl" => "' . $jsUrl . '"',
                $data
            );

            // Enregistrer les modifications
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
            // Obtenir les chemins nécessaires
            $absolutePath = rtrim($this->installConfig['teampassAbsolutePath'], '/');
            $securePath = rtrim($this->installConfig['teampassSecurePath'], '/');
            $settingsFile = $absolutePath . '/includes/config/settings.php';
            $backupFile = $settingsFile . '.' . date('Y_m_d_His') . '.bak';
    
            // Vérifier les permissions
            if (!is_writable($absolutePath . '/includes/config')) {
                return [
                    'success' => false,
                    'message' => "The settings file directory is not writable. Check permissions for: " . $absolutePath . '/includes/config',
                ];
            }
    
            // Sauvegarder le fichier settings.php existant
            if (file_exists($settingsFile) && !@copy($settingsFile, $backupFile)) {
                return [
                    'success' => false,
                    'message' => "Unable to back up the existing settings.php file. Please rename or remove it manually.",
                ];
            }
    
            // Obtenir la clé d'encryption
            $secureFilePath = $securePath . '/' . $this->installConfig['teampassSecureFile'];
            if (!file_exists($secureFilePath) || !is_readable($secureFilePath)) {
                return [
                    'success' => false,
                    'message' => "Encryption key file not found or not readable: " . $secureFilePath,
                ];
            }
            $encryptionKey = file_get_contents($secureFilePath);
    
            // Chiffrer le mot de passe admin
            $encryptedPassword = encryptFollowingDefuse(
                $this->installConfig['adminPassword'],
                $encryptionKey
            )['string'];
    
            // Créer le contenu du fichier settings.php
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
    
            // Écrire le fichier settings.php
            if (file_put_contents($settingsFile, utf8_encode($settingsContent)) === false) {
                return [
                    'success' => false,
                    'message' => "Failed to write settings.php. Check file permissions.",
                ];
            }
    
            // Vérifier si l'utilisateur TP existe
            $tpUserExists = DB::queryFirstField(
                "SELECT COUNT(*) FROM %busers WHERE id = %i",
                $this->inputData['tablePrefix'],
                TP_USER_ID
            );
    
            if (intval($tpUserExists) === 0) {
                // Générer les clés et le mot de passe utilisateur
                $userPassword = GenerateCryptKey(25, true, true, true, true);
                $encryptedUserPassword = cryption(
                    $userPassword,
                    $encryptionKey,
                    'encrypt'
                )['string'];
                $userKeys = generateUserKeys($userPassword);
    
                // Insérer l'utilisateur TP
                DB::insert($this->inputData['tablePrefix'] . 'users', [
                    'id'                     => TP_USER_ID,
                    'login'                  => 'OTV',
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
            // Gestion des erreurs
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
            // Localiser le binaire PHP
            include_once(__DIR__ . '/../tp.functions.php');
            $phpLocation = findPhpBinary();
            if ($phpLocation['error'] === true) {
                return [
                    'success' => false,
                    'message' => "Unable to locate PHP binary. Error: " . $phpLocation['message'],
                ];
            }

            // Obtenir le chemin absolu de Teampass
            $absolutePath = rtrim($this->installConfig['teampassAbsolutePath'], '/');
            if (!is_dir($absolutePath)) {
                return [
                    'success' => false,
                    'message' => "Invalid Teampass absolute path: $absolutePath",
                ];
            }

            // Initialiser le crontab adapter et repository
            $crontabRepository = new CrontabRepository(new CrontabAdapter());

            // Vérifier si une tâche cron pour Teampass existe déjà
            $existingJobs = $crontabRepository->findJobByRegex('/Teampass\ scheduler/');
            if (count($existingJobs) > 0) {
                return [
                    'success' => true,
                    'message' => "Cron job for Teampass already exists.",
                ];
            }

            // Ajouter une nouvelle tâche cron
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
            // Gestion des erreurs avec des messages précis
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
            DB::query(
                "INSERT INTO " . $this->inputData['tablePrefix'] . "misc 
                    (`type`, `intitule`, `valeur`) VALUES 
                    ('install', 'clear_install_folder', 'true');"
            );
            
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
}