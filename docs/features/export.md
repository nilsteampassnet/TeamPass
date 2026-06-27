<!-- docs/features/export.md -->


## Generalities

Teampass permits to export items. Currently, items can be exported to `CSV`, `PDF` and `HTML` files.
This last type is also called `Offline mode`.

## Export option

Exporting items is not allowed by default, it requires to be allowed.

While logged in as an administrator,

* select `Options` in left menu
* write `export` in top right search bar
* set your own set up

![1](../../_media/tp3_export_1.png)

> 💡 Teampass allows you to select the Groups allowed to use this feature.


## Usage

As a user, 

* select the option `Export` from the left menu
* select the folders from which you want to export the items
* select the type of export
* enter a password which is requested for `PDF` and `HTML` export
* enter a file name
* click `Perform` button

![1](../../_media/tp3_export_2.png)


## Offline mode (HTML export)

The `HTML` format produces a single, self-contained file holding every item of the selected
folders the user can access — label, login, password, URL, email, description, tags, folder path
and the item's custom fields (custom fields the user's roles are not allowed to see are omitted).

### How it is protected

* The whole dataset is **encrypted with the export password** using `AES-256-GCM`, with the key
  derived through `PBKDF2-SHA256` (250 000 iterations). The encryption is done server-side; the
  file decrypts natively in the browser through the WebCrypto API — **no cryptographic library is
  embedded** in the file.
* The raw file leaks nothing: it only contains the salt, the initialisation vector and the
  authenticated ciphertext. Without the password, no item data can be read.
* The export password is **never stored** in the file and is never written to disk on the server.
* Because the encryption is authenticated, a wrong password or any tampering with the file is
  detected and rejected cleanly (no garbage is shown).

> 💡 The minimum complexity of the export password is enforced by the administrator setting
> **Offline mode key complexity**.

### Opening the file

The file works fully offline (it can be opened directly from disk, with no connection to the
TeamPass server, in any recent Chrome, Firefox or Edge):

1. Open the `.html` file in a browser.
2. Enter the **export password**.
3. Choose the **auto-lock duration** (1, 5, 15, 30 or 60 minutes — default **5 minutes**).
4. Click `Unlock`: the item table is displayed.

In the table, passwords are hidden by default; each row has a button to reveal/hide its password
and a button to copy it. A search box filters the items, and a `Hide all passwords` button
re-masks every revealed password at once.

### Auto-lock

While the content is displayed, a **countdown** shows the remaining time before the file locks
again. When it reaches zero the page reloads automatically: every decrypted value is wiped from
memory and the password + duration prompt is shown again.

An `Extend` button resets the countdown to the duration initially chosen, so consultation can be
prolonged without re-entering the password.

> 💡 Offline mode must be enabled by the administrator
> (setting **Enable offline mode**). See [Settings](../manage/settings.md).

