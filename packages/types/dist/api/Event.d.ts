import type { Org } from './Org';
import type { TicketType } from './TicketType';
export interface Event {
    id: string;
    org_id: string;
    title: string;
    slug: string;
    description_md: string | null;
    category: string | null;
    venue_name: string | null;
    venue_address: string | null;
    location: {
        lat: number;
        lng: number;
    } | null;
    start_at: string;
    end_at: string;
    timezone: string;
    status: 'draft' | 'published' | 'canceled' | 'ended';
    is_online: boolean;
    settings: Record<string, any> | null;
    created_at: string;
    updated_at: string;
    org?: Org;
    ticketTypes?: TicketType[];
}
//# sourceMappingURL=Event.d.ts.map