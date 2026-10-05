/** Error envelope returned by every /api/v1 endpoint. */
export type ApiErrorBody = {
    message: string;
    code: string;
    errors?: Record<string, string[]>;
};
