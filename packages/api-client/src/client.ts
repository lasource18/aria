export class APIError extends Error {
  constructor(
    message: string,
    public code: string,
    public status: number,
    public details?: any
  ) {
    super(message);
    this.name = 'APIError';
  }
}

export interface APIClientConfig {
  baseURL: string;
  getAuthToken?: () => string | null;
}

export interface APIResponse<T> {
  data: T;
  meta?: {
    pagination?: {
      total: number;
      per_page: number;
      current_page: number;
      last_page: number;
    };
  };
}

export class APIClient {
  constructor(private config: APIClientConfig) {}

  async request<T>(
    method: string,
    path: string,
    body?: any
  ): Promise<T> {
    const url = `${this.config.baseURL}${path}`;
    const headers: HeadersInit = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    const token = this.config.getAuthToken?.();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    try {
      const response = await fetch(url, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
      });

      const data = await response.json() as any;

      if (!response.ok) {
        throw new APIError(
          data.error?.message || data.message || 'Request failed',
          data.error?.code || 'UNKNOWN_ERROR',
          response.status,
          data.error?.details
        );
      }

      // If the response has a data wrapper, unwrap it
      return (data.data !== undefined ? data.data : data) as T;
    } catch (error) {
      if (error instanceof APIError) {
        throw error;
      }
      // Network errors or JSON parse errors
      throw new APIError(
        error instanceof Error ? error.message : 'Network error',
        'NETWORK_ERROR',
        0
      );
    }
  }

  get<T>(path: string): Promise<T> {
    return this.request<T>('GET', path);
  }

  post<T>(path: string, body: any): Promise<T> {
    return this.request<T>('POST', path, body);
  }

  patch<T>(path: string, body: any): Promise<T> {
    return this.request<T>('PATCH', path, body);
  }

  delete<T>(path: string): Promise<T> {
    return this.request<T>('DELETE', path);
  }
}
