# Security Policy

Please **do not report security vulnerabilities through public GitHub issues, pull requests or discussions.** A public report exposes every user of the library before a fix is available.

## How Doctrine approaches security

Doctrine operates very close to your database and, as such, needs to make assumptions about SQL injection. It is vital that you understand how Doctrine approaches security, because we cannot protect you from SQL injection. Please read the security chapter of the DBAL documentation to understand the assumptions we make:

- [DBAL Security page](https://www.doctrine-project.org/projects/doctrine-dbal/en/stable/reference/security.html)
- [security.rst in this repository](docs/en/reference/security.rst)

## Reporting a vulnerability

**Preferred: GitHub private vulnerability reporting**

1. Open the [Security tab](https://github.com/doctrine/dbal/security) of this repository.
2. Click **Report a vulnerability**, or go straight to the [report form](https://github.com/doctrine/dbal/security/advisories/new).
3. Describe the problem and submit.

Your report is visible only to the maintainers. It lets us discuss the issue with you, prepare a fix in private and publish a security advisory once patched releases are available.

**Fallback: email**

Only if you cannot use GitHub, send your report to security@doctrine-project.org. Emails sent to this address are forwarded to the Doctrine core team's private mailing list. See also the [Doctrine security reporting guidelines](https://www.doctrine-project.org/policies/security.html#reporting).

Whichever way you report, the following details help us a lot:

- the affected version(s) of DBAL, and of PHP, the database platform and version, and the driver (for example PDO or a native extension) if relevant
- a description of the problem and its impact
- a minimal way to reproduce it
- a suggested fix, if you have one

## What is a security issue?

A security issue is a flaw in DBAL itself that lets an attacker compromise the confidentiality, integrity or availability of an application, **even though that application uses DBAL as documented**, including the assumptions described in the security documentation above. Examples:

- **Injection:** user-controlled input is interpreted as SQL where DBAL treats it as a plain value or identifier, for example through parameter binding, quoting or escaping, the query builder, or platform-specific handling of identifiers and literals.
- **Silently weakened protection:** a bug that causes a security-relevant connection option, such as TLS settings, to be dropped or ignored, so that a connection is less protected than configured.
- **Unsafe instantiation or code execution:** DBAL instantiates arbitrary classes or executes attacker-controlled code based on untrusted input, such as connection parameters or data from the database.
- **Unintended data access:** DBAL reads, returns or writes rows the application did not ask for, for example because a parameter is bound to the wrong placeholder, a type conversion changes the meaning of a condition, or a condition is dropped.
- **Denial of service:** small, crafted input causes disproportionate resource consumption inside DBAL.

The following are usually **not** security issues:

- Ordinary bugs with no security consequence. Please use the [issue tracker](https://github.com/doctrine/dbal/issues) for these.
- An application concatenating unvalidated input into SQL passed to an API that is documented to accept raw SQL, such as `executeQuery()` with a string-built statement. This is the SQL injection responsibility described in the security documentation.
- Vulnerabilities in the database server, the database driver or PHP extension, or other dependencies. Please report those to the respective projects.
- Insecure database deployment, such as missing authentication or an exposed port.

**If you are unsure, report privately.** We would much rather triage a report that turns out to be a regular bug than have a vulnerability discussed in public. If we conclude that it is not a vulnerability, we will tell you, and you are then welcome to open a public issue.

## Reported publicly by mistake?

If you have already opened a public issue or pull request for something that may be a vulnerability, please [report it privately](https://github.com/doctrine/dbal/security/advisories/new) and reference it. We may edit, hide or lock the public item and continue in private. Please do not add further exploit details in the meantime.

## What happens next

We follow the process described in the [Doctrine security policy](https://www.doctrine-project.org/policies/security.html): we confirm the issue, acknowledge your report, prepare a patch, release fixed versions and publish an advisory crediting you, unless you prefer to remain anonymous. Please do not disclose the issue publicly until we have published the fix.
