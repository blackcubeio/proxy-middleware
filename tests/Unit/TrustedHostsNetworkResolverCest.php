<?php

declare(strict_types=1);

/**
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\ProxyMiddleware\Tests\Unit;

use Blackcube\ProxyMiddleware\TrustedHostsNetworkResolver;
use Blackcube\ProxyMiddleware\Tests\Support\UnitTester;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Unit tests for the only behavioural change vs upstream:
 * withTrustedIps() now accepts CIDR ranges in addition to plain IPs.
 *
 * These tests focus on:
 *   1. withTrustedIps() validation rules (plain IPs, CIDR, garbage)
 *   2. Immutability (with* returns a new instance)
 *   3. End-to-end CIDR matching via isTrustedIp() (private, reached via reflection)
 *
 * We deliberately do NOT exercise process() — the PSR-7 plumbing is unchanged
 * from upstream. The Blackcube-specific contract is entirely in withTrustedIps()
 * and the ability of isTrustedIp() to match against CIDR entries.
 *
 * @copyright 2010-2026 Blackcube - Philippe Gaultier
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
class TrustedHostsNetworkResolverCest
{
    public function withTrustedIpsAcceptsPlainIpv4(UnitTester $I): void
    {
        $I->wantTo('accept plain IPv4 addresses (upstream-compatible behaviour)');

        $resolver = (new TrustedHostsNetworkResolver())
            ->withTrustedIps([
                '192.168.1.1',
                '10.0.0.5',
                '203.0.113.42',
            ]);

        $I->assertSame(
            [
                '192.168.1.1',
                '10.0.0.5',
                '203.0.113.42',
            ],
            $this->readTrustedIps($resolver),
        );
    }

    public function withTrustedIpsAcceptsPlainIpv6(UnitTester $I): void
    {
        $I->wantTo('accept plain IPv6 addresses');

        $resolver = (new TrustedHostsNetworkResolver())
            ->withTrustedIps([
                '2001:db8::1',
                '::1',
            ]);

        $I->assertSame([
            '2001:db8::1',
            '::1',
        ], $this->readTrustedIps($resolver));
    }

    public function withTrustedIpsAcceptsCidrIpv4(UnitTester $I): void
    {
        $I->wantTo('accept CIDR IPv4 ranges (new behaviour, the whole reason for this fork)');

        $resolver = (new TrustedHostsNetworkResolver())
            ->withTrustedIps([
                '10.0.0.0/8',
                '192.168.1.0/24',
                '172.16.0.0/12',
                '0.0.0.0/0',
            ]);

        $I->assertSame(
            [
                '10.0.0.0/8',
                '192.168.1.0/24',
                '172.16.0.0/12',
                '0.0.0.0/0',
            ],
            $this->readTrustedIps($resolver),
        );
    }

    public function withTrustedIpsAcceptsCidrIpv6(UnitTester $I): void
    {
        $I->wantTo('accept CIDR IPv6 ranges');

        $resolver = (new TrustedHostsNetworkResolver())
            ->withTrustedIps([
                '2001:db8::/32',
                'fe80::/10',
                '::/0',
            ]);

        $I->assertSame(
            [
                '2001:db8::/32',
                'fe80::/10',
                '::/0',
            ],
            $this->readTrustedIps($resolver),
        );
    }

    public function withTrustedIpsAcceptsMixedList(UnitTester $I): void
    {
        $I->wantTo('accept a mix of plain IPs and CIDR ranges in the same call');

        $resolver = (new TrustedHostsNetworkResolver())
            ->withTrustedIps([
                '192.168.1.1',
                '10.0.0.0/8',
                '2001:db8::1',
                'fe80::/10',
            ]);

        $I->assertSame(
            [
                '192.168.1.1',
                '10.0.0.0/8',
                '2001:db8::1',
                'fe80::/10',
            ],
            $this->readTrustedIps($resolver),
        );
    }

    public function withTrustedIpsIsImmutable(UnitTester $I): void
    {
        $I->wantTo('verify that withTrustedIps returns a new instance (immutability)');

        $original = new TrustedHostsNetworkResolver();
        $modified = $original->withTrustedIps(['10.0.0.0/8']);

        $I->assertNotSame($original, $modified);
        $I->assertSame([], $this->readTrustedIps($original));
        $I->assertSame(['10.0.0.0/8'], $this->readTrustedIps($modified));
    }

    public function withTrustedIpsRejectsInvalidIp(UnitTester $I): void
    {
        $I->wantTo('reject an entry without "/" that is not a valid IP (upstream-compatible)');

        $I->expectThrowable(
            new InvalidArgumentException('"not-an-ip" is not a valid IP.'),
            static fn () => (new TrustedHostsNetworkResolver())->withTrustedIps(['not-an-ip']),
        );
    }

    public function withTrustedIpsRejectsCidrWithInvalidPrefix(UnitTester $I): void
    {
        $I->wantTo('reject CIDR with out-of-range prefix length (/33 for IPv4)');

        $I->expectThrowable(
            new InvalidArgumentException('"10.0.0.0/33" is not a valid CIDR range.'),
            static fn () => (new TrustedHostsNetworkResolver())->withTrustedIps(['10.0.0.0/33']),
        );
    }

    public function withTrustedIpsRejectsCidrWithNonNumericPrefix(UnitTester $I): void
    {
        $I->wantTo('reject CIDR with non-numeric prefix');

        $I->expectThrowable(
            new InvalidArgumentException('"10.0.0.0/abc" is not a valid CIDR range.'),
            static fn () => (new TrustedHostsNetworkResolver())->withTrustedIps(['10.0.0.0/abc']),
        );
    }

    public function withTrustedIpsRejectsCidrWithMalformedIp(UnitTester $I): void
    {
        $I->wantTo('reject CIDR whose IP part is malformed');

        $I->expectThrowable(
            new InvalidArgumentException('"999.999.999.999/24" is not a valid CIDR range.'),
            static fn () => (new TrustedHostsNetworkResolver())->withTrustedIps(['999.999.999.999/24']),
        );
    }

    public function withTrustedIpsRejectsEmptyString(UnitTester $I): void
    {
        $I->wantTo('reject an empty string entry');

        $I->expectThrowable(
            new InvalidArgumentException('Trusted IP must be non-empty string.'),
            static fn () => (new TrustedHostsNetworkResolver())->withTrustedIps(['']),
        );
    }

    public function withTrustedIpsRejectsNonString(UnitTester $I): void
    {
        $I->wantTo('reject an entry that is not a string');

        $I->expectThrowable(
            new InvalidArgumentException('Trusted IP must be non-empty string.'),
            /** @phpstan-ignore-next-line argument.type */
            static fn () => (new TrustedHostsNetworkResolver())->withTrustedIps([123]),
        );
    }

    public function isTrustedIpMatchesIpInsideCidr(UnitTester $I): void
    {
        $I->wantTo('match an IP that falls inside a configured CIDR range (end-to-end, the core promise)');

        $resolver = (new TrustedHostsNetworkResolver())
            ->withTrustedIps(['192.168.1.0/24']);

        $I->assertTrue($this->invokeIsTrustedIp($resolver, '192.168.1.1'));
        $I->assertTrue($this->invokeIsTrustedIp($resolver, '192.168.1.50'));
        $I->assertTrue($this->invokeIsTrustedIp($resolver, '192.168.1.255'));
    }

    public function isTrustedIpRejectsIpOutsideCidr(UnitTester $I): void
    {
        $I->wantTo('reject an IP that falls outside all configured CIDR ranges');

        $resolver = (new TrustedHostsNetworkResolver())
            ->withTrustedIps(['192.168.1.0/24']);

        $I->assertFalse($this->invokeIsTrustedIp($resolver, '192.168.2.1'));
        $I->assertFalse($this->invokeIsTrustedIp($resolver, '10.0.0.1'));
        $I->assertFalse($this->invokeIsTrustedIp($resolver, '8.8.8.8'));
    }

    public function isTrustedIpMatchesAcrossMultipleCidrs(UnitTester $I): void
    {
        $I->wantTo('match against any of several configured CIDR ranges');

        $resolver = (new TrustedHostsNetworkResolver())
            ->withTrustedIps([
                '10.0.0.0/8',
                '192.168.1.0/24',
                '172.16.0.0/12',
            ]);

        $I->assertTrue($this->invokeIsTrustedIp($resolver, '10.5.5.5'));
        $I->assertTrue($this->invokeIsTrustedIp($resolver, '192.168.1.42'));
        $I->assertTrue($this->invokeIsTrustedIp($resolver, '172.16.200.1'));
        $I->assertFalse($this->invokeIsTrustedIp($resolver, '11.0.0.1'));
        $I->assertFalse($this->invokeIsTrustedIp($resolver, '172.32.0.1'));
    }

    public function isTrustedIpMatchesMixedPlainAndCidr(UnitTester $I): void
    {
        $I->wantTo('match against a mix of plain IPs and CIDR ranges');

        $resolver = (new TrustedHostsNetworkResolver())
            ->withTrustedIps([
                '203.0.113.42',
                '10.0.0.0/8',
            ]);

        $I->assertTrue($this->invokeIsTrustedIp($resolver, '203.0.113.42'));
        $I->assertFalse($this->invokeIsTrustedIp($resolver, '203.0.113.43'));
        $I->assertTrue($this->invokeIsTrustedIp($resolver, '10.0.0.1'));
        $I->assertFalse($this->invokeIsTrustedIp($resolver, '11.0.0.1'));
    }

    public function isTrustedIpMatchesIpv6Cidr(UnitTester $I): void
    {
        $I->wantTo('match an IPv6 address that falls inside an IPv6 CIDR range');

        $resolver = (new TrustedHostsNetworkResolver())
            ->withTrustedIps(['2001:db8::/32']);

        $I->assertTrue($this->invokeIsTrustedIp($resolver, '2001:db8::1'));
        $I->assertTrue($this->invokeIsTrustedIp($resolver, '2001:db8:1234:5678::1'));
        $I->assertFalse($this->invokeIsTrustedIp($resolver, '2001:db9::1'));
    }

    public function isTrustedIpReturnsFalseWhenNoTrustedIpsConfigured(UnitTester $I): void
    {
        $I->wantTo('return false when no trusted IPs have been configured');

        $resolver = new TrustedHostsNetworkResolver();

        $I->assertFalse($this->invokeIsTrustedIp($resolver, '192.168.1.1'));
        $I->assertFalse($this->invokeIsTrustedIp($resolver, '10.0.0.1'));
    }

    /**
     * @return string[]
     */
    private function readTrustedIps(TrustedHostsNetworkResolver $resolver): array
    {
        $prop = new ReflectionProperty(TrustedHostsNetworkResolver::class, 'trustedIps');
        /** @var string[] $value */
        $value = $prop->getValue($resolver);

        return $value;
    }

    private function invokeIsTrustedIp(TrustedHostsNetworkResolver $resolver, string $ip): bool
    {
        $method = new ReflectionMethod(TrustedHostsNetworkResolver::class, 'isTrustedIp');

        return (bool) $method->invoke($resolver, $ip);
    }
}
