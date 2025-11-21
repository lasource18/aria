import type { Event } from './Event';

export interface TicketType {
  id: string; // UUID
  event_id: string;
  name: string;
  type: 'free' | 'paid' | 'donation';
  price_xof: string; // Decimal as string
  fee_pass_through_pct: string; // Decimal as string
  max_qty: number | null;
  per_order_limit: number;
  sales_start: string | null; // ISO 8601
  sales_end: string | null; // ISO 8601
  refundable: boolean;
  archived_at: string | null; // ISO 8601
  created_at: string; // ISO 8601
  updated_at: string; // ISO 8601
  // Relations (optional)
  event?: Event;
}
