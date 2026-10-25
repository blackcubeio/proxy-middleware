# blackcube/proxy-middleware — LLM index

**Role:** PSR-15 middleware resolving the client IP and the request URI (scheme, host, port) behind trusted proxies. Copy of `Yiisoft\ProxyMiddleware\TrustedHostsNetworkResolver` whose `withTrustedIps()` also accepts CIDR ranges; the rest follows yiisoft/proxy-middleware 1.0.3.

## Package card

| | |
|---|---|
| Namespace | `Blackcube\ProxyMiddleware` |
| Class | `TrustedHostsNetworkResolver` (implements `MiddlewareInterface`) |
| Requires | PHP ^8.2, `yiisoft/proxy-middleware` ^1.0 (its exceptions `InvalidConnectionChainItemException`, `RfcProxyParseException`; `IpHelper` / `IpRanges` from yiisoft/network-utilities, `HeaderValueHelper` from yiisoft/http). No Blackcube dependency. |
| Yii config | No config-plugin: the application declares the trusted IPs and places the middleware. |
| License | BSD-3-Clause (upstream copyright kept in `LICENSE.md`) |

## Rules

- `withTrustedIps()` accepts plain IPv4/IPv6 and CIDR ranges (`10.0.0.0/8`, `2001:db8::/32`); anything else throws `InvalidArgumentException`.
- The client IP is set as the request attribute `requestClientIp` (`TrustedHostsNetworkResolver::ATTRIBUTE_REQUEST_CLIENT_IP`); `null` when there is no `REMOTE_ADDR`.
- Without trusted IPs, the client IP is `REMOTE_ADDR`.
- The URI scheme, host and port come from the matched forwarded header group even when `REMOTE_ADDR` is not trusted (same as yiisoft).
- Header groups are tried in order (default: RFC `Forwarded`, then `X-Forwarded-*`); the first group whose IP header is non-empty wins; typical forwarded headers of the other groups are removed from the request.
- An invalid IP, protocol, host or port in a forwarded header throws (`InvalidConnectionChainItemException`, `RfcProxyParseException`): the middleware does not catch it.

## Parts

| File | Content | Read when |
|---|---|---|
| `resolver.md` | Constants, `with*()` methods and their validation, the `process()` algorithm (chain building, walk, stop conditions, URI rewrite, header filtering), measured cases. | before configuring or reading the client IP |
| `integration.md` | Yii: trusted IPs from the environment, place in the middleware stack, reading `requestClientIp`. | before plugging the middleware in |
| `extension.md` | `reverseObfuscateIpIdentifier()` (the only `protected` point); what does not extend. | before extending |
