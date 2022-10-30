<!-- docs/start.md -->




## Installation

> To be written ...



## Upgrading

> You want to upgrade your current Teampass v3 to latest version

### Prerequisites

* Perform a database backup
* Save the main folder

### Steps

* Download latest release from [Teampass](https://github.com/nilsteampassnet/TeamPass/releases/latest)
* Unzip and overwrite existing files in Teampass folder
* Browse to `Teampass` upgrade page by selecting url `https://<your_teampass_instance>/install/upgrade.php`


## Upgrading from 2.x branch

> You want to upgrade to Teampass v3 branch

### Prerequisites

* Your current Teampass instance is 2.1.27.36.
* Perform a database backup
* Save the main folder

### Steps

* Rename current Teampass folder (it will be called `folderv2`)
* Download latest release from [Teampass](https://github.com/nilsteampassnet/TeamPass/releases/latest)
* Unzip and rename folder with the same name as for v2 (it will be called `folderv3`)
* Copy next files from `folderv2` to `folderv3`
```
./includes/config/settings.php
./includes/config/tp.config.php
./includes/librairies/csrfp/libs/csrfp.config.php
./includes/avatars/*
./files/*
./upload/*
```
* Ensure that folders and files have correct rights. Next elements need to be writable:
  ```
./includes/config/
./includes/librairies/csrfp/libs/
./includes/librairies/csrfp/js/
./includes/librairies/csrfp/log/
./includes/avatars/
./files/
./upload/
./install/
  ```
* Browse to `Teampass`