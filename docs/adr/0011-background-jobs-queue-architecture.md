# ADR-0011: Background Jobs and Queue Architecture

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, backend, queues, jobs]

## Context and Problem Statement

Aria requires async processing for ticket generation, email/SMS dispatch, payment reconciliation, payout batching, and order expiry. Jobs must be reliable, retriable, and prioritized.

**Referenced sections**: DESIGN.md Section 17 (Background Jobs & Schedulers)

## Decision Outcome

**Queue Backend**: Redis + Laravel Horizon (Redis-based queue manager with dashboard)

### Job Types and Priorities

| Job | Priority | Timeout | Retries |
|-----|----------|---------|---------|
| GenerateTickets | critical | 60s | 5 |
| SendTicketEmail | normal | 30s | 3 |
| SendTicketSMS | normal | 30s | 3 |
| ExpireOrders | normal | 120s | 1 |
| ReconcilePayments | low | 300s | 3 |
| BatchPayouts | low | 600s | 5 |

### Implementation

**GenerateTickets Job**:
```php
<?php
namespace App\Jobs;

use App\Models\{Order, Ticket};
use Illuminate\Support\Str;

class GenerateTickets implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $tries = 5;
    public $timeout = 60;
    public $queue = 'critical';

    public function __construct(public Order $order) {}

    public function handle()
    {
        foreach ($this->order->items as $item) {
            for ($i = 0; $i < $item->qty; $i++) {
                $ticket = Ticket::create([
                    'order_id' => $this->order->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'code' => $this->generateUniqueCode(),
                    'qr_signature' => $this->generateQRSignature(),
                    'state' => 'issued',
                ]);

                // Generate QR code image and upload to S3
                $qrUrl = $this->generateQRCode($ticket);
                $ticket->update(['qr_url' => $qrUrl]);
            }
        }

        // Dispatch notification jobs
        SendTicketEmail::dispatch($this->order);
        SendTicketSMS::dispatch($this->order);
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Ticket::where('code', $code)->exists());

        return $code;
    }
}
```

**Scheduled Tasks (Cron)**:
```php
<?php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Expire orders every minute
    $schedule->job(new ExpireOrders)->everyMinute();

    // Reconcile payments hourly
    $schedule->job(new ReconcilePayments)->hourly();

    // Batch payouts daily at 03:00
    $schedule->job(new BatchPayouts)->dailyAt('03:00');

    // Analytics ETL every 6 hours
    $schedule->job(new ETLAnalytics)->everySixHours();
}
```

## References
- DESIGN.md Section 17: Background Jobs & Schedulers
- External: [Laravel Horizon](https://laravel.com/docs/11.x/horizon)
