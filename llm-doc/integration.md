# proxy-middleware — Yii integration

## Split

- Package: no config-plugin.
- Application: trusted IPs, position in the middleware stack, use of `requestClientIp`.

## Trusted IPs

Reference: starter `config/web/di/proxy.php`. The list comes from the environment (`AppEnvironment::getTrustedHosts()` = `APP_TRUSTED_HOSTS` split on commas); the definition is added only when the list is not empty:

```php
use App\AppEnvironment;
use Blackcube\ProxyMiddleware\TrustedHostsNetworkResolver;

$appEnvironment = AppEnvironment::create();
$trustedHosts = $appEnvironment->getTrustedHosts();

$config = [];
if (empty($trustedHosts) === false) {
    $config = [
        TrustedHostsNetworkResolver::class => [
            'withTrustedIps()' => [$trustedHosts],
        ],
    ];
}

return $config;
```

Without a definition the container builds the middleware with no trusted IP: the client IP is `REMOTE_ADDR`.

## Middleware stack

Reference: starter `config/web/di/application.php` — right after `ErrorCatcher`, before session, body parsing, routing:

```php
'withMiddlewares()' => [
    [
        ErrorCatcher::class,
        TrustedHostsNetworkResolver::class,
        SessionMiddleware::class,
        // …
        Router::class,
    ],
],
```

Placed after `ErrorCatcher`, an invalid forwarded header (exception thrown by the middleware) becomes an error response.

## Reading the client IP

```php
$ip = $request->getAttribute(TrustedHostsNetworkResolver::ATTRIBUTE_REQUEST_CLIENT_IP); // 'requestClientIp', ?string
```

The URI seen by later middlewares and handlers (`$request->getUri()`) carries the resolved scheme, host and port.
