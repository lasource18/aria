import type { Order } from './Order';
import type { TicketType } from './TicketType';
export interface Ticket {
    id: string;
    order_id: string;
    ticket_type_id: string;
    code: string;
    holder_name: string;
    holder_email: string;
    status: 'valid' | 'checked_in' | 'canceled';
    checked_in_at: string | null;
    checked_in_by: string | null;
    canceled_at: string | null;
    created_at: string;
    updated_at: string;
    order?: Order;
    ticketType?: TicketType;
}
//# sourceMappingURL=Ticket.d.ts.map