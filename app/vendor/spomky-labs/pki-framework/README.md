# Public Key Infrastructure

[![CI](https://github.com/Spomky-Labs/pki-framework/actions/workflows/integrate.yml/badge.svg)](https://github.com/Spomky-Labs/pki-framework/actions/workflows/integrate.yml)

[![Latest Stable Version](https://poser.pugx.org/spomky-labs/pki-framework/v/stable.png)](https://packagist.org/packages/spomky-labs/pki-framework)
[![Total Downloads](https://poser.pugx.org/spomky-labs/pki-framework/downloads.png)](https://packagist.org/packages/spomky-labs/pki-framework)
[![Latest Unstable Version](https://poser.pugx.org/spomky-labs/pki-framework/v/unstable.png)](https://packagist.org/packages/spomky-labs/pki-framework)
[![License](https://poser.pugx.org/spomky-labs/pki-framework/license.png)](https://packagist.org/packages/spomky-labs/pki-framework)

> [!NOTE]
> This framework started as a fork of the libraries published at https://github.com/sop. It has diverged
> substantially since — the code has been reworked, extended and maintained to meet the Spomky-Labs requirements —
> and the two are no longer interchangeable. All credits for the original work go to its developer.

A PHP Framework

* X.509 public key certificates, attribute certificates,
* X.690 Abstract Syntax Notation One (ASN.1) Distinguished Encoding Rules (DER) encoding and decoding
* X.501 ASN.1 types, X.520 attributes and DN parsing.
* [RFC 7468](https://tools.ietf.org/html/rfc7468) textual encodings of cryptographic structures _(PEM)_.
* Various ASN.1 types for cryptographic applications.
* Cryptography support for various PKCS applications.

## Requirements

- PHP >=8.1
- `mbstring`

The extension `gmp` or `bcmath` is highly recommended

## Installation

This library is distributed on [Packagist](https://packagist.org/packages/spomky-labs/pki-framework); the source lives
on [GitHub](https://github.com/Spomky-Labs/pki-framework).

```sh
composer require spomky-labs/pki-framework
```

## Issuing certificates from a certification request

`TBSCertificate::fromCSR()` builds a certificate from a certification request. **Everything a certification request
contains is chosen by whoever submitted it**, so an issuer must treat it as untrusted input:

- the signature of the request is **not** verified by `fromCSR()`. Call `CertificationRequest::verify()` yourself
    before using it;
- extensions that decide what a certificate is allowed to do — `basicConstraints`, `keyUsage`, `extKeyUsage`,
    `nameConstraints`, `policyConstraints`, `policyMappings`, `inhibitAnyPolicy`, `certificatePolicies` and
    `authorityKeyIdentifier` — are never copied from the request. They belong to the issuer, which sets them with
    `withExtensions()` / `withAdditionalExtensions()`;
- **name the extensions you are willing to honour** as the second argument. Anything not named is dropped:

```php
$tbsCertificate = TBSCertificate::fromCSR($csr, [Extension::OID_SUBJECT_ALT_NAME]);
```

- leaving the argument out copies every requested extension that is not forbidden, `subjectAltName` and
    `authorityInformationAccess` included, and raises a deprecation notice. That default is a deny list: the set of
    extensions that matter grows over time and every future one is copied. It becomes the empty allow list in the
    next major release. Pass `null` explicitly if you really want it;
- an unknown extension marked critical is never copied. It would be signed verbatim and no conforming validator,
    this library's own included, would then accept the certificate.

## Validating a certification path

The trust anchor is an input to the validation process, never something read out of the material the peer sent. Start
from the certificates you trust and let the library build the path to the target:

```php
use SpomkyLabs\Pki\X509\Certificate\CertificateBundle;
use SpomkyLabs\Pki\X509\CertificationPath\CertificationPath;
use SpomkyLabs\Pki\X509\CertificationPath\Exception\PathValidationException;
use SpomkyLabs\Pki\X509\CertificationPath\PathValidation\PathValidationConfig;

$trustAnchors = CertificateBundle::create($rootA, $rootB); // your trust store
$intermediates = CertificateBundle::create(...$chainFromPeer); // untrusted, used only to bridge the path

$path = CertificationPath::toTarget($leafFromPeer, $trustAnchors, $intermediates);

try {
    $result = $path->validate(PathValidationConfig::defaultConfig());
} catch (PathValidationException $e) {
    // the chain does not lead to any certificate you trust
}
```

If you already hold the path, name the anchor explicitly:

```php
$config = PathValidationConfig::defaultConfig()->withTrustAnchor($root);
$path->validate($config);
```

Do not validate a chain a peer supplied without an anchor. Left to itself, validation would fall back to the first
certificate of the path — one the peer chose — and confirm only that the chain is internally consistent. A path built
by `CertificationPath::fromCertificateChain()` refuses to validate without an explicit anchor for that reason, and a
path built by `CertificationPath::create()` raises a deprecation notice when it falls back, so an application can find
the call sites before the fallback is removed in the next major release. A path built by `toTarget()` is already
headed by a certificate from the trust list you gave it and needs neither.

## Security

Path validation checks that a chain is well-formed and leads to a trust anchor you named. It does **not** check
revocation: the library never contacts a CRL distribution point or an OCSP responder, so a revoked certificate still
validates. Revocation is the calling application's responsibility.

Attribute certificate validation does not implement RFC 5755 section 5 check 4 on its own: name the authorities you
trust to issue attribute certificates with `ACValidationConfig::withTrustedAttributeAuthorities()`, or the validator
accepts whatever end-entity certificate the issuer path ends in.

Ed25519 and Ed448 are in the default set of allowed signature algorithms, but whether a signature made with them
can be checked depends on the runtime: the OpenSSL extension only grew EdDSA recently, and `ext-sodium` — bundled
since PHP 7.2 — covers Ed25519 alone. Verification fails closed where the runtime cannot do it. Ask
`OpenSSLCrypto::supportsSignatureAlgorithm()` if you would rather narrow the configured set than have a chain
refused during validation.

`Certificate::equals()` compares the two encodings octet by octet, and `CertificateBundle::contains()` is built on
it. Use `Certificate::hasEqualSubjectIdentity()` for the looser "same subject, same key, same serial number"
question — it is not an identity check, since two certificates can agree on all three and still be issued by
different issuers with different extensions.

Found a vulnerability? Do not open a public issue — read [SECURITY.md](SECURITY.md) and report it privately.

## License

This project is licensed under the MIT License.
