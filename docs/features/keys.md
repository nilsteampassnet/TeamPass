<!-- docs/features/keys.md -->

> 🚧 Under construction

## Generalities

In Teampass, all encrypted elements (such as passwords and encrypted fields) have a unique key for each user. 
This key is encrypted with his/hers login password.
Such a process ensures a high level of security for all data stored in the database through Teampass.

💡 [Read more](../install/encryption.md) about this encryption process.

## Regenerate your keys (as a User)

For any reason, if you notice that while browsing Teampass's objects, all related passwords are empty then it might be a corruption of your private key is corrupted.
Could be after several login password changes.

For regenerated all your keys, just follow the next instructions.

1. Select entry `Generate new keys` in personal menu
   ![1](../_media/tp3_keys_1.png)

2. Ensure that the form contains your login password
   ![1](../_media/tp3_keys_2.png)

3. Click `Confirm` button

4. Once started, the process will run in background during several minutes. You can still use Teampass but all the passwords will be blank.
On top of screen, an orange box will show you the process progress. Once finished, you will have your passwords back.
   ![1](../_media/tp3_keys_3.png)

> 💡 During this process, you can change page and even leave Teampass.