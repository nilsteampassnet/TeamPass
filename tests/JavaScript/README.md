# JavaScript regression tests

Run from the repository root with Node.js 22 or newer; no npm dependencies are needed:

```sh
node --test tests/JavaScript/*.test.cjs
```

`folder-special-options.test.cjs` executes the folder creation form's parent-change
handler and submission mapping. It covers all parent option combinations, changing
parents, returning to root, and explicit overrides of inherited defaults. Before
release, check the same flows in the browser and verify that the newly inserted
folder row and its edit sidebar display the values actually saved by the server.

## Renewal previews

`renewal-preview.test.cjs` runs the shipped shared notice renderer and request handling
with controlled DOM and HTTP adapters. It covers folder changes with out-of-order
responses, creation estimates, expired and unknown dates, safe label rendering,
individual policies with folder expiration disabled, item form reset/toggle behavior,
copy inheritance and cancellation/failure of move previews.

The same suite checks safe renewal badge rendering and clearing the previous badge
when another item opens. `renewal-page.test.cjs` executes the page initialization,
date filtering and clear action with controlled DataTables and datepicker adapters.

In a configured browser, also check the folder banner (including empty folders),
creation, editing, copying, drag-and-drop moves and bulk moves from Search. Moving
an old password into a shorter-period folder must show its existing age and warn
when it would already be expired. These tests do not exercise a live database or
the browser's native confirmation dialog.

## Login submission

The suite executes the login template's JavaScript functions and event handlers.
Form controls, HTTP responses, navigation and timers are simulated so failures and
overlapping actions can be tested deterministically. PHP substitutions use inert
values. The tests do not contact LDAP, an OAuth2 provider or a Duo service.

`Missing login source section` means the harness could not locate a JavaScript
declaration or event registration in `app/core/login.js.php`. If that code moved
or was renamed, update the corresponding anchors in the harness. Comment wording
is not used as an anchor. The lightweight PHP substitution assumes no PHP string
literal contains `?>`; it is not a PHP parser.

Translation escaping is tested with real PHP rendering by
`tests/Unit/LoginJavascriptTranslationTest.php`, in the PHPUnit suite.

Before release, also verify these flows in a configured browser environment:

- Local and LDAP login: rapid Enter/click submissions send one `identify_user`
  request; a refusal restores the form, and success keeps it locked until navigation.
- Network failure: an error is visible and the form can be submitted again.
- MFA: Google enrollment and verification, YubiKey input, and changing MFA methods
  between attempts retain focus and remain usable. Method selectors are visibly
  inactive and ignore clicks while an attempt is pending.
- Duo and OAuth2: provider redirects and callbacks complete; failures restore the form.
  Returning with the browser Back button reloads a cached, locked login page.
- Expired session: renewal replays the pending credentials once while the form stays
  locked; failed renewal leads to the existing refresh dialog.
- Returning to an idle tab: a pending session check completes before login is sent.
  Each session check or key-renewal request times out after 10 seconds. A timed-out
  check allows the login attempt to continue; a timed-out renewal opens the refresh
  dialog. This timeout does not apply to the authentication request itself.

This is a client-side duplicate-submission guard. Server-side authentication and
brute-force controls remain necessary.
