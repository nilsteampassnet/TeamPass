# Changelog

### 4.1.19 (2019-11-11)

- keep more non XSS content from html input


### 4.1.18 (2019-11-11)

- fix open tags problem e.g. "<img/"


### 4.1.17 (2019-11-08)

- add "addNeverAllowedRegex()"
- add "removeNeverAllowedRegex()"


### 4.1.16 (2019-11-03)

- fix replacing of "-->" (issue #50)
- update vendor lib (Portable UTF-8)


### 4.1.15 (2019-09-26)

- optimize regex
- update vendor lib (Portable UTF-8)


### 4.1.14 (2019-06-27)

- add "removeNeverAllowedOnEventsAfterwards()" && "addNeverAllowedOnEventsAfterwards()"
- update "_never_allowed_on_events_afterwards" -> add "onTouchend" + "onTouchLeave" + "onTouchMove" (thx @DmytroChymyrys)
- optimize phpdoc for array => string[]


### 4.1.13 (2019-06-08)

- fix replacing of false-positive xss words e.g. "<script@gmail.com>" (issue #44)


### 4.1.12 (2019-05-31)

- fix replacing of false-positive xss words e.g. "<video@gmail.com>" (issue #44)


### 4.1.11 (2019-05-28)

- fix replacing of false-positive xss words e.g. "<styler_tester@gmail.com>" (issue #44)


### 4.1.10 (2019-04-19)

- fix replacing of false-positive xss words e.g. "ANAMNESI E VAL!DEFINITE BREVI ORTO" (issue #43)


### 4.1.9 (2019-04-19)

- optimize the spacing regex


### 4.1.8 (2019-04-19)

- fix replacing of false-positive xss words e.g. "MONDRAGÓN" (issue #43)


### 4.1.7 (2019-04-19)

- fix replacing of false-positive xss words e.g. "DE VAL HERNANDEZ" (issue #43)


### 4.1.6 (2019-04-12)

- fix replacing of false-positive xss words e.g. "Mondragon" (issue #43)


### 4.1.5 (2019-02-13)

- fix issue with "()" in some html attributes (issue #41)


### 4.1.4 (2019-01-22)

- use new version of "Portable UTF8"


### 4.1.3 (2018-10-28)

- fix for url-decoded stored-xss
- fix return type (?string -> string)


### 4.1.2 (2018-09-13)

- use new version of "Portable UTF8"
- add some more event listener
- use PHPStan


### 4.1.1 (2018-04-26)

- "UTF7 repack corrected" | thx @alechner #34


### 4.1.0 (2018-04-17)

- keep the input value (+ encoding), if no xss was detected #32


### 4.0.3 (2018-04-12)

- fix "href is getting stripped" #30


### 4.0.2 (2018-02-14)

- fix "URL escaping bug" #29


### 4.0.1 (2018-01-07)

- fix usage of "Portable UTF8"


### 4.0.0 (2017-12-23)
- update "Portable UTF8" from v4 -> v5

  -> this is a breaking change without API-changes - but the requirement
     from "Portable UTF8" has been changed (it no longer requires all polyfills from Symfony)


### 3.1.0 (2017-11-21)
- add "_evil_html_tags" -> so you can remove / add html-tags


### 3.0.1 (2017-11-19)
- "php": ">=7.0"
  * use "strict_types"
- simplify a regex


### 3.0.0 (2017-11-19)
- "php": ">=7.0"
  * drop support for PHP < 7.0
