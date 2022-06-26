<!-- docs/api-basic.md -->

> :warning: **Warning:** API are still in development.

## Progress

- [x] Global API structure
- [x] Authentication
- [ ] Items - list with criteria
- [ ] Items - get item info
- [ ] Items - edit an item


## Generalities

Teampass v3 comes with an API permitting several operations on items and users.\
Its usage relies on a JWT token generated on demand.
Queries via API are possible until this token is valid.\
API is by default disabled. 

> The usage of the API requires <mark>a valid account and a valid API key</mark>.


## Setup API in Teampass

Once enabled, the default auth token is set for a duration of 60 seconds. You can adapt this value to your needs.

You need to create an API key.

> :bulb: **Tip:** Provide a label for each key so that you know in what context it is used.


## API usage

The base API url is: `<Teampass url>/api/index.php/<action criteria>`

### Authorize

| Info | Description |
| ---- | ----------- |
| Criteria | authorize |
| Type | POST |
| URL | `<Teampass url>/api/index.php/authorize` |
| BODY | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"apikey": "_generated api key in Teampass_",<br>&nbsp;&nbsp;&nbsp;&nbsp;"login": "_teampass user login_",<br>&nbsp;&nbsp;&nbsp;&nbsp;"password": "_user password_"<br>} |
| Return | A token valid for a specific duration.<br>Return format is:<br>{<br>&nbsp;&nbsp;&nbsp;&nbsp;"token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."<br>} |

### List items in folders

| Info | Description |
| ---- | ----------- |
| Criteria | item/inFolders |
| Type | GET |
| URL | `<Teampass url>/api/index.php/item/inFolders?folders={590,12}` |
| PARAMETERS | folders={<folder_id>,<folder_id>} |
| HEADER | {<br>&nbsp;&nbsp;&nbsp;&nbsp;"Authorization": "Bearer _token received from authorize step_"<br>} |
| Return | An array of items in json format.<br>Example:<br>[
    {
        "id": 1025,
        "label": "MONIT-01 - centreon",
        "description": "",
        "pw": "UbXyeypTdojiDMxvZtq7hw==",
        "pw_iv": "",
        "pw_len": 0,
        "url": "192.168.10.50",
        "id_tree": "590",
        "perso": 0,
        "login": "admin",
        "inactif": 0,
        "restricted_to": "",
        "anyone_can_modify": 0,
        "email": null,
        "notification": null,
        "viewed_no": 1,
        "complexity_level": "-1",
        "auto_update_pwd_frequency": 0,
        "auto_update_pwd_next_date": "0",
        "encryption_type": "teampass_aes",
        "fa_icon": null
    }
    ] |




