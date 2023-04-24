<!-- docs/features/roles.md -->


> 🚧 Under construction

## Generalities

Roles permits to define canvas of folders access rights.

## Mapping AD groups with Teampass roles

A feature permits to map an AD Group to a Teampass Role.

### Quick explanation

As an effect, any AD user belonging to an AD group mapped with a Teampass role will automatically inherit this role.
Example, User has 2 Teampass roles called "Role A" and "Role B".
If he belongs to AD Group "AD Group 1" that is mapped to "Role C" in Teampass then this user will have the 3 roles.

### How to set?

First ensure that next option is enbaled and GUID attribute set in page `Settings/LDAP`.

![1](../../_media/tp3_features_roles_1.png)

Also please ensure to have set correct group filter

![1](../../_media/tp3_features_roles_4.png)

Navigate to `Roles` and click the top button `LDAP synchronization`.

![1](../../_media/tp3_features_roles_2.png)

If settings are correct, a list of existing roles in your directory will be shown.

![1](../../_media/tp3_features_roles_6.png)

In the above screenshot, you can see that AD group `IT` is mapped with Teampass role `IT`.
Also AD group `domainUsers` is not mapped with any Teampass role.

To define a new mapping, click the role you want to define and select it in the list of Teampass roles, and click `Submit` button.

![1](../../_media/tp3_features_roles_5.png)

In the above screenshot, by submitting the change, AD group `domainUsers` will become mapped with Teampass role `Admin`.