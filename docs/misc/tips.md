<!-- docs/misc/tips.md -->


## You have lost your admin account

If you have no admin user set, the only way to define a new one is to follow next instructions.

* Select what user currently able to get logged to Teampass can tempolarly have this role.
* Ensure this user is not logged into Teampass.
* Provide in database the `admin` role to this user
```
UPDATE `<TABLE PREFIX>_users` SET admin = 1 WHERE login = '<USER LOGIN>';
```
* Ask the user to get logged in.
* Select the `Users` page.
* Created a new user with Administrator privileges.
* Get unlogged.
* Remove the `admin` role to the previous user
```
UPDATE `<TABLE PREFIX>_users` SET admin = 0 WHERE login = '<USER LOGIN>';
```
* Done, you now have a new user.
