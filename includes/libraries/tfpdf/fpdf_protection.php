<?php
/****************************************************************************
 * Software: FPDF_Protection                                                 *
 * Version:  1.03                                                            *
 * Date:     2009-11-29                                                      *
 * Author:   Klemen VODOPIVEC                                                *
 * License:  FPDF                                                            *
 *                                                                           *
 * Thanks:  Cpdf (http://www.ros.co.nz/pdf) was my working sample of how to  *
 *          implement protection in pdf.                                     *
 ****************************************************************************/

require 'tfpdf.php';

if (function_exists('mcrypt_encrypt')) {
    public function RC4($key, $data)
    {
        return mcrypt_encrypt(MCRYPT_ARCFOUR, $key, $data, MCRYPT_MODE_STREAM, '');
    }
} else {
    public function RC4($key, $data)
    {
        static $last_key, $last_state;

        if ($key != $last_key) {
            $k = str_repeat($key, 256/strlen($key)+1);
            $state = range(0, 255);
            $j = 0;
            for ($i=0; $i<256; $i++) {
                $t = $state[$i];
                $j = ($j + $t + ord($k[$i])) % 256;
                $state[$i] = $state[$j];
                $state[$j] = $t;
            }
            $last_key = $key;
            $last_state = $state;
        } else
            $state = $last_state;

        $len = strlen($data);
        $a = 0;
        $b = 0;
        $out = '';
        for ($i=0; $i<$len; $i++) {
            $a = ($a+1) % 256;
            $t = $state[$a];
            $b = ($b+$t) % 256;
            $state[$a] = $state[$b];
            $state[$b] = $t;
            $k = $state[($state[$a]+$state[$b]) % 256];
            $out .= chr(ord($data[$i]) ^ $k);
        }

        return $out;
    }
}

class FPDF_Protection extends tFPDF
{
    public $encrypted = false;  //whether document is protected
    public $Uvalue;             //U entry in pdf document
    public $Ovalue;             //O entry in pdf document
    public $Pvalue;             //P entry in pdf document
    public $enc_obj_id;         //encryption object id

    /**
     * Function to set permissions as well as user and owner passwords
     *
     * - permissions is an array with values taken from the following list:
     *   copy, print, modify, annot-forms
     *   If a value is present it means that the permission is granted
     * - If a user password is set, user will be prompted before document is opened
     * - If an owner password is set, document can be opened in privilege mode with no
     *   restriction if that password is entered
     */
    public function SetProtection($permissions=array(), $user_pass='', $owner_pass=null)
    {
        $options = array('print' => 4, 'modify' => 8, 'copy' => 16, 'annot-forms' => 32 );
        $protection = 192;
        foreach ($permissions as $permission) {
            if (!isset($options[$permission]))
                $this->Error('Incorrect permission: '.$permission);
            $protection += $options[$permission];
        }
        if ($owner_pass === null)
            $owner_pass = uniqid(rand());
        $this->encrypted = true;
        $this->padding = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08".
                        "\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";
        $this->_generateencryptionkey($user_pass, $owner_pass, $protection);
    }

/****************************************************************************
 *                                                                           *
 *                              Private methods                              *
 *                                                                           *
 ****************************************************************************/

    public function _putstream($s)
    {
        if ($this->encrypted) {
            $s = RC4($this->_objectkey($this->n), $s);
        }
        parent::_putstream($s);
    }

    public function _textstring($s)
    {
        if ($this->encrypted) {
            $s = RC4($this->_objectkey($this->n), $s);
        }

        return parent::_textstring($s);
    }

    /**
     * Compute key depending on object number where the encrypted data is stored
     */
    public function _objectkey($n)
    {
        return substr($this->_md5_16($this->encryption_key.pack('VXxx',$n)),0,10);
    }

    public function _putresources()
    {
        parent::_putresources();
        if ($this->encrypted) {
            $this->_newobj();
            $this->enc_obj_id = $this->n;
            $this->_out('<<');
            $this->_putencryption();
            $this->_out('>>');
            $this->_out('endobj');
        }
    }

    public function _putencryption()
    {
        $this->_out('/Filter /Standard');
        $this->_out('/V 1');
        $this->_out('/R 2');
        $this->_out('/O ('.$this->_escape($this->Ovalue).')');
        $this->_out('/U ('.$this->_escape($this->Uvalue).')');
        $this->_out('/P '.$this->Pvalue);
    }

    public function _puttrailer()
    {
        parent::_puttrailer();
        if ($this->encrypted) {
            $this->_out('/Encrypt '.$this->enc_obj_id.' 0 R');
            $this->_out('/ID [()()]');
        }
    }

    /**
     * Get MD5 as binary string
     */
    public function _md5_16($string)
    {
        return pack('H*',md5($string));
    }

    /**
     * Compute O value
     */
    public function _Ovalue($user_pass, $owner_pass)
    {
        $tmp = $this->_md5_16($owner_pass);
        $owner_RC4_key = substr($tmp,0,5);

        return RC4($owner_RC4_key, $user_pass);
    }

    /**
     * Compute U value
     */
    public function _Uvalue()
    {
        return RC4($this->encryption_key, $this->padding);
    }

    /**
     * Compute encryption key
     */
    public function _generateencryptionkey($user_pass, $owner_pass, $protection)
    {
        // Pad passwords
        $user_pass = substr($user_pass.$this->padding,0,32);
        $owner_pass = substr($owner_pass.$this->padding,0,32);
        // Compute O value
        $this->Ovalue = $this->_Ovalue($user_pass,$owner_pass);
        // Compute encyption key
        $tmp = $this->_md5_16($user_pass.$this->Ovalue.chr($protection)."\xFF\xFF\xFF");
        $this->encryption_key = substr($tmp,0,5);
        // Compute U value
        $this->Uvalue = $this->_Uvalue();
        // Compute P value
        $this->Pvalue = -(($protection^255)+1);
    }
}
