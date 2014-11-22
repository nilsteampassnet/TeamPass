<?php
/* 
 * Password Hashing With PBKDF2 (http://crackstation.net/hashing-security.htm).
 * Copyright (c) 2013, Taylor Hornby
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, 
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation 
 * and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" 
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE 
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE 
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE 
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR 
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF 
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS 
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN 
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE 
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once('PasswordHash.php');

echo "Sample hash:\n";
$hash = create_hash("test_password");
echo $hash . "\n";

echo "\nTest results:\n";

// Test vector raw output.
$a = bin2hex(pbkdf2("sha1", "password", "salt", 2, 20, true));
$b = "ea6c014dc72d6f8ccd1ed92ace1d41f0d8de8957";
if ($a === $b) {
    echo "pass\n";
} else { 
    echo "FAIL\n";
}

// Test vector hex output.
$a = pbkdf2("sha1", "password", "salt", 2, 20, false);
$b = "ea6c014dc72d6f8ccd1ed92ace1d41f0d8de8957";
if ($a === $b) {
    echo "pass\n";
} else { 
    echo "FAIL\n";
}

$hash_of_password = create_hash("password");

// Test correct password.
if (validate_password("password", $hash_of_password)) {
    echo "pass\n";
} else {
    echo "FAIL\n";
}

// Test wrong password.
if (validate_password("wrong_password", $hash_of_password) === FALSE) {
    echo "pass\n";
} else {
    echo "FAIL\n";
}

// Test bad hash.
if (validate_password("password", "") === FALSE) {
    echo "pass\n";
} else {
    echo "FAIL\n";
}

?>
