888888888888                                         88888888ba
     88                                              88      "8b
     88                                              88      ,8P
     88   ,adPPYba,  ,adPPYYba,  88,dPYba,,adPYba,   88aaaaaa8P'  ,adPPYYba,  ,adPPYba,  ,adPPYba,
     88  a8P_____88  ""     `Y8  88P'   "88"    "8a  88""""""'    ""     `Y8  I8[    ""  I8[    ""
     88  8PP"""""""  ,adPPPPP88  88      88      88  88           ,adPPPPP88   `"Y8ba,    `"Y8ba,
     88  "8b,   ,aa  88,    ,88  88      88      88  88           88,    ,88  aa    ]8I  aa    ]8I
     88   `"Ybbd8"'  `"8bbdP"Y8  88      88      88  88           `"8bbdP"Y8  `"YbbdP"'  `"YbbdP"'

|===================================================================================================|
|						TeamPass - A Collaborative Passwords Manager								|
|								2011 (c) Nils Laumaillé												|
|===================================================================================================|

*****************************************************************************************************
***** 	  																						*****
***** 	  								LICENCE AGREEMENT										*****
***** 	  Before installing and using TeamPass, you must accept its licence defined	as			*****
*****	  GNU AFFERO GPL.																		*****
*****	  Copyright (c) 2011, Nils Laumaillé (Nils@TeamPass.net)								*****
*****																							*****
*****     This program is free software: you can redistribute it and/or modify					*****
*****     it under the terms of the GNU Affero General Public License as						*****
*****     published by the Free Software Foundation, either version 3 of the					*****
*****     License, or any later version.														*****
*****																							*****
*****     This program is distributed in the hope that it will be useful,						*****
*****     but WITHOUT ANY WARRANTY; without even the implied warranty of						*****
*****     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the							*****
*****     GNU Affero General Public License for more details.									*****
*****																							*****
*****     You should have received a copy of the GNU Affero General Public License				*****
*****     along with this program.  If not, see <http://www.gnu.org/licenses/>.					*****
*****																							*****
*****************************************************************************************************

----------------------------------------  INFORMATIONS  ---------------------------------------------
Website: http://www.teampass.net/

BUGS & SUGGESTIONS:
*For bugs discovery or any suggestions, please report in Google Code Issues (http://code.google.com/p/cpassman/issues/list).

INSTALLATION:
* http://www.teampass.net/tag/installation/
* Once uploaded, launch install/install.php and follow instructions.

NEEDS:
* Apache, MySQL, PHP 5.3.0 (or higher) and mcrypt extension

UPDATE:
* Once uploaded, launch install/upgrade.php and follow instructions.

LANGUAGES:
* ENGLISH 	by Nils and Petr
* FRENCH 	by Nils
* CZECH 	by Petr and Philipp
* GERMAN 	by Philipp
* RUSSIAN 	by Anton (to be finished)
* TURKISH 	by Ahmet (to be finished)
* NORWEGIAN by Kai (to be finished)
* JAPANESE	by Shinji (to be finished)
* PORTUGUESE by Luiz LeFort

--------------------------------------------  TODO  -------------------------------------------------
* IMAP login
* translations
* PostgreSQL database support
* DB2 database support
* Issue 187:	After LDAP support is configured every user (except admin) must exist in ldap
* The folder structure is automatically expanded can there be a feature / option to disable that
* mail notification when selecting an item
* script that permit to change the SALT (see db_patch.php)
* import from "Password safe" tool
* settings page. manage settings via table and do a loop in order to display options.
* Suggestion: Password copy button in search results
* Would it be possible to add support for syslog? It would be a nice feature to be able to log all activity to a third syslog server. This would be mainly for security and auditing.
* Do your think your can support yubikey ?
* Issue 231:	How to Restrict Admin from Viewing items
* Issue 232:	Restricted User modifications not reported on the item log
* Issue 237:	IE8 Password Entry List Improperly Formatted

------------------------------------------  CHANGELOG  ----------------------------------------------
2.1.2
* improved upgrade connection errors and automatic credentials import
* Corrections: #4, #7, 236

v2.1.1
* 2 bugs correction

v2.1
* Licence has changed to GNU AFFERO GPL 3.0
* 203 - password complexity on Roles
* 121 - Default language can be set + user language stored in DB
* Encrypt old passwords in LOG_ITEMS table
* started CRON activity for emails sending
* new option: send email to Admins when users get connected
* "Restricted to" field not viewable to everyone
* add an icon for hide/show passwords in clear text (toggle button)