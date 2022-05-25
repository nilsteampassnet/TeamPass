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






