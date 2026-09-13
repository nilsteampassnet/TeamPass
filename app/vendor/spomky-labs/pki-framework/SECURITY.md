# Security Policy

## Supported Versions

Only the latest stable release line receives security fixes. If you run an older one, upgrade before reporting: a fix
will not be backported to it.

| Version | Supported          |
|---------|--------------------|
| 1.6.x   | :white_check_mark: |
| < 1.6   | :x:                |

## Reporting a Vulnerability

**Do not open a public issue, pull request or discussion for a security problem.** Doing so hands a working exploit to
every user of the library before a fix exists.

Report it privately, through either channel:

- [GitHub private vulnerability reporting](https://github.com/Spomky-Labs/pki-framework/security/advisories/new) —
    preferred; it opens a private thread with the maintainers, attached to a draft advisory;
- email `security AT spomky-labs.com`, if you would rather not use GitHub.

A report is easiest to act on when it contains:

- the version of the library you tested against, and the PHP version and extensions in use (`gmp`, `bcmath`,
    `openssl`);
- what an attacker gains — a forged certificate accepted as valid, path validation bypassed, a crash, unbounded memory
    or CPU use, …;
- a minimal reproducer: the DER or PEM material involved (base64 is fine) and the few lines of code that mishandle it;
- the fix you would suggest, if you have one in mind.

## What happens next

1. The report is acknowledged and a draft [GitHub Security Advisory][advisories] is opened.
2. The fix is written in the private fork attached to that advisory, so it stays out of the public repository until it
    ships.
3. A patch release is cut on the supported line, the advisory is published, a CVE is requested, and you are credited
    unless you ask otherwise.

## Scope

The library parses, builds and validates X.509 and ASN.1 structures. It never opens a network connection: nothing is
fetched from an AIA, CRL distribution point or OCSP responder, and no trust store is read from the system. Everything
the library trusts is handed to it by the calling application.

Two consequences of that are by design rather than vulnerabilities:

- **`PathValidator` performs no revocation checking.** `CertificationPath::validate()` says the chain is well-formed
    and leads to a trust anchor you chose — it does not say the certificate is still valid. Checking CRLs or OCSP is
    the caller's responsibility.
- **The trust anchor is an input, never a discovery.** Validating a peer-supplied chain without naming the anchor you
    trust is a bug in the calling code; see the [README](README.md#validating-a-certification-path).

Reports about the code shipped in `src/` are in scope. Findings that only affect the test suite, the CI workflows or
the development dependencies are welcome as ordinary issues.

[advisories]: https://github.com/Spomky-Labs/pki-framework/security/advisories
