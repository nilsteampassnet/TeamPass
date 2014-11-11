2.1.22
 #700 - Errors related to "includes/js/jstree/themes/default"
 #718 - Two factor authentication: "This user has no email set!"
 #674 - API - User rights
 #697 - Default language setting, not being applied to automatically created ldap users.
 #698 - Default language setting, not being applied to newly created users.
 #707 - httpRequest is missing in upgrade process
 #725 - Disable button after item creation or edition 
 #720 - cannot sign up to 2factor 
 #690 - limit password export via PDF/CSV to user/group
 #745 - Enable again save_button after error on Add/Edit Item
 #739 - OTV correction
 #731 - Export password to file
 #653 - Passwords preprended during upgrade
 #767 - Backup restore feature fix
 #774 - Call to undefined method DB::queryInsert
 Other: #711 - #699 - #726 - #744 - #684 - #737
 New - Rights "Read / Write / No Access" added to folders for better rights management
 New - quick copy to clipboard for password and login
 New - New option : Prevent against duplicate items in same folder
 New - If folder is read-only for the User then it is striked-through
 Changed - list of restricted users refined by folder selected
 Fix - Not possible to see more than 8 Roles in Roles matrix

2.1.21
 #597 - Rapid click on save button on "Add a folder"
 #599 - SQL:AUTO_INCREMENT id --> language
 #600 - preg_replace(): Unknown modifier '|'
 #598 - Extra fields in home page
 #602 - can't change user password by very heavy complexity
 #603 - password complexity check only in javascript
 #415 - Items are not show when in folder view. Can easy search and open.
 #578 - API generate new key
 #580 - Redirect to login page when accessing directly an item (if not logged)
 #576 - Mismatch email_body tags
 #607 - HMTL export erroneous download link
 #622 - Tooltip on left menu buttons
 #619 - CSV Import does not import passwords
 #617 - CSV Import doesn't handle passwords with quotes well
 #627 - Complete authentication bypass
 #626 - API vulnerable (improvement in progress)
 #633 - favicon correction
 #636 - MySQL on non-standard port
 #632 - Refactor order of index.php
 #629 - A password for admin account is required during installation
 #654 - Tab character breaks json format
 #652 - one-time view not working when interface is in French
 #658 - Rapid Click on Item Copy
 #657 - Rapid Click on Password Creation
 #656 - Can't Create Folder as User
 #643 - email charset in UTF-8
 #641 - Add and save item -> double click on that icon won't work
 #671 - When password is generated, it is added in confirm field too
 #672 - Changing password makes account inaccessible
 #637 - Multi Domain LDAP
 #673 - Changed strategy for quick icon clipboard copy
 #639 - Design fix in admin page
 #681 - Fix for Folder and Users creation as Administrator
 #680 - Set custom expiry for one time view link
 #682 - Fix SMTP authentication which were used regardless of the settings
      - Fix a query used in the "lost password" management.
      - Fix the mysql error message when the session_expired page is accessed…
 - New option permitting to send or not an email to User when admin changes his password
 - Fix for image viewer when option files encryption is set
 - Fix for password complexity level update

2.1.20
 #492 - Default admin password not working
 #509 - Password complexity
 #493 - Unable to purge logs
 #503 - manual insertions in Items History log not working
 #494 - Logs > Administration JSON error
 #491 - Applying email address to user
 #441 - Attachments encryption
 #459 - Turn off strict mode
 #477-#452 - Fix for upgrade
 #459 - Turn off strict mode
 #472 - Error on line 582 index.php
 #474 - Set default to checked for secure passwords
 #497 - Moved GA QR code creation to administration
 #487 - Off-line mode, link make the page scroll up
 #533 #521 #528 - Installation issue
 #525 - Settings.php should not be commited
 #527 - Potential security bug
 #485 - CVS Import on V 2.1.19 quotes problems
 #544 - DataTables warning: JSON data from server could not be parsed
 #547 - User search
 #520 - API access
 #549 #550 - Server Time in footer
 #539 - New feature: Simplify Items Tree
 #547 - Search in Users page
 #401 - Folder role inheretance on new folder
 #552 - added MBstring check
 #554 - Search-Page "Jump to item"-Button not working correctly
 Fork from slimm609 - Encrypted Sessions and CSRFGuard enabled
 Issues with folder creation in "personal folder"
 #536 - one time view page for anonymous user
 #517 - New feature: Suggest items system
 New feature: Sub-folder inherits of parent folder

2.1.19
 #413 - fix for PHP Parse error: syntax error, unexpected '['
 #447 - fix for PHP Fatal error: Cannot redeclare getBits()
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