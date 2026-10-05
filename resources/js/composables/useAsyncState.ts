import { ref, shallowRef } from 'vue';
import { ApiError } from '@/api/client';

/** Wraps an async loader with loading / error / retry state. */
export function useAsyncState<T, Args extends unknown[] = []>(loader: (...args: Args) => Promise<T>) {
    const data = shallowRef<T | null>(null);
    const error = shallowRef<ApiError | null>(null);
    const loading = ref(false);
    let lastArgs: Args | null = null;

    async function run(...args: Args): Promise<T | null> {
        lastArgs = args;
        loading.value = true;
        error.value = null;
        try {
            data.value = await loader(...args);
            return data.value;
        } catch (e) {
            error.value = e instanceof ApiError ? e : new ApiError(0, 'unknown', String(e));
            return null;
        } finally {
            loading.value = false;
        }
    }

    function retry(): Promise<T | null> {
        return lastArgs ? run(...lastArgs) : Promise.resolve(null);
    }

    return { data, error, loading, run, retry };
}
