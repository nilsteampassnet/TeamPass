[![SWUbanner](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner-direct.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

# Teampass 3

Teampass is a Collaborative Passwords Manager solution installed On-Premise.

[![StandWithUkraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

![](https://img.shields.io/github/stars/nilsteampassnet/TeamPass?style=social)
![](https://img.shields.io/github/license/nilsteampassnet/teampass)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg?style=flat-square)](https://makeapullrequest.com)
![](https://img.shields.io/docker/pulls/teampass/teampass?label=Docker%20Pulls)

![](https://img.shields.io/github/v/release/nilsteampassnet/Teampass)
![](https://img.shields.io/github/commits-since/nilsteampassnet/teampass/latest)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/badges/build.png?b=master)](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/build-status/master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)

> Copyright © 2009-2025, [Nils Laumaillé](Nils@Teampass.net)

<!-- MDTOC maxdepth:2 firsth1:0 numbering:0 flatten:0 bullets:1 updateOnSave:1 -->

- [Requirements](#requirements)
  - [About PHP versions](#about-php-versions)
- [Installation](#installation)
  - [Docker (Recommended)](#docker-recommended)
  - [Traditional Installation](#traditional-installation)
- [Documentation](#documentation)
- [Languages](#languages)
- [Licence Agreement](#licence-agreement)
- [Website](#website)
- [Bugs](#bugs)

<!-- /MDTOC -->

## Requirements

* MySQL 5.7 or higher,
* MariaDB 10.7 or higher
* PHP 8.1 or newer,
* PHP extensions:
  * mcrypt
  * openssl
  * ldap (if used)
  * mbstring
  * bcmath
  * iconv
  * xml
  * gd
  * mysql
  * curl
  * gmp

### About PHP versions

Teampass should be installed using the most recent PHP version.
The branch `master` is the living one that is improved and comes with new features.
It requires __at least__ `PHP 8.1` installed on the server.

Nevertheless, Teampass can be used with PHP 7.4 version.
The Github Teampass project has a dedicated branch called `PHP_7.4` for this version.
Notice that only bug fixing will be performed on this branch.

## Installation

### Docker (Recommended)

The easiest way to run Teampass is using Docker. We provide official images on Docker Hub with production-ready configurations.

**Quick Start:**

```bash
# Download compose files
curl -O https://raw.githubusercontent.com/nilsteampassnet/TeamPass/master/docker/docker-compose/docker-compose.yml
curl -O https://raw.githubusercontent.com/nilsteampassnet/TeamPass/master/docker/docker-compose/.env.example

# Configure
cp .env.example .env
nano .env  # Set secure passwords

# Start Teampass
docker-compose up -d
```

**Available registries:**
- Docker Hub: `teampass/teampass`
- GitHub Container Registry: `ghcr.io/nilsteampassnet/teampass`

**📚 Complete Docker Documentation:**
- **[Docker Installation Guide](DOCKER.md)** - Complete guide with configuration options
- **[Migration Guide](DOCKER-MIGRATION.md)** - Upgrade from older Docker versions
- **[Docker Hub](https://hub.docker.com/r/teampass/teampass)** - Official images and tags

**Key Features:**
- ✅ Optimized Alpine-based image (PHP 8.3-FPM + Nginx)
- ✅ Automatic SSL support with Let's Encrypt
- ✅ Health checks and monitoring
- ✅ Optional automatic installation
- ✅ Persistent volumes for data safety

### Traditional Installation

For traditional server installations without Docker:

**Resources:**
- 📖 [Official Documentation](https://documentation.teampass.net)
- 📝 Website article: [TeamPass Installation Guide](https://www.valters.eu/teampass-a-self-hosted-password-manager-to-increase-organizations-cybersecurity/)
- 🎥 YouTube video: [Installation Tutorial](https://youtu.be/eXieWAIsGzc?feature=shared)

## Documentation

> ✍️ [Documentation](https://documentation.teampass.net) is available.

**Key documentation:**
- [Docker Installation](DOCKER.md) - Complete Docker deployment guide
- [Docker Migration](DOCKER-MIGRATION.md) - Upgrade from older versions
- [Official Documentation](https://documentation.teampass.net) - Full user and admin guides
- [API Documentation](https://documentation.teampass.net/api/) - REST API reference

## Languages

Teampass is currently available in 19 languages:
* CATALAN
* CHINESE
* CZECH
* DUTCH
* ENGLISH
* ESTONIAN
* FRENCH
* GERMAN
* HUNGARIAN
* ITALIAN
* JAPANESE
* NORWEGIAN
* PORTUGUESE
* PORTUGUESE (BR)
* ROMANIAN
* RUSSIAN
* SPANISH
* TURKISH
* UKRAINIAN
* VIETNAMESE

Languages strings are managed at [POEditor.com](https://poeditor.com/projects/view?id=433631).
Please participate to improving its translation by joining Teampass POEditor project.

## Licence Agreement

For detailed information on the licenses of our dependencies and our licence policy, please see [Detailed Licence Information](/licences/dependencies.licences.md).

## Website

Visit [Teampass.net](https://teampass.net/)

## Bugs

If you discover bugs, please report them in [Github Issues](https://github.com/nilsteampassnet/TeamPass/issues).

---

## Support & Community

- 💬 [Reddit Community](https://www.reddit.com/r/TeamPass/)
- 📧 [Email](mailto:nils@teampass.net)
- 🐛 [Issue Tracker](https://github.com/nilsteampassnet/TeamPass/issues)
- 📖 [Documentation](https://documentation.teampass.net)