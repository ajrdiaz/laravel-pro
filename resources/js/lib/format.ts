/**
 * Format a `YYYY-MM-DD` date as a long Spanish date, e.g. "19 de octubre de 2026".
 *
 * Parsed at noon so the day doesn't shift with the viewer's timezone.
 */
export function formatDate(date: string): string {
    return new Intl.DateTimeFormat('es-PE', { dateStyle: 'long' }).format(
        new Date(`${date}T12:00:00`),
    );
}
