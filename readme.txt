|==============================================================================|
|				TeamPass - A Collaborative Passwords Manager				   |
|						2009-2013 (c) Nils Laumaillé						   |
|==============================================================================|

********************************************************************************
* 																		 	   *
* 								LICENCE AGREEMENT							   *
* Before installing and using TeamPass, you must accept its licence defined	as *
* GNU AFFERO GPL.															   *
* Copyright (c) 2009-2013, Nils Laumaillé (Nils@TeamPass.net)				   *
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
* ENGLISH 		by Nils and Petr
* FRENCH 		by Nils
* CZECH 		by Petr and Philipp
* GERMAN 		by Philipp
* RUSSIAN 		by Anton
* TURKISH 		by Ahmet
* NORWEGIAN 	by Kai
* JAPANESE		by Shinji
* PORTUGUESE 	by Luiz LeFort
* ITALIAN		by Federico

--------------------------------  CHANGELOG  -----------------------------------
2.1.19
 #442 - problem edit folder
 #399 - Export encrypted passwords (off-line mode)
 #408 - Personal Salt Key changing doesn't work
 #419 - Password complexity not refreshed
 #418 - English translation improvement
 #407 - "Restricted to" feature improvement
 #402 - In item list, description is cut with <br />
 #393 - Password input and confirmation field location
 #388 - Unable to move items between folders
 #400 - Extra fields for Item
 #414 - Maintenance mode during upgrade can be disabled
 #389 - Language dropdown not working
 #392 - Check of absolute path for SK.PHP
 #385 - Email not sent ... check your configuration (to be checked)
 #379 - CSV importing not working (to be checked)
 #134 - Login After Session Expires
 #429 - Changed user.psk field to allow NULLs
 #428 : error: iconv(): Detected an illegal character in input string
 #426/#430 : New option to disable information loading in Admin page
 #142 - Google Authenticator implemented
 * Dialogbox not closed when changing folder name
 * Display Item details through Find page error

2.1.18
 #315 - jstree style.css badly referenced
 #314 - Folder is not being deleted
 #320 - Enabling LDAP prevents local admin login
 #317 - server expected extensions are tested
 #318 - Upgrade process badly creates sk.php file
 #348 - Fix for undefined index "isAdministratedByRole"
 #350 - Fix for Lock and delete user actions don't refresh page
 #354 - Fix for removing folders
 #359 - Fix for initial user password change complexity check
 #371 - Fix for uploaded files corrupted
 #291 - Fix to support openLDAP / posix style LDAP
 #361 - Option to use login password as SALT key
 * Fix - no possibility to update a Role
 * Fix - editing users by clicking on the fields broken
 * Fix - parse error in database errors log
 * New - requested user password complexity shown when changing password
 * New - option for deactivate client-server encryption (usage of SSL)
 * New - in tree, new counters added (subfolders and items in subfolders numbers)
 * New language added - Catalan

2.1.17
 * New exchange encryption protocol. No key is visible. The channel is
 encrypted at start of session.
 * HTTPS connection can be activated (be carefull, you need a certificate)
 * Change Users passwords encryption
 * Corrected - once clicked on not authorized Item, any Item selection was
 no more possible.
 #283 - Rights on a folder created at root are set.
 #285 - New settings: Anyone can modify option can be activated by default
 #287 - newly created personal folders ar propergated to the group
 #289 - Personal folder name badly constructed
 #270 - Restricted items visible in Find results
 #298 - Protection against bad actions on personal folders
 #299 - User can be explicetly administrated by Managers of specific Roles
 #300 - Personal SK is encrypted in COOKIE
 #301 - Corrected query call error
 #302 - Under "Views" users can see items that exist in personal folders
 that have been accessed
 #307 - fclose() statement badly placed

2.1.16
 * #245 - #248 - #249 - #265 - #266 - #267 - #268 - #273
 * #277: Change personal saltkey error

2.1.15
 * list of bugs corrected: #242 - #254 - #244 - #247 - #256 - #250 - #254 - #248
   #243 - #252 - #232 - #240 - #260 - #259 - #262 - #251 - #236
 * MySQL hashing => todo
 * CSV importation

2.1.14
 * list of bugs corrected: #238 - #235 - #239 - #203 - #201 - #233 - #226 - #236
   #228 - #189 - #234 - #225 - #239 - #194 - #86
 * Corrected bug for sending emails
 * Different small corrections

2.1.13
 * Code improvement for PSR compliance
 * jQueryUI updated to v1.9
 * Cleanup unused files
 * #207: Managers can only see the Roles they are allowed to.
 * #190, #192, #199, #202, #196, #204, #191, #214 corrected
 * Correction: taking into account user "can create at root level" setting
 * Added: saltkey is exported in a unique file that should be moved outside
   "www" server scope.
 * Added: 2-factors authentication
 * Added: new check when Role creation
 * Added: new check for database query error
 * Added: Item in edition will lock any other edition
 * Added: New administrator View permitting to view "Users actually connected"
   and "Tokens taken for Items in edition"
 * Added: User account contains now Name and Last Name fields

2.1.12
 * #188
 * #185 Started adjusting codebase to follow PSR 1 and PSR 2 based on ecaron
 		work (thank you)

2.1.11
 * #184 - bug correction

2.1.10
 * #161 - #100 - #175
 * #163 Personal saltkey duration based on cookie (under option)
 * share item -> manage error when email not sent
 * Improved/corrected export CSV and PDF
 * Correction: During upgrade, languages table is wrong
 * Personal Saltkey is stored in cookie (new admin setting)
 * Emails settings are moved to admin settings page (no more in settings.php)
 * Files folder is now a setting (to improve security)
 * Exported PDF is encrypted (contributor: Jay2k1)
 * #168 Add description field in PDF
 * #174 User creation and modification log

2.1.9
 * #126-#132-#130-#131-#139-#129-#141-#146
 * Italian translation
 * Find page - focus in search box (contributor: Jay2k1)

2.1.8
 * SF 206
 * #107-#95-#102-#103-#67-#32-#87-#71-#125-#120-#116-#111-#108-#104-#90-
   #85-#78-#48-#34-#67-#75-#82-#84
 * bug correction cache table
 * view Item details from the Find page
 * CSV export  -> started
 * mail notification when selecting an item
 * share Item by mail
 * add email field in Item form
 * automatic deletion of Item after X opening or after limit date
 * Roles / Folders matrix: Roles passwords complexity shown

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