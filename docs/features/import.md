<!-- docs/features/import.md -->


## Generalities

Teampass permits to import items. Items can be imported from:

* `CSV` files (TeamPass generic format),
* `XML (Keepass 2)` files,
* and directly from the export of another password manager: **Bitwarden**, **LastPass**, **1Password** and **KeePassXC** (see [Importing from another password manager](#importing-from-another-password-manager)).

## Importing using CSV file

### Enable `Import`

Importing items is not allowed by default, it requires to be allowed.

While logged in as an administrator,

* select `Options` in left menu
* write `import` in top right search bar
* set your own set up

![1](../../_media/tp3_export_1.png)

### CSV structure

In order to be imported, the CSV requires to be build following a specific format.

* The first line must be a header,
* The header must contain 5 or 6 columns (with or without folder),
* The separator character is a comma `,`,
* The encalupsation character is a double quotes `"`,
* Expected columns are: `Label` , `Login` , `Password` , `URL` , `Comments` , `Folder` (is optionnal).

#### Example with folder

```
label,login,password,url,comments,folder
"My nice item","MyLogin","MyPassword","http://www.mydomain.com","This is an example 1","Folder #1"
"My nice subitem","My1Login","My1Password","http://www.mydomain.com","This is an example 1.1","Folder #1/Sub Folder #1"
"My nice item 2","My2Login","My2Password","http://www.mydomain2.com","This is an example 2","Folder #2"
"My nice item 3","My3Login","My3Password","http://www.mydomain3.com","This is an example 3","Folder #3"
```

#### Example without folder

```
label,login,password,url,comments
"My nice item","MyLogin","MyPassword","http://www.mydomain.com","This is an example 1"
"My nice subitem","My1Login","My1Password","http://www.mydomain.com","This is an example 1.1"
"My nice item 2","My2Login","My2Password","http://www.mydomain2.com","This is an example 2"
"My nice item 3","My3Login","My3Password","http://www.mydomain3.com","This is an example 3"
```

### Implemented rules

* Folders will only be imported if user has any `manager` role.
* If not, the items will be imported directly in the destination folder


## Importing using Keepass2 XML file

> 🚧 Under construction


## Importing from another password manager

TeamPass can import directly from the export files produced by the most common
password managers, so migrating in does not require building a TeamPass CSV by
hand.

Supported sources:

| Source | Export file | How to produce it |
| --- | --- | --- |
| **Bitwarden** | `.json` (unencrypted) | Tools → *Export vault* → file format `json`. Do **not** tick "Password protected". |
| **LastPass** | `.csv` | Advanced Options → *Export* → save the CSV file. |
| **1Password** | `.csv` | Desktop app → File → *Export* → CSV. |
| **KeePassXC** | `.csv` | Database → *Export* → *Export to CSV*. |

### How to import

1. Open `Import` from the left menu (the feature must be enabled by an administrator,
   see [Enable Import](#enable-import)).
2. In the **source** selector, pick the password manager you are migrating from
   (Bitwarden, LastPass, 1Password or KeePassXC). The page shows the exact export
   steps for that source.
3. Upload the export file.
4. Choose the options (target folder, keys generation strategy, access rights, …) and
   click `Perform`.

### What is imported

For every entry, TeamPass maps:

* the **title / name** → item label,
* the **username / login** → item login,
* the **password** → item password,
* the **URL** → item URL,
* the **notes** → item description,
* the **folder / group / grouping** → folder path (created on import).

Folder handling follows the same rule as the CSV import: **folders are created only when
the user has a `Manager` role**; otherwise items are imported flat into the selected
target folder.

Source-specific notes:

* **Bitwarden** – folders are rebuilt from the `folders` section of the export. Data
  carried by non-login entries (TOTP secret, card or identity fields) is appended to the
  item description so nothing is lost. Encrypted exports are rejected — export without
  encryption.
* **LastPass** – nested folders use the `\` separator (`Folder\Subfolder`). Secure notes
  (sentinel URL `http://sn`) are imported without a URL.
* **1Password** – the CSV export has no folder column; when present, the **first tag** is
  used as the destination folder, otherwise items are imported flat.
* **KeePassXC** – the group path uses the `/` separator. A leading `Root/` segment is
  stripped to avoid a redundant top-level folder.

### Import follow-up

Every import is tracked. The **Import follow-up** panel at the bottom of the Import page
lists your recent imports with, for each one:

* the **source** (CSV, Keepass, Bitwarden, …),
* the **status** (Analyzing, Ready, Creating folders, Importing items, Completed, Failed),
* the number of **items** imported versus the total detected,
* the number of **failed** items,
* the number of **folders** detected,
* the **date** of the import.

The panel refreshes automatically when an import completes, and can be refreshed manually
with the refresh button.