---
paths:
  - "app/includes/libraries/teampassclasses/emailservice/**"
  - "app/vendor/teampassclasses/emailservice/**"
  - "app/scripts/traits/EmailTrait.php"
---

# Email transport architecture

`EmailService` remains the entry point used by immediate callers and background
notifications. It resolves the transport, applies the existing sanitization and
`emailBody()` wrapper, then calls PHPMailer's `preSend()` exactly once. A transport
receives that prepared PHPMailer message. It must not render templates, rebuild
the body, or interpret business permissions.

`SmtpTransport` uses `postSend()` to preserve the existing SMTP/TLS implementation.
Missing `email_transport` means `smtp`; an unknown selection fails explicitly.
There is no automatic fallback to SMTP after another transport fails.

`EmailSendResult` separates provider acceptance from confirmed failure and an
indeterminate outcome. Its error codes are a closed set. It carries no provider
response body or decrypted content. Acceptance is not inbox delivery. A timeout
after submission or partial recipient acceptance must never trigger a blind replay.
The transport reports retry eligibility and delay; it does not schedule retries.

For compatibility, `sendMail()` still returns an empty string on acceptance and
JSON with `error`, `errorInfo` and `message` on failure. Failure JSON also carries
the structured result metadata. New callers can use `getLastResult()` instead of
parsing translated messages. Error translations use the configured default
language and its English fallback; sending does not depend on a user session.

All package classes have synchronized source and vendor copies. Extend
`EmailServiceCopiesInSyncTest` when adding a class. Test adapters through the
transport interface; regression tests also exercise real PHPMailer MIME preparation
and the SMTP boundary without installation credentials.

OAuth state is stored separately in `email_oauth_state`, encrypted with the existing
instance-key helpers before SQL writes. A configuration revision invalidates stale
readers. Renewal leases use the database clock, a random owner and conditional
updates; no transaction remains open during network I/O. Late workers cannot save
or release a replacement owner's state. Access-token invalidation matches the
observed token hash so a late 401 cannot discard a newer token. Never put token
values in `misc`, task arguments, logs or browser responses.

`EmailTokenManager` uses separate cache keys and scopes for Microsoft Graph and
Exchange SMTP client credentials. Gmail uses delegated consent with PKCE and the
single `gmail.send` scope, retaining refresh tokens when renewal omits them.
Token requests only reach fixed HTTPS endpoints, verify TLS, refuse redirects,
bound response bodies and discard provider error bodies. A renewal lease conflict
defers the caller rather than racing a refresh. Acquiring a token does not prove
that the configured mailbox can send mail.

Guzzle and League OAuth2 Client were already shipped transitively; they are now
declared directly without changing locked versions or the production autoloader.

`ApiTransport` sends prepared MIME to Graph (standard base64, HTTP 202) or Gmail
(base64url, HTTP 200 with a message ID). It targets the configured mailbox, requires
the From/envelope sender to match it, and never follows a redirect. One explicit
401 permits renewing the observed token and retrying once. A 429 can be deferred;
timeouts, 5xx and malformed acceptance responses are indeterminate and cannot be
automatically replayed. The v1 application limit is 2 MiB of MIME; Bcc is explicitly
refused because SMTP-prepared MIME omits its envelope. Existing SMTP retains Bcc.
To, Cc, Reply-To, multipart bodies, headers and attachments stay in the MIME.

Microsoft SMTP OAuth uses only `smtp.office365.com:587`, STARTTLS and verified TLS.
It cannot fall back to Basic credentials. Each service send creates a new PHPMailer
to prevent auth providers, headers or attachments surviving a configuration change.
