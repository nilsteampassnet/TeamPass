<?php
/*
 * Author: Ryan Gilfether
 * URL: http://www.gilfether.com/phpCrypt
 * Date: May 3, 2013
 * Copyright (C) 2013 Ryan Gilfether
 *
 * This file is part of phpCrypt
 *
 * phpCrypt is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see <http://www.gnu.org/licenses/>.
 */

namespace PHP_CRYPT;
require_once(dirname(__FILE__)."/../Cipher.php");
require_once(dirname(__FILE__)."/../phpCrypt.php");
require_once(dirname(__FILE__)."/Raw.php");


/**
 * Used only on Stream Ciphers, this isn't really a mode, it's
 * just a way to apply the stream cipher to the data being encrypted
 * Since it's basically the same as the Mode_Raw, we just extend from Mode_Raw
 *
 * @author Ryan Gilfether
 * @link http://www.gilfether.com/phpcrypt
 * @copyright 2013 Ryan Gilfether
 */
class Mode_Stream extends Mode_Raw
{
    /**
     * Constructor
     * Sets the cipher object that will be used for encryption
     *
     * @param object $cipher one of the phpCrypt encryption cipher objects
     * @return void
     */
    public function __construct($cipher)
    {
        // call the secondary 'constructor' from the parent
        parent::__construct1(PHP_Crypt::MODE_STREAM, $cipher);

        // this works with only stream Ciphers
        if ($cipher->type() != Cipher::STREAM) {
                    trigger_error("Stream mode requires a stream cipher", E_USER_WARNING);
        }
    }


    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct()
    {
        parent::__destruct();
    }
}
?>
