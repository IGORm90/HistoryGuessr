import { onBeforeUnmount, ref, type Ref } from 'vue';

/** Whether a CSS media query matches, kept up to date. Without `matchMedia` (tests) it is `fallback`. */
export function useMediaQuery(query: string, fallback = true): Ref<boolean> {
    const list = typeof window.matchMedia === 'function' ? window.matchMedia(query) : null;
    const matches = ref(list?.matches ?? fallback);
    const update = (event: MediaQueryListEvent): void => {
        matches.value = event.matches;
    };
    list?.addEventListener?.('change', update);
    onBeforeUnmount(() => list?.removeEventListener?.('change', update));
    return matches;
}

/** Tailwind's `lg`: the desktop layout of the full-screen map pages. */
export const DESKTOP_QUERY = '(min-width: 1024px)';
