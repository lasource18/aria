export interface User {
    id: string;
    email: string;
    phone: string | null;
    full_name: string;
    preferences: Record<string, any>;
    locale: string;
    is_platform_admin: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}
//# sourceMappingURL=User.d.ts.map