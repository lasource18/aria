# ADR-0013: Data Protection and Privacy

**Status**: Accepted
**Date**: 2025-11-19
**Deciders**: Architect Agent
**Tags**: [architecture, security, privacy, compliance]

## Context and Problem Statement

Aria handles PII (email, phone, names) and payment references. We must comply with Côte d'Ivoire data protection regulations and best practices (GDPR-inspired).

**Referenced sections**: DESIGN.md Section 12 (Security & Risk)

## Decision Outcome

### PII Encryption at Rest
- **Phone numbers**: Encrypted using Laravel `encrypt()` helper (AES-256-GCM)
- **Email**: Stored plain-text (needed for login/search) but masked in logs
- **Payment references**: Encrypted in `payments.provider_ref` column

### Data Retention Policy
- **Orders/Payments**: 7 years (tax compliance)
- **PII anonymization**: After 2 years, replace names/emails with "ANONYMIZED_<hash>"
- **Audit logs**: 5 years (immutable)

### PII Scrubbing in Logs
```php
<?php
function mask_phone(string $phone): string {
    return substr($phone, 0, 4) . '****' . substr($phone, -2);
}

function mask_email(string $email): string {
    [$local, $domain] = explode('@', $email);
    return substr($local, 0, 2) . '***@' . $domain;
}
```

### Encryption Key Management
- **Laravel Encryption Key**: Rotated quarterly; stored in environment variables
- **Database-level Encryption**: RDS encryption at rest enabled
- **Backup Encryption**: Automated backups encrypted with KMS

### User Data Export/Deletion (GDPR-inspired)
```php
<?php
public function exportUserData(User $user)
{
    return [
        'profile' => $user->only(['email', 'full_name', 'phone']),
        'orders' => $user->orders()->with('tickets')->get(),
        'org_memberships' => $user->orgs()->get(),
    ];
}

public function deleteUserData(User $user)
{
    // Anonymize orders (keep for tax records)
    $user->orders()->update(['email' => 'ANONYMIZED', 'phone' => null]);

    // Delete user account
    $user->delete();
}
```

## References
- DESIGN.md Section 12: Security & Risk
- External: [GDPR](https://gdpr.eu/), [Laravel Encryption](https://laravel.com/docs/11.x/encryption)
