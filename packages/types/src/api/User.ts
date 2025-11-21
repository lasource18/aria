export interface User {
  id: string; // UUID
  email: string;
  phone: string | null; // E.164 format
  full_name: string;
  preferences: Record<string, any>;
  locale: string;
  is_platform_admin: boolean;
  email_verified_at: string | null; // ISO 8601
  created_at: string; // ISO 8601
  updated_at: string; // ISO 8601
}
