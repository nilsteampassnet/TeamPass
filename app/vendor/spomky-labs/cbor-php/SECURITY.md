# Security Release Process

CBOR PHP is devoted in providing the best experience for all developers who want to encode and decode CBOR data structures in their applications.
Spomky-Labs has adopted this security disclosure and response policy to ensure we responsibly handle critical issues.

## Supported Versions

The CBOR PHP project maintains release branches for the three most recent minor releases.
Applicable fixes, including security fixes, may be backported to those three release branches, depending on severity and feasibility. Please refer to [RELEASES.md](RELEASES.md) for details.

## Reporting a Vulnerability - Private Disclosure Process

Security is of the highest importance and all security vulnerabilities or suspected security vulnerabilities should be reported to CBOR PHP privately, to minimize attacks against current users of CBOR PHP before they are fixed.
Vulnerabilities will be investigated and patched on the next patch (or minor) release as soon as possible.
This information could be kept entirely internal to the project.

If you know of a publicly disclosed security vulnerability for CBOR PHP, please **IMMEDIATELY** contact security@spomky-labs.com to inform the CBOR PHP Security Team.

**IMPORTANT: Do not file public issues on GitHub for security vulnerabilities**

To report a vulnerability or a security-related issue, please use the [private vulnerability reporting form](https://github.com/Spomky-Labs/cbor-php/security/advisories/new), or email the private address security@spomky-labs.com with the details of the vulnerability.
The report will be fielded by the CBOR PHP Security Team, which is made up of the CBOR PHP maintainers who have committer and release permissions.
Do not report non-security-impacting bugs through this channel. Use [GitHub issues](https://github.com/Spomky-Labs/cbor-php/issues/new/choose) instead.

Emails can be encrypted if you wish to share the vulnerability details securely.
The CBOR PHP Security Team's PGP key is available on the [PGP keyservers](https://keys.openpgp.org/search?q=security%40spomky-labs.com).

### Proposed Email Content

Provide a descriptive subject line and in the body of the email include the following information:

-   Basic identity information, such as your name and your affiliation or company.
-   Detailed steps to reproduce the vulnerability (POC scripts, screenshots, and CBOR test vectors are all helpful to us).
-   Description of the effects of the vulnerability on CBOR PHP and the related hardware and software configurations, so that the CBOR PHP Security Team can reproduce it.
-   How the vulnerability affects CBOR PHP usage and an estimation of the attack surface, if there is one.
-   List other projects or dependencies that were used in conjunction with CBOR PHP to produce the vulnerability.

## When to report a vulnerability

-   When you think CBOR PHP has a potential security vulnerability.
-   When you suspect a potential vulnerability, but you are unsure that it impacts CBOR PHP.
-   When you know of or suspect a potential vulnerability on another project that is used by CBOR PHP. For example CBOR PHP has a dependency on `brick/math` and on the `mbstring`, `gmp` and `bcmath` PHP extensions.

## Patch, Release, and Disclosure

The CBOR PHP Security Team will respond to vulnerability reports as follows:

1.  The Security Team will investigate the vulnerability and determine its effects and criticality.
2.  If the issue is not deemed to be a vulnerability, the Security Team will follow up with a detailed reason for rejection.
3.  The Security Team will initiate a conversation with the reporter as soon as possible.
4.  If a vulnerability is acknowledged and the timeline for a fix is determined, the Security Team will work on a plan to communicate with the appropriate community, including identifying mitigating steps that affected users can take to protect themselves until the fix is rolled out.
5.  The Security Team will work on fixing the vulnerability and perform internal testing before preparing to roll out the fix.
6.  A public disclosure date is negotiated by the CBOR PHP Security Team and the bug submitter. We prefer to fully disclose the bug as soon as possible once a user mitigation or patch is available. It is reasonable to delay disclosure when the bug or the fix is not yet fully understood, the solution is not well-tested, or for distributor coordination. The timeframe for disclosure is from immediate (especially if it’s already publicly known) to a few weeks. For a critical vulnerability with a straightforward mitigation, we expect report date to public disclosure date to be on the order of 14 business days. The CBOR PHP Security Team holds the final say when setting a public disclosure date.
7.  Once the fix is confirmed, the Security Team will patch the vulnerability in the next patch or minor release, and backport a patch release into all earlier supported releases. Upon release of the patched version of CBOR PHP, we will follow the **Public Disclosure Process**.

### Public Disclosure Process

The Security Team publishes a public [advisory](https://github.com/Spomky-Labs/cbor-php/security/advisories) to the CBOR PHP community via GitHub. In most cases, additional communication via blog and other channels will assist in educating CBOR PHP users and rolling out the patched release to affected users.

The Security Team will also publish any mitigating steps users can take until the fix can be applied to their CBOR PHP installations. CBOR PHP distributors will handle creating and publishing their own security advisories.

## Mailing lists

-   Use security@spomky-labs.com to report security concerns to the CBOR PHP Security Team, who uses the list to privately discuss security issues and fixes prior to disclosure.

## Early Disclosure to CBOR PHP Distributors List

This private list is intended to be used primarily to provide actionable information to multiple distributor projects at once. This list is not intended to inform individuals about security issues.

## Confidentiality, integrity and availability

We consider vulnerabilities leading to the compromise of data confidentiality, elevation of privilege, or integrity to be our highest priority concerns.
Availability, in particular in areas relating to DoS and resource exhaustion, is also a serious security concern.
The CBOR PHP Security Team takes all vulnerabilities, potential vulnerabilities, and suspected vulnerabilities seriously and will investigate them in an urgent and expeditious manner.
