<!-- docs/install/extra-settings.md -->


## Mysql connection with ssl enabled

By default, ssl is not enabled but if you require it, you will need to perform next steps:

- Open in edition mode the file `./includes/config/settings.php`
- Find
```
define("DB_ENCODING", "utf8");
```
- Replace by
```
define("DB_ENCODING", "utf8");
define("DB_SSL", array(
    "key" => "",
    "cert" => "",
    "ca_cert" => "",
    "ca_path" => "",
    "cipher" => ""
));
define("DB_CONNECT_OPTIONS", array(
    MYSQLI_OPT_CONNECT_TIMEOUT => 10
));
```
- Fill in the 5 expected keys in variable `DB_SSL`. 
An example could be:
```
define("DB_SSL", array(
    "key" => "/mysql_keys/server-key.pem",
    "cert" => "/mysql_keys/server-cert.pem",
    "ca_cert" => "/mysql_keys/ca-cert.pem",
    "ca_path" => "",
    "cipher" => "DHE-RSA-AES256-SHA"
));
```
- Save the file