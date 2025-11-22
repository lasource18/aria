import type { Order } from './Order';
export interface Payment {
    id: string;
    order_id: string;
    provider: 'orange_money' | 'mtn_momo' | 'wave';
    provider_tx_id: string | null;
    amount_xof: string;
    status: 'pending' | 'completed' | 'failed' | 'refunded';
    completed_at: string | null;
    failed_at: string | null;
    refunded_at: string | null;
    failure_reason: string | null;
    metadata: Record<string, any> | null;
    created_at: string;
    updated_at: string;
    order?: Order;
}
//# sourceMappingURL=Payment.d.ts.map