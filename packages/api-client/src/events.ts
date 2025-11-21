import type { Event } from '@aria/types';
import { APIClient } from './client';

export interface EventSearchParams {
  q?: string;
  category?: string;
  from?: string; // ISO 8601 date
  to?: string; // ISO 8601 date
  lat?: number;
  lng?: number;
  price?: 'free' | 'paid';
  status?: 'draft' | 'published' | 'canceled' | 'ended';
}

export interface CreateEventRequest {
  title: string;
  slug: string;
  description_md?: string;
  category?: string;
  venue_name?: string;
  venue_address?: string;
  location?: { lat: number; lng: number };
  start_at: string; // ISO 8601
  end_at: string; // ISO 8601
  timezone: string;
  is_online?: boolean;
  settings?: Record<string, any>;
}

export interface UpdateEventRequest extends Partial<CreateEventRequest> {
  status?: 'draft' | 'published' | 'canceled' | 'ended';
}

export class EventsAPI {
  constructor(private client: APIClient) {}

  /**
   * Search for events
   * GET /v1/events
   */
  async search(params?: EventSearchParams): Promise<Event[]> {
    const query = new URLSearchParams();

    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined) {
          query.append(key, String(value));
        }
      });
    }

    const queryString = query.toString();
    const path = queryString ? `/v1/events?${queryString}` : '/v1/events';

    return this.client.get<Event[]>(path);
  }

  /**
   * Get event by slug
   * GET /v1/events/{slug}
   */
  async getBySlug(slug: string): Promise<Event> {
    return this.client.get<Event>(`/v1/events/${slug}`);
  }

  /**
   * Create a new event (requires authentication)
   * POST /v1/orgs/{orgId}/events
   */
  async create(orgId: string, data: CreateEventRequest): Promise<Event> {
    return this.client.post<Event>(`/v1/orgs/${orgId}/events`, data);
  }

  /**
   * Update an event (requires authentication)
   * PATCH /v1/events/{id}
   */
  async update(id: string, data: UpdateEventRequest): Promise<Event> {
    return this.client.patch<Event>(`/v1/events/${id}`, data);
  }

  /**
   * Publish an event (requires authentication)
   * POST /v1/events/{id}/publish
   */
  async publish(id: string): Promise<Event> {
    return this.client.post<Event>(`/v1/events/${id}/publish`, {});
  }

  /**
   * Cancel an event (requires authentication)
   * POST /v1/events/{id}/cancel
   */
  async cancel(id: string): Promise<Event> {
    return this.client.post<Event>(`/v1/events/${id}/cancel`, {});
  }
}
