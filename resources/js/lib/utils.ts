import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

const APP_TIMEZONE = 'America/Bogota';

export function formatDate(dateStr: string, options: Intl.DateTimeFormatOptions = {}): string {
    return new Date(dateStr).toLocaleDateString('es', { timeZone: APP_TIMEZONE, ...options });
}

export function formatTime(dateStr: string, options: Intl.DateTimeFormatOptions = {}): string {
    return new Date(dateStr).toLocaleTimeString('es', { timeZone: APP_TIMEZONE, hour: '2-digit', minute: '2-digit', ...options });
}

export function formatDateTime(dateStr: string): string {
    return new Date(dateStr).toLocaleString('es', { timeZone: APP_TIMEZONE });
}

export function formatEventTime(minute: number, second: number): string {
    return `${minute}:${String(second).padStart(2, '0')}`;
}
