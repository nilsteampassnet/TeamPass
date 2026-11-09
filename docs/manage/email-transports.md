<!-- docs/manage/email-transports.md -->

# Email transports

TeamPass has one active email configuration. Notifications, recovery messages,
backup reports and the administrator's test use `EmailService`. PHPMailer builds
the HTML/text MIME message once; the selected transport submits it. Email template
customization continues to work independently of the transport.

## Installation and upgrade

Use the normal TeamPass installer or upgrade procedure before starting workers
with these files. The schema marker is advanced, including for installations
already reporting 3.2.4.1 or 3.2.4.2. The existing `upgrade_run_3.2.4.php` step is replayable;
fresh installation creates the same schema. The migration adds:

- `email_transport=smtp`, an empty SMTP auth-mode override, and provider settings;
- a random configuration revision;
- `email_oauth_state`, separate from general settings;
- `email_attempts` and `next_attempt_at` on background tasks, plus a due-task index.

Existing relay, authentication, sender, TLS and certificate settings remain in
place. An absent transport still means SMTP; an empty auth-mode override derives
the previous authentication setting. No OAuth configuration is required to keep
using a legacy SMTP relay. Unknown transport/authentication selections fail.

Back up the database, application configuration and instance key using the normal
upgrade process. Preserve the instance key: it protects credentials, tokens and
new queued messages. Do not create a new key to repair an email problem.

For rollback, restore compatible code and its database backup together. Older
workers cannot interpret the new encrypted email envelopes. Drain or preserve
those tasks before any downgrade; do not silently run old workers against them.

## Save and test

Open **Configuration → Emails** as an administrator. Select a transport and fill
the fields shown. **From** must match the configured provider mailbox for Graph,
Gmail and Microsoft SMTP OAuth. Save the complete profile, then send a test to the
email address saved in your administrator profile. Unsaved changes block testing.

For each secret, choose **Keep**, **Replace** or **Clear**. An empty replacement
is refused. The browser never receives the stored value. Typing a replacement
automatically selects Replace. Inactive provider settings can be retained for a
later switch; explicitly clear credentials you no longer need.

A save publishes the profile atomically and rotates its revision. Stale browser
tabs and late token renewals cannot overwrite it. Reload after another
administrator changes the profile. Changing provider or credentials invalidates
the previous cached authorization. There is no fallback to another transport.

The send test submits an actual message. A successful token request or a recorded
Google authorization alone does not prove mailbox send permission. Acceptance by
the relay/API does not prove inbox delivery: check the received message and the
provider's delivery tools. Use fictitious content for tests.

## SMTP relays

OAuth token requests (including SMTP XOAUTH2), Microsoft Graph and Gmail API
requests use the global `proxy_ip` / `proxy_port` settings when configured.
HTTPS certificate verification remains enabled through the proxy. SMTP relay
connections themselves still use a direct SMTP socket.

Choose **SMTP relay**, then no authentication or username/password. Keep the
existing relay host, port and encryption combination; internal DNS names and IP
addresses are supported. STARTTLS and implicit TLS remain separate choices.
Certificate verification retains the existing installation's setting. Configure
a trusted certificate and enable verification for authenticated TLS relays.

Debug output contains bounded protocol stages and numeric codes. Credentials,
message bodies and raw server responses are excluded. API requests have no raw
HTTP debug mode. See [email troubleshooting](email-troubleshooting.md).

## Microsoft Graph

