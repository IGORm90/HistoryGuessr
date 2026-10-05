import { describe, expect, it, vi } from 'vitest';
import { ApiError } from '@/api/client';
import { useAsyncState } from '@/composables/useAsyncState';

describe('useAsyncState', () => {
    it('stores data on success', async () => {
        const state = useAsyncState(async (n: number) => n * 2);
        await state.run(21);
        expect(state.data.value).toBe(42);
        expect(state.error.value).toBeNull();
        expect(state.loading.value).toBe(false);
    });

    it('captures ApiError and retries with the same arguments', async () => {
        const loader = vi
            .fn<(id: string) => Promise<string>>()
            .mockRejectedValueOnce(new ApiError(503, 'unavailable', 'down'))
            .mockResolvedValueOnce('ok');
        const state = useAsyncState(loader);

        await state.run('abc');
        expect(state.error.value?.isRetryable).toBe(true);

        await state.retry();
        expect(loader).toHaveBeenLastCalledWith('abc');
        expect(state.data.value).toBe('ok');
        expect(state.error.value).toBeNull();
    });
});
