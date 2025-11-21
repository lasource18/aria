import type { Order } from '@aria/types';
import { APIClient } from './client';

export interface CreateOrderRequest {
  buyer_email: string;
  buyer_phone: string; // E.164 format
  buyer_name: string;
  items: Array<{
    ticket_type_id: string;
    qty: number;
  }>;
}

export class OrdersAPI {
  constructor(private client: APIClient) {}

  /**
   * Create a new order for an event
   * POST /v1/events/{eventSlug}/orders
   */
  async create(eventSlug: string, data: CreateOrderRequest): Promise<Order> {
    return this.client.post<Order>(`/v1/events/${eventSlug}/orders`, data);
  }

  /**
   * Get order by ID
   * GET /v1/orders/{id}
   */
  async get(id: string): Promise<Order> {
    return this.client.get<Order>(`/v1/orders/${id}`);
  }

  /**
   * Get order by confirmation code
   * GET /v1/orders/by-code/{confirmationCode}
   */
  async getByCode(confirmationCode: string): Promise<Order> {
    return this.client.get<Order>(`/v1/orders/by-code/${confirmationCode}`);
  }

  /**
   * Cancel an order
   * POST /v1/orders/{id}/cancel
   */
  async cancel(id: string): Promise<Order> {
    return this.client.post<Order>(`/v1/orders/${id}/cancel`, {});
  }
}
