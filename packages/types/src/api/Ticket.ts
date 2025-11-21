import type { Order } from './Order';
import type { TicketType } from './TicketType';

export interface Ticket {
  id: string; // UUID
  order_id: string;
  ticket_type_id: string;
  code: string; // QR code identifier
  holder_name: string;
  holder_email: string;
  status: 'valid' | 'checked_in' | 'canceled';
  checked_in_at: string | null; // ISO 8601
  checked_in_by: string | null; // UUID of user who checked in
  canceled_at: string | null; // ISO 8601
  created_at: string; // ISO 8601
  updated_at: string; // ISO 8601
  // Relations (optional)
  order?: Order;
  ticketType?: TicketType;
}
