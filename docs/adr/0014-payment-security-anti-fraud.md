# ADR-0014: Payment Security and Anti-Fraud

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, security, payments, fraud]

## Context and Problem Statement

Aria must protect against fraudulent organizers, stolen payment credentials, and replay attacks on payment webhooks.

**Referenced sections**: DESIGN.md Section 12 (Security & Risk - Anti-fraud)

## Decision Outcome

### Webhook Security
- **Signature Verification**: HMAC SHA256 on raw request body
- **Replay Protection**: Reject webhooks with timestamp >5 minutes old
- **Nonce Tracking**: Store webhook IDs in Redis (24-hour TTL) to prevent duplicates

```php
<?php
public function verifyWebhookSignature(Request $request, string $provider): bool
{
    $secret = config("payment.providers.{$provider}.webhook_secret");
    $receivedSig = $request->header('X-Webhook-Signature');
    $timestamp = $request->header('X-Webhook-Timestamp');

    // Replay protection
    if (abs(now()->timestamp - $timestamp) > 300) {
        throw new ReplayAttackException('Webhook timestamp too old');
    }

    // Signature verification
    $payload = $request->getContent();
    $expectedSig = hash_hmac('sha256', $timestamp . $payload, $secret);

    return hash_equals($expectedSig, $receivedSig);
}
```

### Velocity Checks (Anti-fraud)
```php
<?php
public function checkVelocityLimits(string $msisdn, string $ip): void
{
    $orderCount = Cache::remember("orders:{$msisdn}:1h", 3600, function() use ($msisdn) {
        return Order::where('phone', $msisdn)->where('created_at', '>', now()->subHour())->count();
    });

    if ($orderCount >= 5) {
        throw new VelocityLimitException('Too many orders from this phone number');
    }

    $ipOrderCount = Cache::remember("orders:{$ip}:1h", 3600, function() use ($ip) {
        return Order::where('checkout_ip', $ip)->where('created_at', '>', now()->subHour())->count();
    });

    if ($ipOrderCount >= 10) {
        throw new VelocityLimitException('Too many orders from this IP address');
    }
}
```

### Organizer KYB/KYC Thresholds
- **Verification required**: Cumulative sales >XOF 1,000,000 OR refund rate >10%
- **Payout hold**: Manual admin approval for first 3 payouts
- **Rolling reserves**: 10% held for 14 days for high-risk organizers

### Secrets Rotation Policy
- **Webhook secrets**: Rotated every 90 days; old secrets valid for 7-day grace period
- **API keys**: Rotated on compromise; invalidate old tokens immediately
- **Encryption keys**: Rotated quarterly; decrypt-and-re-encrypt migration job

## References
- DESIGN.md Section 12: Security & Risk (Anti-fraud)
- External: [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/)
