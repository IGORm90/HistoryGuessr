<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Map frame with a zoom-out button inside the map. Expanded, the map grows to twice its size with
 * the same aspect ratio, anchored at its top-right corner, and floats over the page. The same map
 * element (and Leaflet instance) only changes size; `useLeafletMap` resizes it via ResizeObserver.
 * Where there is no room to grow sideways (phones), it doubles its height instead.
 */
const expanded = defineModel<boolean>('expanded', { default: false });

const SCALE = 2;
const PAGE_GUTTER = 16;

const placeholder = ref<HTMLElement | null>(null);
const panel = ref<HTMLElement | null>(null);
// Explicit pixel size while expanded or animating; null means «fill the placeholder».
const size = ref<{ width: number; height: number } | null>(null);
const raised = ref(false);

const panelStyle = computed(() => (size.value ? { width: `${size.value.width}px`, height: `${size.value.height}px` } : {}));

function placeholderRect(): DOMRect | null {
    return placeholder.value?.getBoundingClientRect() ?? null;
}

function targetSize(rect: DOMRect): { width: number; height: number } {
    const room = rect.right - PAGE_GUTTER;
    if (room < rect.width * 1.25) {
        return { width: rect.width, height: rect.height * SCALE };
    }
    const width = Math.min(rect.width * SCALE, room);
    return { width, height: (width * rect.height) / rect.width };
}

function nextFrame(): Promise<void> {
    return new Promise((resolve) => requestAnimationFrame(() => resolve()));
}

async function grow(): Promise<void> {
    const rect = placeholderRect();
    if (!rect) return;
    document.addEventListener('pointerdown', onOutsidePointer, true);
    window.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', onResize);
    raised.value = true;
    // Pin the current size first so the browser has a start value to animate from.
    size.value = { width: rect.width, height: rect.height };
    await nextFrame();
    await nextFrame();
    if (expanded.value) {
        size.value = targetSize(rect);
    }
}

function shrink(): void {
    removeListeners();
    const rect = placeholderRect();
    if (!rect || !size.value) {
        size.value = null;
        raised.value = false;
        return;
    }
    size.value = { width: rect.width, height: rect.height };
}

function onTransitionEnd(event: TransitionEvent): void {
    if (event.target === panel.value && !expanded.value && event.propertyName === 'height') {
        size.value = null;
        raised.value = false;
    }
}

function onOutsidePointer(event: PointerEvent): void {
    if (panel.value && !panel.value.contains(event.target as Node)) {
        expanded.value = false;
    }
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        expanded.value = false;
    }
}

function onResize(): void {
    const rect = placeholderRect();
    if (rect && expanded.value) {
        size.value = targetSize(rect);
    }
}

function removeListeners(): void {
    document.removeEventListener('pointerdown', onOutsidePointer, true);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onResize);
}

watch(expanded, async (open) => {
    await nextTick();
    if (open) await grow();
    else shrink();
});

onBeforeUnmount(removeListeners);
</script>

<template>
    <div ref="placeholder" class="relative h-64 sm:h-[260px]">
        <div
            ref="panel"
            class="absolute top-0 right-0 h-full w-full overflow-hidden rounded-[14px] transition-[width,height,box-shadow] duration-300 ease-out motion-reduce:transition-none"
            :class="raised ? 'z-[1500] shadow-[0_18px_48px_rgb(43_29_20/0.35)] ring-1 ring-rule' : ''"
            :style="panelStyle"
            @transitionend="onTransitionEnd"
        >
            <slot />
            <button
                type="button"
                class="absolute top-2.5 right-2.5 z-[1001] flex size-[38px] items-center justify-center rounded-xl border border-rule bg-paper-raised text-ink shadow-[0_1px_2px_rgb(59_42_30/0.12)] transition-colors hover:bg-paper"
                :aria-label="expanded ? 'Уменьшить карту' : 'Увеличить карту'"
                :aria-expanded="expanded"
                @click="expanded = !expanded"
            >
                <svg
                    v-if="!expanded"
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7" />
                </svg>
                <svg
                    v-else
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7" />
                </svg>
            </button>
        </div>
    </div>
</template>
