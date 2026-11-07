<!-- docs/manage/email-troubleshooting.md -->

# Troubleshooting email delivery

TeamPass sends notifications through its configured SMTP, Microsoft Graph or
Gmail transport. Provider acceptance does not confirm inbox delivery. See
[email transports](email-transports.md) for provider setup, migration and limits.

## Test the saved configuration

In **Configuration → Emails**, use **Send a test email**. The test uses the saved
configuration and the administrator's own email address. It has a shorter SMTP
timeout than a background notification.

When a debug level is selected, TeamPass shows connection stages, SMTP command
names and numeric response codes. Command arguments, credentials, message bodies
and free-form server replies are deliberately excluded, even at the highest debug
level. The captured output is bounded. Do not re-enable raw PHPMailer transcripts
to diagnose production messages: they can contain passwords and recovery links.

An error reports a standard PHPMailer reason and, when available, validated SMTP
response codes. For example, an authentication failure can include `535 5.7.8`.
Use the relay's documentation or its protected administration tools to investigate
that code. Never post passwords, message contents or recovery links in an issue.

## Background notifications

Notifications queued in `background_tasks` are marked failed for permanent or
ambiguous failures. Confirmed transient failures may be deferred for up to three
attempts, respecting `Retry-After`. A message containing a temporary password is also refused if that
credential cannot be decrypted. Ordinary notifications do not attempt to decrypt
an empty credential.

Task diagnostics contain identifiers, classified reasons and scheduling metadata,
without dumping arguments. Use the administrator's test to diagnose the saved
configuration. New queued payloads are encrypted with the instance key; historical
arguments can remain plaintext, so database access must remain restricted.

After an interrupted or partially accepted SMTP transaction, or an ambiguous API
response, check the provider before sending again: some recipients may already
have received the message. These outcomes never trigger an automatic retry.

The **waiting emails** control on the Emails page still handles the historical
`emails` table. Its count does not include the newer `background_tasks` queue.

## Compatibility

Existing SMTP authentication, host, port, encryption and certificate verification
settings are preserved. The multi-transport schema requires the normal upgrade;
existing SMTP installations do not need OAuth reconfiguration.
Multiple deliveries through the same service instance keep independent recipient
lists, including after a failed send.
