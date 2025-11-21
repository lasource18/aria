import type { Org } from './Org';
import type { TicketType } from './TicketType';

export interface Event {
  id: string; // UUID
  org_id: string;
  title: string;
  slug: string;
  description_md: string | null;
  category: string | null;
  venue_name: string | null;
  venue_address: string | null;
  location: { lat: number; lng: number } | null;
  start_at: string; // ISO 8601
  end_at: string; // ISO 8601
  timezone: string;
  status: 'draft' | 'published' | 'canceled' | 'ended';
  is_online: boolean;
  settings: Record<string, any> | null;
  created_at: string; // ISO 8601
  updated_at: string; // ISO 8601
  // Relations (optional)
  org?: Org;
  ticketTypes?: TicketType[];
}
