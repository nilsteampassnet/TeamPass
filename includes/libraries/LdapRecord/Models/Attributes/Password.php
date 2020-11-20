<?php

namespace LdapRecord\Models\Attributes;

class Password
{
    /**
     * Make an encoded password for transmission over LDAP.
     *
     * @param string $password
     *
     * @return string
     */
    public static function encode($password)
    {
        return iconv('UTF-8', 'UTF-16LE', '"'.$password.'"');
    }

    /**
     * Make a salted SHA password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function ssha($password)
    {
        return '{SSHA}'.static::makeHash($password, 'sha1');
    }

    /**
     * Make a salted SSHA256 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function ssha256($password)
    {
        return '{SSHA256}'.static::makeHash($password, 'hash', 'sha256');
    }

    /**
     * Make a salted SSHA384 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function ssha384($password)
    {
        return '{SSHA384}'.static::makeHash($password, 'hash', 'sha384');
    }

    /**
     * Make a salted SSHA512 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function ssha512($password)
    {
        return '{SSHA512}'.static::makeHash($password, 'hash', 'sha512');
    }

    /**
     * Make a non-salted SHA password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function sha($password)
    {
        return '{SHA}'.static::makeHash($password, 'sha1', $algo = null, $salt = false);
    }

    /**
     * Make a non-salted SHA256 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function sha256($password)
    {
        return '{SHA256}'.static::makeHash($password, 'hash', 'sha256', $salt = false);
    }

    /**
     * Make a non-salted SHA384 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function sha384($password)
    {
        return '{SHA384}'.static::makeHash($password, 'hash', 'sha384', $salt = false);
    }

    /**
     * Make a non-salted SHA512 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function sha512($password)
    {
        return '{SHA512}'.static::makeHash($password, 'hash', 'sha512', $salt = false);
    }

    /**
     * Make a salted md5 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function smd5($password)
    {
        return '{SMD5}'.static::makeHash($password, 'md5', $algo = null, $salt = true);
    }

    /**
     * Make a non-salted md5 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function md5($password)
    {
        return '{MD5}'.static::makeHash($password, 'md5', $algo = null, $salt = false);
    }

    /**
     * Make a new password hash.
     *
     * @param string      $password The password to make a hash of
     * @param string      $method   The hash function to use
     * @param string|null $algo     The algorithm to use for hashing
     * @param bool        $salt     Whether a salt is required
     *
     * @return string
     */
    protected static function makeHash($password, $method, $algo = null, $salt = true)
    {
        $salt = $salt ? random_bytes(4) : null;

        // If no algorithm is given, we don't need to pass it
        // into the method for generating the password hash.
        $params = $algo ? [$algo, $password.$salt] : [$password.$salt];

        return base64_encode(pack('H*', call_user_func($method, ...$params)).$salt);
    }
}
