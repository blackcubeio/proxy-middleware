# proxy-middleware — Extension

## Extension point

| Member | Visibility | Use |
|---|---|---|
| `reverseObfuscateIpIdentifier(string $ipIdentifier, array $validatedConnectionChainItems, array $remainingConnectionChainItems, RequestInterface $request): ?array` | protected | resolve an obfuscated RFC 7239 identifier (`for=_xxx`) into an IP. Return `[ip, port]` (`port` = non-empty string or `null`) or `null` when unresolved. Default: `null`. |

The returned data is checked: an invalid IP or port throws `RuntimeException` (`IP returned from reverse-obfuscated IP data is not valid.`, `Port returned from reverse-obfuscated IP data is not valid.`).

Configuration (trusted IPs, header groups, typical headers, chain attribute) goes through the `with*()` methods, not through subclassing.

## Not extensible

| Element | Reason |
|---|---|
| Chain building, walk, private-IP rule, URI rewrite, header filtering | `private` methods |
| Configuration state | `private` properties, set only by the `with*()` methods |
| Allowed protocols, RFC directives, port bounds | `private const` |
| Upstream `Yiisoft\ProxyMiddleware\TrustedHostsNetworkResolver` | not extended: this class is a copy (every upstream state member is `private`) |
