import type { User } from './User';
import type { Event } from './Event';
import type { Payment } from './Payment';
import type { Ticket } from './Ticket';
import type { OrderState } from '../enums/OrderState';
export interface Order {
    id: string;
    event_id: string;
    user_id: string | null;
    buyer_email: string;
    buyer_phone: string;
    buyer_name: string;
    state: OrderState;
    subtotal_xof: string;
    fees_xof: string;
    total_xof: string;
    confirmation_code: string;
    canceled_at: string | null;
    refunded_at: string | null;
    created_at: string;
    updated_at: string;
    event?: Event;
    user?: User;
    items?: OrderItem[];
    payments?: Payment[];
    tickets?: Ticket[];
}
export interface OrderItem {
    id: string;
    order_id: string;
    ticket_type_id: string;
    qty: number;
    unit_price_xof: string;
    subtotal_xof: string;
    created_at: string;
    updated_at: string;
}
//# sourceMappingURL=Order.d.ts.map