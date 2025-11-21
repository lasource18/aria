/**
 * Format XOF currency amount with space thousands separator
 * @example formatXOF(5000) // "5 000 XOF"
 * @example formatXOF(1500000) // "1 500 000 XOF"
 * @example formatXOF(0) // "0 XOF"
 */
export function formatXOF(amount: number): string {
  const formatted = Math.round(amount)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  return `${formatted} XOF`;
}

/**
 * Parse XOF formatted string to number
 * @example parseXOF("5 000 XOF") // 5000
 * @example parseXOF("1 500 000 XOF") // 1500000
 * @example parseXOF("0 XOF") // 0
 */
export function parseXOF(formatted: string): number {
  const cleaned = formatted.replace(/[^\d]/g, '');
  return parseInt(cleaned, 10) || 0;
}
