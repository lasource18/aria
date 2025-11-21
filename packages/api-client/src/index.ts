export * from './client';
export * from './events';
export * from './orders';
export * from './tickets';

import { APIClient, APIClientConfig } from './client';
import { EventsAPI } from './events';
import { OrdersAPI } from './orders';
import { TicketsAPI } from './tickets';

/**
 * Create a type-safe API client instance
 *
 * @example
 * ```typescript
 * import { createAPIClient } from '@aria/api-client';
 *
 * const api = createAPIClient({
 *   baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
 *   getAuthToken: () => localStorage.getItem('token'),
 * });
 *
 * // Use the API client
 * const events = await api.events.search({ category: 'music' });
 * const event = await api.events.getBySlug('my-event');
 * ```
 */
export function createAPIClient(config: APIClientConfig) {
  const client = new APIClient(config);

  return {
    events: new EventsAPI(client),
    orders: new OrdersAPI(client),
    tickets: new TicketsAPI(client),
  };
}
