<!-- docs/api/api-basic.md -->

> :warning: **Warning:** API are still in development.

## Progress

- [x] Global API structure
- [x] Authentication
- [x] Items - list with criteria
- [x] Items - get item info
- [ ] Items - edit an item
- [x] Folders - create new
- [x] Items - create new


## Generalities

Teampass v3 comes with an API permitting several operations on items and users.\
Its usage relies on a JWT token generated on demand.
Queries via API are possible until this token is valid.\
API is by default disabled. 

> The usage of the API requires <mark>a valid account and a valid API key</mark>.

## Define _LimitRequestFieldsize_ directive in Apache settings

Before starting using Teampass API, it is requested to change the default value _LimitRequestFieldsize_ directive in Apache settings.
This directive defines the limit on the allowed size of an HTTP request-header field below the normal input buffer size compiled with the server.

> Set `LimitRequestFieldSize 200000` in _apache2.conf_ file.

## Setup API in Teampass

Once enabled, the default auth token is set for a duration of 60 seconds. You can adapt this value to your needs.

You need to create an API key.

> :bulb: **Tip:** Provide a label for each key so that you know in what context it is used.


## API usage

The base API url is: `<Teampass url>/api/index.php/<action criteria>`

### Authorize

> :memo: **Note:** Returns the JWT token requested for next API queries

| Info | Description |
| ---- | ----------- |
| Criteria | authorize |
| Type | POST |
| URL | `<Teampass url>/api/index.php/authorize` |
| BODY | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"apikey": "_generated api key in Teampass_",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "_teampass user login_",<br>&nbsp;&nbsp;&nbsp;&nbsp;"password": "_user password_"<br>} |
| Return | A token valid for a specific duration.<br>Return format is:<br>{<br>&nbsp;&nbsp;&nbsp;&nbsp;"token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."<br>} |

### List items in folders

