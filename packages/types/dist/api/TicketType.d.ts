import type { Event } from './Event';
export interface TicketType {
    id: string;
    event_id: string;
    name: string;
    type: 'free' | 'paid' | 'donation';
    price_xof: string;
    fee_pass_through_pct: string;
    max_qty: number | null;
    per_order_limit: number;
    sales_start: string | null;
    sales_end: string | null;
    refundable: boolean;
    archived_at: string | null;
    created_at: string;
    updated_at: string;
    event?: Event;
}
//# sourceMappingURL=TicketType.d.ts.map