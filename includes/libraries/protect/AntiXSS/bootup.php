<?php

namespace protect\AntiXSS;

/**
 * Class Bootup
 *
 * this is a bootstrap for the polyfills (iconv / intl / mbstring / normalizer / xml)
 *
 * @package voku\helper
 */
class Bootup
{
  /**
   * filter request inputs
   *
   * Ensures inputs are well formed UTF-8
   * When not, assumes Windows-1252 and converts to UTF-8
   * Tests only values, not keys
   *
   * @param int    $normalization_form
   * @param string $leading_combining
   */
  public static function filterRequestInputs($normalization_form = 4 /* n::NFC */, $leading_combining = '◌')
  {
    $a = array(
        &$_FILES,
        &$_ENV,
        &$_GET,
        &$_POST,
        &$_COOKIE,
        &$_SERVER,
        &$_REQUEST,
    );

    /** @noinspection ReferenceMismatchInspection */
    /** @noinspection ForeachSourceInspection */
    foreach ($a[0] as &$r) {
      $a[] = array(
          &$r['name'],
          &$r['type'],
      );
    }
    unset($r, $a[0]);

    $len = count($a) + 1;
    for ($i = 1; $i < $len; ++$i) {
      /** @noinspection ReferenceMismatchInspection */
      /** @noinspection ForeachSourceInspection */
      foreach ($a[$i] as &$r) {
        /** @noinspection ReferenceMismatchInspection */
        $s = $r; // $r is a reference, $s a copy
        if (is_array($s)) {
          $a[$len++] = &$r;
        } else {
          $r = self::filterString($s, $normalization_form, $leading_combining);
        }
      }
      unset($r, $a[$i]);
    }
  }

  /**
   * Filter current REQUEST_URI .
   *
   * @param string|null $uri <p>If null is set, then the server REQUEST_URI will be used.</p>
   * @param bool        $exit
   *
   * @return mixed
   */
  public static function filterRequestUri($uri = null, $exit = true)
  {
    if (!isset($uri)) {

      if (!isset($_SERVER['REQUEST_URI'])) {
        return false;
      }

      $uri = $_SERVER['REQUEST_URI'];
    }

    $uriOrig = $uri;

    //
    // Ensures the URL is well formed UTF-8
    //

    if (preg_match('//u', urldecode($uri))) {
      return $uri;
    }

    //
    // When not, assumes Windows-1252 and redirects to the corresponding UTF-8 encoded URL
    //

    $uri = preg_replace_callback(
        '/[\x80-\xFF]+/',
        function ($m) {
          return urlencode($m[0]);
        },
        $uri
    );

    $uri = preg_replace_callback(
        '/(?:%[89A-F][0-9A-F])+/i',
        function ($m) {
          return urlencode(UTF8::encode('UTF-8', urldecode($m[0])));
        },
        $uri
    );

    if (
        $uri !== $uriOrig
        &&
        $exit === true
        &&
        headers_sent() === false
    ) {
      // Use ob_start() to buffer content and avoid problem of headers already sent...
      $severProtocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1');
      header($severProtocol . ' 301 Moved Permanently');
      header('Location: ' . $uri);
      exit();
    }

    return $uri;
  }

  /**
   * Normalizes to UTF-8 NFC, converting from WINDOWS-1252 when needed.
   *
   * @param string $s
   * @param int    $normalization_form
   * @param string $leading_combining
   *
   * @return string
   */
  public static function filterString($s, $normalization_form = 4 /* n::NFC */, $leading_combining = '◌')
  {
    return UTF8::filter($s, $normalization_form, $leading_combining);
  }

  /**
   * Get random bytes via "random_bytes()" (+ polyfill).
   *
   * @ref https://github.com/paragonie/random_compat/
   *
   * @param  int $length Output length
   *
   * @return  string|false false on error
   */
  public static function get_random_bytes($length)
  {
    if (!$length) {
      return false;
    }

    $length = (int)$length;

    if ($length <= 0) {
      return false;
    }

    return random_bytes($length);
  }

  /**
   * bootstrap
   */
  public static function initAll()
  {
    ini_set('default_charset', 'UTF-8');

    // everything is init via composer, so we are done here ...
  }

  /**
   * Determines if the current version of PHP is equal to or greater than the supplied value.
   *
   * @param string $version
   *
   * @return bool <p>Return <strong>true</strong> if the current version is $version or higher</p>
   */
  public static function is_php($version)
  {
    static $_IS_PHP;

    $version = (string)$version;

    if (!isset($_IS_PHP[$version])) {
      $_IS_PHP[$version] = version_compare(PHP_VERSION, $version, '>=');
    }

    return $_IS_PHP[$version];
  }
}