Use a dedicated single-tenant Entra application with a client secret. Record the
tenant ID, application/client ID, secret expiry and sender mailbox. TeamPass uses
client credentials with `https://graph.microsoft.com/.default` and sends MIME to
`/users/{mailbox}/sendMail`; HTTP 202 records acceptance.
[Graph sendMail documentation](https://learn.microsoft.com/en-us/graph/api/user-sendmail?view=graph-rest-1.0).

Restrict the application to the required mailbox through Exchange Application
RBAC: create an Exchange service-principal pointer, create a mailbox resource
scope, and assign `Application Mail.Send` within that scope. Use the Enterprise
Application service principal's object ID, rather than the App Registration
object ID. Test with `Test-ServicePrincipalAuthorization` for both the intended
mailbox and an excluded mailbox.

Do not retain an unscoped Entra `Mail.Send` grant alongside a scoped RBAC grant:
the effective permissions combine, which can defeat mailbox scoping. Follow the
[Exchange Application RBAC guide](https://learn.microsoft.com/en-us/exchange/permissions-exo/application-rbac)
for tenant-side commands and propagation behavior.

In TeamPass select **Microsoft Graph** and save the IDs, secret and mailbox.
Send a test and verify reception. Token acquisition succeeds independently of
the mailbox authorization check.

## Microsoft 365 SMTP OAuth

Select **SMTP relay → Microsoft 365 OAuth2 (application)**. Supply the tenant ID,
client ID, secret and sender mailbox. TeamPass fixes the endpoint to
`smtp.office365.com:587` with STARTTLS and verified TLS. Existing Basic credentials
are not used; a failed OAuth send cannot fall back to them.

Configure the dedicated Entra application's Exchange Online application permission
`SMTP.SendAsApp` with the required tenant consent. Register its service principal
in Exchange, using the Enterprise Application object ID. Grant access only to the
sender mailbox, including the mailbox and SendAs grants required by Microsoft's
client-credentials procedure. Check the tenant and mailbox SMTP AUTH policies;
do not enable it broadly just to make the test pass. The token audience is
`https://outlook.office365.com/.default`, distinct from Graph.

Follow Microsoft's [SMTP OAuth setup and mailbox permission instructions](https://learn.microsoft.com/en-us/exchange/client-developer/legacy-protocols/how-to-authenticate-an-imap-pop-smtp-application-by-using-oauth).
Graph `Mail.Send` does not substitute for the SMTP setup. Test the actual send
after permissions have propagated and monitor the application secret's expiry.

## Gmail API / Google Workspace

Create a Google Cloud project, enable Gmail API and configure its OAuth consent
screen. Create a **Web application** OAuth client. Register the exact authorized
redirect URI displayed by TeamPass, including any subdirectory. The canonical
TeamPass URL must use HTTPS; loopback HTTP is allowed for local development.
Supply the client ID, secret and sender mailbox, with matching From.

Choose **Authorize Google and activate Gmail**, sign in to the configured sender
account and grant consent. TeamPass requests only
`https://www.googleapis.com/auth/gmail.send`, offline access and PKCE. It targets
the configured account in Gmail's send endpoint, rather than silently choosing a
different consenting mailbox. See [Google web-server OAuth](https://developers.google.com/identity/protocols/oauth2/web-server)
and [Gmail scopes](https://developers.google.com/workspace/gmail/api/auth/scopes).

Consent must finish in the same administrator session within ten minutes. The
previous transport stays active until code exchange and persistence succeed.
Expired state, a changed configuration, denied consent or a different administrator
cannot activate the candidate. Saving Gmail changes requires fresh consent.
After return, send a real test. The recorded-authorization indicator is not a
live permission or delivery check.

Refresh tokens permit unattended renewal without a logged-in TeamPass user.
Revocation, Google policies and client-secret changes can require new consent.
An external consent screen left in **Testing** normally produces a seven-day
refresh token for this scope. Select the appropriate production/internal app
configuration and satisfy Google's verification requirements before relying on
long-running notifications. See [Google token expiration](https://developers.google.com/identity/protocols/oauth2#expiration).

This version implements delegated Gmail API access. It does not implement Google
SMTP OAuth, service accounts or domain-wide delegation. Google SMTP's broader
mail scope is a separate integration; [Google documents that distinction](https://developers.google.com/workspace/gmail/imap/xoauth2-protocol).

## Message support and limits

| Feature | SMTP | Graph / Gmail API |
| --- | --- | --- |
| HTML, alternative text, UTF-8 | Preserved | Same prepared MIME |
| Multiple To, Cc, Reply-To | Preserved | Preserved |
| Bcc | SMTP envelope | Explicitly refused in this version |
| Attachments, inline images, headers | PHPMailer MIME | Preserved within the MIME limit |
| Sender aliases / separate envelope sender | Existing SMTP behavior | From/envelope sender must match the configured mailbox |
| Message size | Relay limits | TeamPass v1 limit: 2 MiB of prepared MIME |

The common `sendMail()` caller interface still takes its historical recipient
list. The transport boundary preserves richer prepared messages where callers
supply them. It never silently drops Bcc or an oversized MIME body. Inline-image
delivery and provider-specific header transformations still require real-provider
verification; protocol mocks do not prove client rendering.

Only the global Microsoft cloud and standard Google endpoints are implemented.
Native Brevo, SendGrid, Mailgun and SES API adapters, certificate-based Microsoft
credentials, multiple active profiles and automatic failover are outside this
version. Configured transactional SMTP relays use the existing SMTP transport.

## Queue, renewal and failures

Tokens live encrypted in `email_oauth_state`; application secrets use the existing
encrypted settings helpers. Token audiences and configuration revisions are
isolated. An expiring database lease serializes renewal; network requests hold
no database transaction open. A late 401 cannot invalidate a replacement token.

New background email arguments contain a versioned encrypted envelope. Historical
tasks remain readable until drained. Tokens and provider credentials never enter
task arguments. Completed/failed task retention follows existing TeamPass policy;
restrict database and backup access because historical arguments can be plaintext.
When the worker records completion or terminal failure, only the outcome and
`email`/`login` remain, so **Logs → Tasks** can identify the recipient without
retaining the subject, body, encrypted credential or envelope.

Confirmed transient failures can defer the same task for at most three attempts.
The minimum backoff is 30 then 60 seconds; a longer `Retry-After` is preserved
up to one hour. A delay beyond one hour fails the task instead of parking it
indefinitely or retrying earlier than the provider permits.
The existing cron/handler schedule must keep running. There is no sleeping worker
or new queue infrastructure. Permanent authentication, permission, configuration
and message errors require administrator action.

An API 401 permits one immediate token renewal and resubmission after that explicit
rejection. API timeouts, 5xx, malformed acceptance replies, partial SMTP acceptance
and interrupted workers are ambiguous: automatic replay is refused. Check the
provider before manually resending. Exactly-once delivery across a worker crash
cannot be guaranteed by SMTP or these non-idempotent send APIs.

The Emails page distinguishes background tasks from the historical `emails`
backlog. Its legacy send button acts only on that old table. Task diagnostics
store classified reasons and scheduling metadata, without raw provider bodies,
tokens, recipient lists or message content.

## Validation before production

Automated tests use synthetic credentials: HTTP provider mocks, real PHPMailer
preparation, a loopback SMTP simulator, real instance-key encryption, worker/handler
boundaries and disposable MariaDB migrations/transactions. The SMTP simulator
does not validate Exchange's public TLS endpoint or real mailbox grants.

On a test installation, validate each enabled provider with the following checks:

1. Upgrade an existing SMTP installation and verify its test and a queued
   notification. Replay the upgrade and confirm credentials are unchanged.
2. Test a freshly installed instance and a notification generated without a
   signed-in user. Check received HTML/text and any required attachments/images.
3. For Microsoft, verify an allowed mailbox sends and an excluded mailbox cannot
   use the application. Test expired credentials and permission removal.
4. For Gmail, test denied/expired/replayed consent, a different consenting account,
   authorization revocation and renewal after the first access token expires.
5. Generate representative standard-user and administrator notifications,
   recovery/security messages and a backup report using fictitious data. Check
   templates, permissions and audit behavior in each originating workflow.
6. Simulate a confirmed throttle and verify the task waits. Interrupt a send and
   confirm it is failed without an automatic replay. Inspect logs for leakage.
7. Review the Emails screen in French/English, a narrow viewport and dark mode.
   Confirm unsaved changes cannot be tested and saved secrets never reappear.

Real Microsoft/Google accounts, receipt, token expiry and visual administration
checks remain separate acceptance tests; a passing mock suite cannot validate them.
