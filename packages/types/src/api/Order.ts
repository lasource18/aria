import type { User } from './User';
import type { Event } from './Event';
import type { Payment } from './Payment';
import type { Ticket } from './Ticket';
import type { OrderState } from '../enums/OrderState';

export interface Order {
  id: string; // UUID
  event_id: string;
  user_id: string | null; // Null for guest orders
  buyer_email: string;
  buyer_phone: string; // E.164 format
  buyer_name: string;
  state: OrderState;
  subtotal_xof: string; // Decimal as string
  fees_xof: string; // Decimal as string
  total_xof: string; // Decimal as string
  confirmation_code: string;
  canceled_at: string | null; // ISO 8601
  refunded_at: string | null; // ISO 8601
  created_at: string; // ISO 8601
  updated_at: string; // ISO 8601
  // Relations (optional)
  event?: Event;
  user?: User;
  items?: OrderItem[];
  payments?: Payment[];
  tickets?: Ticket[];
}

export interface OrderItem {
  id: string; // UUID
  order_id: string;
  ticket_type_id: string;
  qty: number;
  unit_price_xof: string; // Decimal as string
  subtotal_xof: string; // Decimal as string
  created_at: string; // ISO 8601
  updated_at: string; // ISO 8601
}
