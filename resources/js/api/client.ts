import type { ApiErrorBody } from '@/types/api';

const API_PREFIX = '/api/v1';

export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly code: string,
        message: string,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /** Network failures and 5xx responses are worth retrying; client errors are not. */
    get isRetryable(): boolean {
        return this.status === 0 || this.status >= 500 || this.status === 429;
    }
}

let csrfReady: Promise<void> | null = null;

function ensureCsrfCookie(): Promise<void> {
    csrfReady ??= fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).then((response) => {
        if (!response.ok) {
            csrfReady = null;
            throw new ApiError(response.status, 'csrf_failed', 'Не удалось инициализировать сессию');
        }
    });
    return csrfReady;
}

function readXsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export async function apiRequest<T>(method: Method, path: string, body?: unknown): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' };

    if (method !== 'GET') {
        await ensureCsrfCookie();
        const token = readXsrfToken();
        if (token) {
            headers['X-XSRF-TOKEN'] = token;
        }
    }
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    let response: Response;
    try {
        response = await fetch(API_PREFIX + path, {
            method,
            headers,
            credentials: 'same-origin',
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new ApiError(0, 'network_error', 'Нет соединения с сервером');
    }

    if (response.status === 419) {
        csrfReady = null;
    }

    const payload: unknown = response.status === 204 ? null : await response.json().catch(() => null);

    if (!response.ok) {
        const error = (payload ?? {}) as Partial<ApiErrorBody>;
        throw new ApiError(
            response.status,
            error.code ?? 'http_error',
            error.message ?? 'Что-то пошло не так',
            error.errors ?? {},
        );
    }

    return payload as T;
}

export const api = {
    get: <T>(path: string) => apiRequest<T>('GET', path),
    post: <T>(path: string, body?: unknown) => apiRequest<T>('POST', path, body ?? {}),
};
