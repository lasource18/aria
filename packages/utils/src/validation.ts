/**
 * Validate E.164 phone number format
 * E.164 format: +[country code][number] (e.g., +2250707123456 for Côte d'Ivoire)
 * @example isValidE164Phone("+2250707123456") // true
 * @example isValidE164Phone("0707123456") // false
 * @example isValidE164Phone("+33612345678") // true
 */
export function isValidE164Phone(phone: string): boolean {
  return /^\+[1-9]\d{10,14}$/.test(phone);
}

/**
 * Validate email format (RFC 5322 basic validation)
 * @example isValidEmail("user@example.com") // true
 * @example isValidEmail("invalid.email") // false
 */
export function isValidEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/**
 * Validate slug format (lowercase alphanumeric + hyphens)
 * @example isValidSlug("my-event-slug") // true
 * @example isValidSlug("My Event Slug") // false
 * @example isValidSlug("my_event_slug") // false
 */
export function isValidSlug(slug: string): boolean {
  return /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug);
}
