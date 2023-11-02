CSRFProtector configuration
==========================================

 - `CSRFP_TOKEN`: name of the csrf nonce, used for cookie or posting as argument. default: `CSRFP-Token` (if left blank)
 - `failedAuthAction`: Action code (integer) for action to be taken in case of failed validation. Has two different values for bot `GET` and `POST`. Different action codes are specified as follows, (<br>**Default:** `0` for both `GET` & `POST`):
    *  `0` Send **403, Forbidden** Header
    *  `1` **Strip the POST/GET query** and forward the request! unset($_POST)
    *  `2` **Redirect to custom error page** mentioned in `errorRedirectionPage` 
    *  `3` **Show custom error message** to user, mentioned in `customErrorMessage` 
    *  `4` Send **500, Internal Server Error** header

 - `errorRedirectionPage`: **Absolute url** of the file to which user should be redirected. <br>**Default: null**
 - `customErrorMessage`: **Error Message** to be shown to user. Only this text will be shown!<br>**Default: null**
 - `jsUrl`: **Absolute url** of the js file or `FALSE` if the js file will be added to the page manually. (See [Setting up](https://github.com/mebjas/CSRF-Protector-PHP/wiki/Setting-up-CSRF-Protector-PHP-in-your-web-application) for more information)
 - `tokenLength`: length of csrfp token, Default `10`
 - `cookieConfig`: Array of parameter values for set cookie method.  supports three properties: `path`, `domain`, `secure` and `expire`. They have same meaning as respective parameters of `setcookie` method: [learn more - php.net]
 - `disabledJavascriptMessage`: messaged to be shown if js is disabled (string)
 - `verifyGetFor`: regex rules for those urls for which csrfp validation should be enabled for `GET` requests also. (View [verifyGetFor rules](https://github.com/mebjas/CSRF-Protector-PHP/wiki/verifyGetFor-rules) for more information)