> :memo: **Note:** Returns a list of items belonging to the provided folders (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| Criteria | item/inFolders |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/inFolders?folders=[590,12]` |
| PARAMETERS | folders=[<folder_id>,<folder_id>] |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of items in json format.<br>Example:<br>[<br>&nbsp;&nbsp;{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 1027,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "Teampass production",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description": "Use for administration",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd": "Ajdh-652Syw-625sWW-Ca18",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "https://teampass.net",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "tpAdmin",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email": "nils@teampass.net",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no": 54,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon": null,<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso": 0<br>&nbsp;&nbsp;}<br>] |

### Get item data by ID

> :memo: **Note:** Returns the item definition based upon its ID (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| Criteria | item/get |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/get?id=2052` |
| PARAMETERS | id=<item_id> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of item attributes in json format.<br>Example:<br>[{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id":2053,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label":"new object for #3500 v3",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description":"<p>bla bla</p>",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd":"SK^dsf123s_6A}]V$t^]",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url":"",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login":"Me",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email":"",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no":2,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon":"",<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif":0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso":0<br>&nbsp;&nbsp;&nbsp;&nbsp;"id_tree": 670,<br>&nbsp;&nbsp;&nbsp;&nbsp;"folder_label": "MACHINES",<br>&nbsp;&nbsp;&nbsp;&nbsp;"path": "issue3317>issue 3325>ITI 2>PROD"<br>&nbsp;&nbsp;&nbsp;&nbsp;}<br>&nbsp;&nbsp;&nbsp;] |

### Get item data by LABEL

> :memo: **Note:** Returns an item list definition based upon its LABEL (taking into account the user access rights)

This query accepts an optional parameter called `like` that permits to perform a search on the field `label`.
If `like=1` then you can add in parameter `label` the symbol `%` to refine the search.

Example:
* `label="%some text"` will search for all labels finishing by `some text`.
* `label="%some text%"` will search for all labels containing `some text`.
* `label="some text%"` will search for all labels starting by `some text`.

| Info | Description |
| ---- | ----------- |
| Criteria | item/get |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/get?label="some text"&like=0` |
| PARAMETERS | label="some text"&like=<O or 1> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of item attributes in json format.<br>Example:<br>[{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 21,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "bug 1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd": "Voici un é1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no": 13,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"id_tree": 1,<br>&nbsp;&nbsp;&nbsp;&nbsp;"folder_label": "F1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"path": ""<br>&nbsp;&nbsp;&nbsp;&nbsp;},<br>&nbsp;&nbsp;&nbsp;{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 22,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "bug 1 - 1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd": "EwS5jc+S}Y6x",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no": 4,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"id_tree": 1,<br>&nbsp;&nbsp;&nbsp;&nbsp;"folder_label": "F1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"path": ""<br>&nbsp;&nbsp;&nbsp;&nbsp;}<br>&nbsp;&nbsp;&nbsp;] |

### Get item data by DESCRIPTION

> :memo: **Note:** Returns an item list definition based upon its DESCRIPTION (taking into account the user access rights)

| Info | Description |
| ---- | ----------- |
| Criteria | item/get |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/get?description="some text"&like=0` |
| PARAMETERS | description="some text"&like=<O or 1> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of item attributes in json format.<br>Example:<br>[{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 21,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "bug 1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd": "Voici un é1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no": 13,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"id_tree": 1,<br>&nbsp;&nbsp;&nbsp;&nbsp;"folder_label": "F1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"path": ""<br>&nbsp;&nbsp;&nbsp;&nbsp;},<br>&nbsp;&nbsp;&nbsp;{<br>&nbsp;&nbsp;&nbsp;&nbsp;"id": 22,<br>&nbsp;&nbsp;&nbsp;&nbsp;"label": "bug 1 - 1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"description": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"pwd": "EwS5jc+S}Y6x",<br>&nbsp;&nbsp;&nbsp;&nbsp;"url": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"email": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"viewed_no": 4,<br>&nbsp;&nbsp;&nbsp;&nbsp;"fa_icon": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"inactif": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"perso": 0,<br>&nbsp;&nbsp;&nbsp;&nbsp;"id_tree": 1,<br>&nbsp;&nbsp;&nbsp;&nbsp;"folder_label": "F1",<br>&nbsp;&nbsp;&nbsp;&nbsp;"path": ""<br>&nbsp;&nbsp;&nbsp;&nbsp;}<br>&nbsp;&nbsp;&nbsp;] |

### Add a new folder

> :memo: **Note:** Creates a new folder based upon provided parameters

Warning:
* `parent_id` must be valid.
* `complexity` must be one of the values: 0 (Weak) ; 20 (Medium) ; 38 (Strong) ; 48 (Heavy) ; 60 (Very heavy).
* `access_rights` must be one of the values: R (Read) ; W (Write) ; ND (No deletion) ; NE (No edit) ; NDNE (No deletion and No edit).

| Info | Description |
| ---- | ----------- |
| Criteria | folder/create |
| Type | POST |
| URL | `<Teampass url>/api/index.php/folder/create?title=Folder created from API 1&parent_id=934&complexity=0&duration&create_auth_without&edit_auth_without&icon=fa-cubes&icon_selected&access_rights=NDNE` |
| PARAMETERS | 'title'=is a string<br>'parent_id'=is the parent folder id<br>'complexity'=<0, 20 38, 48, 60><br>'duration'=is the expiration delay in minutes<br>'create_auth_without'=item can be created even if password strengh not enougth<br>'edit_auth_without'=item can be updated even if password strengh not enougth<br>'icon'=fontawesome icon code<br>'icon_selected'=fontawesome icon code on folder selection<br>'access_rights'=<R, W, ND, NE, NDNE> |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of item attributes in json format.<br>Example:<br>[{<br>&nbsp;&nbsp;&nbsp;&nbsp;"error": false,<br>&nbsp;&nbsp;&nbsp;&nbsp;"message": "",<br>&nbsp;&nbsp;&nbsp;&nbsp;"newId": "148"<br>&nbsp;&nbsp;&nbsp;&nbsp;}<br>&nbsp;&nbsp;&nbsp;] |


### Add a new item

> :memo: **Note:** Creates a new item based upon provided parameters

Warning:
* All fields are mandaotry

| Info | Description |
| ---- | ----------- |
| Criteria | item/create |
| Type | POST |
| URL | `<Teampass url>/api/index.php/item/create?label=item created from API 6&folder_id=934&password=$LjPRGBAJa8x8!qqGKc$@pvYtY5NY^k*GES3FHLeW%2o%23&description=Ceci est une déscription simple.&login=monLogin&email=mon@email.fr&url=https://teampass.readthedocs.io/en/latest/api/api-write/&tags=api,test&anyone_can_modify=0&icon=fa-solid fa-start text-orange` |
| PARAMETERS | 'label'=is a string<br>'folder_id'=is the parent folder id<br>'password'=is a string<br>'description'=is a string<br>'login'=is a string<br>'email'=is a string<br>'url'=is a string<br>'icon'=fontawesome icon code<br>'anyone_can_modify'=is a boolean|
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of item attributes in json format.<br>Example:<br>[{<br>&nbsp;&nbsp;&nbsp;&nbsp;"error": false,<br>&nbsp;&nbsp;&nbsp;&nbsp;"message": "Item created successfully",<br>&nbsp;&nbsp;&nbsp;&nbsp;"newId": "658"<br>&nbsp;&nbsp;&nbsp;&nbsp;}<br>&nbsp;&nbsp;&nbsp;] |