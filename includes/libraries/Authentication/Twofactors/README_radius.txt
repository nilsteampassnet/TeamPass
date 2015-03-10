Radius 2Factor Authentication requirements
------------------------------------------

For this Class to work, you need to install the php pecl-radius 
(hint: pecl install Radius)

A working OTP solution and radius server is also needed.
( Tested with LinOTP + FreeRadius & Google Authenticator Mobile App) 

2 new fields in the teampass_misc table:

INSERT INTO `teampass_misc` (`type`, `intitule`) VALUES ('admin', 'radius_secret');
INSERT INTO `teampass_misc` (`type`, `intitule`) VALUES ('admin', 'radius_servers');

After this, you should be able to add the radius server(s) and secret as teampass admin.

On the admin pages , the 'Enable Google 2-Factor Authentication' toggle has been replaced with a dropdown menu
Choices are : 
	None  = 0 
	Google = 1
	Radius = 2

When selecting one of the options, only relevant input fields are shown.

When Radius 2 factor auth is selected, the login window shows , just like the google authenticator functionality,
a token input field. 


Possible good candidates for a todo list:

- Better input validation? and error handling
- Another option to allow passtru when none of the radius servers are reachable 
- At the moment, Also the admin user needs to authenticate via OTP.
  This was a deliberate choice , but maybe it also should be made optional.
