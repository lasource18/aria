# ADR-0012: Observability and Monitoring Stack

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, infra, observability, monitoring]

## Context and Problem Statement

Aria requires real-time visibility into payment success rates, API latency, queue depths, and error spikes to meet 99.9% uptime SLA.

**Referenced sections**: DESIGN.md Section 13 (Observability & Ops)

## Decision Outcome

**Stack**:
- **Logs**: Structured JSON logs → Sentry (errors) + DigitalOcean Logs (access logs)
- **Metrics**: Laravel Telescope (dev) + Prometheus + Grafana (prod)
- **Traces**: OpenTelemetry → Jaeger (payment flow tracing)
- **Alerts**: PagerDuty (critical: payment failures, webhook backlog)

### Key Metrics (SLIs)

| Metric | Target | Alert Threshold |
|--------|--------|-----------------|
| API P95 Latency | <200ms | >500ms for 5 min |
| Payment Success Rate | >95% | <90% for 10 min |
| Webhook Processing Time | <2s P95 | >10s P95 |
| Queue Depth | <100 jobs | >1000 jobs |
| Order Conversion Rate | N/A | <50% (trend) |

### Implementation

**Structured Logging**:
```php
<?php
Log::channel('payments')->info('Payment initiated', [
    'order_id' => $order->id,
    'provider' => $provider,
    'amount_xof' => $order->amount_total,
    'msisdn' => mask_phone($msisdn),
]);
```

**Custom Metrics**:
```php
<?php
// app/Observers/PaymentObserver.php
public function updated(Payment $payment)
{
    if ($payment->wasChanged('state')) {
        Prometheus::counter('payments_total', 'Total payments')
            ->labels(['provider' => $payment->provider, 'state' => $payment->state])
            ->inc();

        if ($payment->state === 'succeeded') {
            Prometheus::histogram('payment_duration_seconds', 'Payment duration')
                ->observe($payment->created_at->diffInSeconds(now()));
        }
    }
}
```

## References
- DESIGN.md Section 13: Observability & Ops
- External: [Sentry](https://sentry.io/), [Prometheus](https://prometheus.io/)
