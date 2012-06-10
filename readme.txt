|==============================================================================|
|				TeamPass - A Collaborative Passwords Manager				   |
|						2012 (c) Nils Laumaillé							   |
|==============================================================================|

********************************************************************************
* 																		 	   *
* 								LICENCE AGREEMENT							   *
* Before installing and using TeamPass, you must accept its licence defined	as *
* GNU AFFERO GPL.															   *
* Copyright (c) 2012, Nils Laumaillé (Nils@TeamPass.net)					   *
* 																			   *
* This program is free software: you can redistribute it and/or modify		   *
* it under the terms of the GNU Affero General Public License as			   *
* published by the Free Software Foundation, either version 3 of the		   *
* License, or any later version.											   *
* 																			   *
* This program is distributed in the hope that it will be useful,			   *
* but WITHOUT ANY WARRANTY; without even the implied warranty of			   *
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the				   *
* GNU Affero General Public License for more details.						   *
* 																			   *
* You should have received a copy of the GNU Affero General Public License	   *
* along with this program.  If not, see <http://www.gnu.org/licenses/>.		   *
* 																			   *
********************************************************************************

----------------------------  INFORMATIONS  ------------------------------------
Website: http://www.teampass.net/

BUGS & SUGGESTIONS:
  For bugs discovery or any suggestions, please report in Github
        https://github.com/nilsteampassnet/TeamPass/issues

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

-------------------------------  TODO  -----------------------------------------
* IMAP login
* translations
* PostgreSQL database support
* DB2 database support
* Issue 187:	After LDAP support is configured every user (except admin) must
exist in ldap
* The folder structure is automatically expanded can there be a feature /
option to disable that
* import from "Password safe" tool
* settings page. manage settings via table and do a loop in order to display
options.
* Would it be possible to add support for syslog? It would be a nice feature to
be able to log all activity to a third syslog server. This would be mainly for
security and auditing.
* Do your think your can support yubikey ?
* Issue 242:	Feature Request: SALT Key Sync with LDAP
* Tree view => cookie collapse or not
* Tree search => if big list then the view doesn't scroll down to the found
folder

32.48.207.
* RAJOUTER une versiond ans les fichier JS et CSS

--------------------------------  CHANGELOG  -----------------------------------
2.1.8
 * SF 206
 * bug correction cache table
 * view Item details from the Find page
 * CSV export  -> started
 * mail notification when selecting an item -> started
 * share Item by mail  -> started

2.1.7
 * SF 247 - 248 - 261 - 264 - 265 - 266 - 267
 * 67:	protect uploadify library => different file protection added
 * protect Downloadfile.php
 * SF228: reset personal saltkey (purge personal items)
 * SF262: copy of item is in log
 * old password in log was badly encoded
 * item copy from search page corrected
 * some rights checks added before action
 * email send to new created user

2.1.6
 * #59: settings.php email setting errors
 * #67: Protected upload file
 * added email notification for user requiering an access to a restricted item.
 * 264:	Feature Request: Password History

2.1.5
* #56: Temporary solution for keeping old ADMIN profile rights

2.1.4
* Corrections: SF237, SF240, SF243 , #29, #25,  #32 , #36 , #37 , #39 , #40
	SF257, SF259, SF239, #41, #40, #51
* Improvements:
	SF232
	SF231:	How to Restrict Admin from Viewing items
	#31: new setting option for dynamic list
	#27: new subfolders only associated to the same roles as the parent folder
	#33: folder management in items page
	Changing SALT key from admin pages

2.1.3
* upgrade improvement in case of upgrading from 1.x version.

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