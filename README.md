# Blackcube Proxy Middleware

Fork of [`Yiisoft\ProxyMiddleware\TrustedHostsNetworkResolver`](https://github.com/yiisoft/proxy-middleware) with CIDR range support added to `withTrustedIps()`.

[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/blackcube/proxy-middleware.svg)](https://packagist.org/packages/blackcube/proxy-middleware)

## Installation

```bash
composer require blackcube/proxy-middleware
```

## Why this fork?

The upstream `TrustedHostsNetworkResolver::withTrustedIps()` rejects any entry that is not a plain IP — **even though its own matching engine (`Yiisoft\NetworkUtilities\IpRanges`) already supports CIDR**.

| Entry               | Upstream           | This fork |
|---------------------|--------------------|-----------|
| `192.168.1.1`       | accepted           | accepted  |
| `10.0.0.0/8`        | rejected           | accepted  |
| `2001:db8::/32`     | rejected           | accepted  |
| `10.0.0.0/33`       | rejected           | rejected  |
| `not-an-ip`         | rejected           | rejected  |

Every internal state field in the upstream class is `private`, so clean extension is not possible — the class is duplicated and `withTrustedIps()` is the only behavioural change.

## Usage

```php
use Blackcube\ProxyMiddleware\TrustedHostsNetworkResolver;

$middleware = (new TrustedHostsNetworkResolver())
    ->withTrustedIps([
        '10.0.0.0/8',          // whole private network
        '192.168.1.0/24',      // LAN subnet
        '2001:db8::/32',       // IPv6 documentation range
        '203.0.113.42',        // single IP still works
    ]);
```

All other methods (`withForwardedHeaderGroups()`, `withTypicalForwardedHeaders()`, `withConnectionChainItemsAttribute()`, `process()`) behave identically to the upstream — see the [upstream docs](https://github.com/yiisoft/proxy-middleware) for details.

## Validation rules

`withTrustedIps()` throws `InvalidArgumentException` when:

- an entry is not a non-empty string
- an entry contains `/` but is not a valid CIDR (invalid prefix, malformed IP)
- an entry has no `/` and is not a valid IPv4/IPv6 address

## Credits

Forked from [`yiisoft/proxy-middleware`](https://github.com/yiisoft/proxy-middleware) (© Yii Software, BSD 3-Clause). All credit for the original `TrustedHostsNetworkResolver` goes to the Yiisoft contributors.

## License

BSD 3-Clause — see [LICENSE.md](LICENSE.md) for the full license text, including the upstream copyright notice.
