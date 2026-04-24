# flametrench/ids

Prefixed wire-format identifiers for [Flametrench](https://github.com/flametrench/spec).

```php
use Flametrench\Ids\Id;

Id::generate('usr');
// → "usr_0190f2a81b3c7abc8123456789abcdef"

Id::encode('org', '0190f2a8-1b3c-7abc-8123-456789abcdef');
// → "org_0190f2a81b3c7abc8123456789abcdef"

Id::decode('usr_0190f2a81b3c7abc8123456789abcdef');
// → ['type' => 'usr', 'uuid' => '0190f2a8-1b3c-7abc-8123-456789abcdef']

Id::isValid('usr_0190f2a81b3c7abc8123456789abcdef');           // true
Id::isValid('usr_0190f2a81b3c7abc8123456789abcdef', 'org');    // false
```

## Why prefixed IDs

Flametrench uses UUIDv7 in storage and prefixed strings on the wire. The wire format is self-describing: `usr_...` is a user, `org_...` is an organization, `ses_...` is a session. This is the Stripe playbook, and it pays off in every log line, support ticket, and debugger session.

The specification details live at [flametrench/spec/docs/ids.md](https://github.com/flametrench/spec/blob/main/docs/ids.md). Implementations in other languages follow the same format.

## Install

```bash
composer require flametrench/ids
```

Requires PHP 8.3 or newer. Ships with a dependency on `symfony/uid` for UUID handling.

## Registered type prefixes

The v0.1 specification reserves the following prefixes:

| Prefix | Resource           |
| ------ | ------------------ |
| `usr`  | User               |
| `org`  | Organization       |
| `mem`  | Membership         |
| `inv`  | Invitation         |
| `ses`  | Session            |
| `cred` | Credential         |
| `tup`  | Authorization tuple |

Implementations must not invent prefixes outside the specification. New prefixes are added through the specification's RFC process.

## Testing

```bash
composer install
composer test
```

The package uses PEST. The test suite covers encoding, decoding, validation, generation, round-trip properties, and sortability of generated IDs.

## License

Apache License 2.0. Copyright 2026 NDC Digital, LLC.
