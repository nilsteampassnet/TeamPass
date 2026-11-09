<!-- docs/manage/email-troubleshooting.md -->

# Troubleshooting email delivery

TeamPass sends notifications through its configured SMTP relay. An accepted SMTP
transaction means the relay accepted the message; it does not confirm delivery to
the recipient's inbox.

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

Notifications queued in `background_tasks` are marked failed when the relay refuses
the send. A message containing a temporary password is also refused if that
credential cannot be decrypted. Ordinary notifications do not attempt to decrypt
an empty credential.

Task diagnostics contain task identifiers and types, without dumping their
arguments. Email failures distinguish `Email delivery failed.` from
`Email credential could not be decrypted.`. The latter points to the instance key
(`SECUREFILE`) or encrypted credential, before the relay is contacted. The test
email can succeed in that situation because it has no queued credential to decrypt.
Use the administrator's test to diagnose the current relay configuration. Unknown
exceptions are replaced with the generic delivery message. Completed and failed email tasks
retain only the recipient and login; message bodies and encrypted credentials are
removed when the attempt ends. Old failed tasks are purged after the configured
task-history retention period, including tasks without subtasks. Database access
must remain restricted because recipient metadata is still retained until purge.

These fixes do not add automatic retries. After an interrupted or partially
accepted SMTP transaction, check the relay before sending again: some recipients
may already have received the message.

The **waiting emails** control on the Emails page still handles the historical
`emails` table. Its count does not include the newer `background_tasks` queue.

## Compatibility

Existing SMTP authentication, host, port, encryption and certificate verification
settings are preserved. No database migration or reconfiguration is required.
Multiple deliveries through the same service instance keep independent recipient
lists, including after a failed send.
