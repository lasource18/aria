import type { Order } from './Order';

export interface Payment {
  id: string; // UUID
  order_id: string;
  provider: 'orange_money' | 'mtn_momo' | 'wave';
  provider_tx_id: string | null;
  amount_xof: string; // Decimal as string
  status: 'pending' | 'completed' | 'failed' | 'refunded';
  completed_at: string | null; // ISO 8601
  failed_at: string | null; // ISO 8601
  refunded_at: string | null; // ISO 8601
  failure_reason: string | null;
  metadata: Record<string, any> | null;
  created_at: string; // ISO 8601
  updated_at: string; // ISO 8601
  // Relations (optional)
  order?: Order;
}
