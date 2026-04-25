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

export function formatTimeAgo(dateStr: string): string {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMin = Math.floor(diffMs / 60000);

    if (diffMin < 1) {
        return 'ahora mismo';
    }

    if (diffMin < 60) {
        return diffMin === 1 ? 'hace 1 minuto' : `hace ${diffMin} minutos`;
    }

    const diffHours = Math.floor(diffMin / 60);

    if (diffHours < 24) {
        return diffHours === 1 ? 'hace 1 hora' : `hace ${diffHours} horas`;
    }

    const startOfToday = new Date(now);
    startOfToday.setHours(0, 0, 0, 0);
    const startOfDate = new Date(date);
    startOfDate.setHours(0, 0, 0, 0);
    const dayDiff = Math.floor((startOfToday.getTime() - startOfDate.getTime()) / (1000 * 60 * 60 * 24));

    if (dayDiff === 0) {
        return 'hoy';
    }

    if (dayDiff === 1) {
        return 'ayer';
    }

    if (dayDiff < 7) {
        return `hace ${dayDiff} días`;
    }

    if (dayDiff < 30) {
        const weeks = Math.floor(dayDiff / 7);
        return weeks === 1 ? 'hace 1 semana' : `hace ${weeks} semanas`;
    }

    return date.toLocaleDateString('es', { day: 'numeric', month: 'long', year: 'numeric' });
}

export function formatEventTime(minute: number, second: number): string {
    return `${minute}:${String(second).padStart(2, '0')}`;
}

export function buildShareUrl(path: string): string {
    if (typeof window === 'undefined') {
        return path;
    }

    return `${window.location.origin}${path}`;
}

export function buildCanonicalUrl(appUrl: string, path: string): string {
    const base = appUrl.replace(/\/$/, '');
    const suffix = path.startsWith('/') ? path : `/${path}`;

    return `${base}${suffix}`;
}

export function truncateForMeta(value: string | null | undefined, max = 160): string {
    const trimmed = (value ?? '').replace(/\s+/g, ' ').trim();

    if (trimmed.length <= max) {
        return trimmed;
    }

    return `${trimmed.slice(0, max - 1).trimEnd()}…`;
}

export function getCsrfToken(): string {
    return decodeURIComponent(
        document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '',
    );
}

export function stripDiacritics(value: string): string {
    return value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

export function teamInitials(name: string): string {
    return name
        .split(/\s+/)
        .map(w => w.charAt(0).toUpperCase())
        .join('')
        .slice(0, 2) || '?';
}

function relativeLuminance(hex: string): number {
    const m = hex.replace('#', '').match(/.{2}/g);
    if (!m) return 0;
    const [r, g, b] = m.map(h => {
        const v = parseInt(h, 16) / 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrastTextColor(bgColor: string | null | undefined): string {
    if (!bgColor) return '#fafafa';
    return relativeLuminance(bgColor) > 0.55 ? '#18181b' : '#fafafa';
}
