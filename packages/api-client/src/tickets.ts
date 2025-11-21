import type { Ticket } from '@aria/types';
import { APIClient } from './client';

export class TicketsAPI {
  constructor(private client: APIClient) {}

  /**
   * Get ticket by code
   * GET /v1/tickets/{code}
   */
  async get(code: string): Promise<Ticket> {
    return this.client.get<Ticket>(`/v1/tickets/${code}`);
  }

  /**
   * Check in a ticket (requires authentication)
   * POST /v1/tickets/{code}/check-in
   */
  async checkIn(code: string): Promise<Ticket> {
    return this.client.post<Ticket>(`/v1/tickets/${code}/check-in`, {});
  }

  /**
   * Cancel a ticket (requires authentication)
   * POST /v1/tickets/{code}/cancel
   */
  async cancel(code: string): Promise<Ticket> {
    return this.client.post<Ticket>(`/v1/tickets/${code}/cancel`, {});
  }
}
