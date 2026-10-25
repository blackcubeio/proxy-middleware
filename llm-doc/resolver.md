# proxy-middleware — `TrustedHostsNetworkResolver`

## Constants (public)

| Constant | Value |
|---|---|
| `FORWARDED_HEADER_RFC`, `FORWARDED_HEADER_GROUP_RFC` | `'forwarded'` |
| `FORWARDED_HEADER_GROUP_X_PREFIX` | `['ip' => 'x-forwarded-for', 'protocol' => 'x-forwarded-proto', 'host' => 'x-forwarded-host', 'port' => 'x-forwarded-port']` |
| `DEFAULT_FORWARDED_HEADER_GROUPS` | `[FORWARDED_HEADER_GROUP_RFC, FORWARDED_HEADER_GROUP_X_PREFIX]` |
| `TYPICAL_FORWARDED_HEADERS` | `forwarded`, `x-forwarded-for`, `x-forwarded-host`, `x-forwarded-proto`, `x-forwarded-port`, `front-end-https` |
| `ATTRIBUTE_REQUEST_CLIENT_IP` | `'requestClientIp'` |

## Configuration (immutable: each `with*()` returns a clone)

| Method | Accepts | Throws `InvalidArgumentException` |
|---|---|---|
| `withTrustedIps(array $trustedIps)` | plain IPs and CIDR ranges | non-string or empty entry; entry with `/` that is not a valid CIDR (`"x" is not a valid CIDR range.`); entry without `/` that is not an IP (`"x" is not a valid IP.`) |
| `withForwardedHeaderGroups(array $headerGroups)` | `FORWARDED_HEADER_GROUP_RFC` or arrays with exactly the keys `ip`, `protocol`, `host`, `port`; `protocol` = header name, or `[headerName, mapping array]`, or `[headerName, callable]` | empty list, wrong keys, empty names, invalid protocol mapping |
| `withTypicalForwardedHeaders(array $headerNames)` | header names (lower-cased) | empty list or empty name |
| `withConnectionChainItemsAttribute(?string $attribute)` | attribute name receiving the validated chain items, or `null` | empty string |

Header names are lower-cased. Protocols allowed: `http`, `https` (case-sensitive).

## `process()`

1. No `REMOTE_ADDR`: `requestClientIp` = `null` (and the chain attribute = `null`), request otherwise untouched.
2. Chain building: first item = `REMOTE_ADDR`. Groups are tried in order; the first group with a non-empty IP header is used:
   - RFC `Forwarded`: each element parsed (`for`, `by`, `proto`, `host` directives; `for` required; obfuscated identifiers `_xxx` and `unknown` allowed);
   - separate headers: the IP header line is split on `,` and trimmed; every item gets the protocol, host and port headers.
   The chain is walked from `REMOTE_ADDR` towards the client (header values reversed).
3. Headers listed in `typicalForwardedHeaders` but not part of the matched group are removed from the request.
4. Walk: the first item is always validated; the walk continues while the current IP is trusted; it stops on an untrusted IP, on a private IP after the first item, on an item without IP, or on the identifier `unknown`. The last reached item gives the client IP and the URI parts.
5. URI: `withScheme(protocol)`, `withHost(host)`, `withPort(port)` for the non-null parts of that item.
6. Attributes: `requestClientIp` = item IP; the chain attribute (if configured) = the validated items (first item + trusted ones).

## Measured cases (`10.0.0.0/8` trusted unless stated)

| Request | `requestClientIp` | URI | Headers left |
|---|---|---|---|
| no `REMOTE_ADDR` + `X-Forwarded-*` | `null` | unchanged | all |
| `REMOTE_ADDR` 10.0.0.5, no trusted IPs, `X-Forwarded-For: 203.0.113.9`, proto `https`, host `example.com`, port 443 | `10.0.0.5` | `https://example.com/path` | `x-forwarded-*` (not `front-end-https`) |
| `REMOTE_ADDR` 10.0.0.5, same headers | `203.0.113.9` | `https://example.com/path` | `x-forwarded-*` |
| `REMOTE_ADDR` 198.51.100.7 (outside the range), same headers | `198.51.100.7` | `https://example.com/path` | `x-forwarded-*` |
| `REMOTE_ADDR` 10.0.0.5, `X-Forwarded-For: 203.0.113.9, 192.168.1.10` | `10.0.0.5` (private hop stops the walk) | unchanged | `x-forwarded-for` |
| `REMOTE_ADDR` 10.0.0.5, `Forwarded: for=203.0.113.9;proto=https;host=example.com` + `X-Forwarded-For` | `203.0.113.9` | `https://example.com/path` | `forwarded` |
| `REMOTE_ADDR` 10.0.0.5, `X-Forwarded-For: not-an-ip` | `InvalidConnectionChainItemException` `"not-an-ip" is not a valid IP.` | | |
| `REMOTE_ADDR` 10.0.0.5, only `Front-End-Https` | `10.0.0.5` | unchanged | none |
