import type { User } from './User';
import type { Event } from './Event';

export type PayoutChannel = 'orange_mo' | 'mtn_momo' | 'wave' | 'bank';

export interface Org {
  id: string; // UUID
  name: string;
  slug: string;
  country_code: string;
  kyb_data: Record<string, any> | null;
  payout_channel: PayoutChannel | null;
  payout_identifier: string | null;
  verified: boolean;
  created_at: string; // ISO 8601
  updated_at: string; // ISO 8601
  // Relations (optional)
  members?: OrgMember[];
  events?: Event[];
}

export interface OrgMember {
  id: string; // UUID
  org_id: string;
  user_id: string;
  role: 'owner' | 'admin' | 'member';
  created_at: string; // ISO 8601
  updated_at: string; // ISO 8601
  // Relations (optional)
  org?: Org;
  user?: User;
}
