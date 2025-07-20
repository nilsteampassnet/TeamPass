<!-- docs/features/import.md -->


## Generalities

Teampass permits to import items. Currently, items can be imported from `CSV` and `XML (Keepass 2)` files.

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