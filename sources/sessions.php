<?php
/**
 * ------------------------------------------------
 * Encrypt PHP session data using files
 * ------------------------------------------------
 * The encryption is built using mcrypt extension
 * and the randomness is managed by openssl
 * The default encryption algorithm is AES (Rijndael-256)
 * and we use CTR+HMAC (Encrypt-then-mac) with SHA-256
 *
 * @author    Enrico Zimuel (enrico@zimuel.it)
 * @modified  Brian Davis (slimm609@gmail.com)
 * @copyright GNU General Public License
 */
class CryptSession {
     # Encryption algorithm
    protected $_algo= MCRYPT_RIJNDAEL_256;
     # Key for encryption/decryption
    protected $_key;
     # Key for HMAC authentication
    protected $_auth;
     # Path of the session file
    protected $_path;
     # Session name (optional)
    protected $_name;
     # Size of the IV vector for encryption
    protected $_ivSize;
     # Cookie variable name of the encryption + auth key
    protected $_keyName;
     # Generate a random key using openssl
     # fallback to mcrypt_create_iv
    protected function _randomKey($length=32) {
        if(function_exists('openssl_random_pseudo_bytes')) {
            $rnd = openssl_random_pseudo_bytes($length, $strong);
            if ($strong === true) {
                return $rnd;
            }
        }
        if (defined(MCRYPT_DEV_URANDOM)) {
            return mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
        } else {
            throw new Exception("I cannot generate a secure pseudo-random key. Please install OpenSSL or Mcrypt extension");
        }
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        session_set_save_handler(
            array($this, "open"),
            array($this, "close"),
            array($this, "read"),
            array($this, "write"),
            array($this, "destroy"),
            array($this, "gc")
        );
        register_shutdown_function('session_write_close');
    }
    /**
     * Open the session
     *
     * @param  string $save_path
     * @param  string $session_name
     * @return bool
     */
    public function open($save_path, $session_name)
    {
        // Default session path to temp dir
        if($save_path == "") {
            $save_path = sys_get_temp_dir();
        }

        $this->_path    = $save_path.'/';
        $this->_name    = $session_name;
        $this->_keyName = "KEY_$session_name";
        $this->_ivSize  = mcrypt_get_iv_size($this->_algo, 'ctr');

        if (empty($_COOKIE[$this->_keyName]) || strpos($_COOKIE[$this->_keyName],':')===false) {
                $keyLength    = mcrypt_get_key_size($this->_algo, 'ctr');
                $this->_key   = self::_randomKey($keyLength);
                $this->_auth  = self::_randomKey(32);
                $cookie_param = session_get_cookie_params();
                setcookie(
                    $this->_keyName,
                    base64_encode($this->_key) . ':' . base64_encode($this->_auth),
                    $cookie_param['lifetime'],
                    $cookie_param['path'],
                    $cookie_param['domain'],
                    $cookie_param['secure'],
                    $cookie_param['httponly']
                );
        } else {
                list ($this->_key, $this->_auth) = explode (':',$_COOKIE[$this->_keyName]);
                $this->_key  = base64_decode($this->_key);
                $this->_auth = base64_decode($this->_auth);
        }
        return true;
    }
     # Close the session
    public function close()
    {
        return true;
    }
     # Read and decrypt the session
    public function read($id)
    {
        $sess_file = $this->_path.$this->_name."_$id";
        if (@!file_exists($sess_file)) {
            return false;
        }
	  	$data      = file_get_contents($sess_file);
	  	if (empty($data)) {return false;}
        list($hmac, $iv, $encrypted)= explode(':',$data);
        $iv        = base64_decode($iv);
        $encrypted = base64_decode($encrypted);
        $newHmac   = hash_hmac('sha256', $iv . $this->_algo . $encrypted, $this->_auth);
        if ($hmac !== $newHmac) {
            return false;
        }
  	$decrypt = mcrypt_decrypt(
            $this->_algo,
            $this->_key,
            $encrypted,
            'ctr',
            $iv
        );
        return rtrim($decrypt, "\0");
    }
     # Encrypt and write the session
    public function write($id, $data)
    {
        $sess_file = $this->_path . $this->_name . "_$id";
	    $iv = mcrypt_create_iv($this->_ivSize, MCRYPT_DEV_URANDOM);
        $encrypted = mcrypt_encrypt(
            $this->_algo,
            $this->_key,
            $data,
            'ctr',
            $iv
        );
        $hmac  = hash_hmac('sha256', $iv . $this->_algo . $encrypted, $this->_auth);
        $bytes = @file_put_contents($sess_file, $hmac . ':' . base64_encode($iv) . ':' . base64_encode($encrypted));
        return ($bytes !== false); 
    }
     # Destoroy the session
    public function destroy($id)
    { 
    	if (!defined("NODESTROY_SESSION")) {
        	$sess_file = $this->_path . $this->_name . "_$id";
        	@setcookie ($this->_keyName, '', time() - 3600);
			return(@unlink($sess_file));
    	} else {
    		return true;
    	} 
    }
     # Garbage Collector
    public function gc($max)
    {
    	foreach (glob($this->_path . $this->_name . '_*') as $filename) {
            if (filemtime($filename) + $max < time()) {
                @unlink($filename);
            }
  	}
  	return true;
    }
}

new CryptSession();