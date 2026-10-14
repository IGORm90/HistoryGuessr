<script setup lang="ts">
import { computed } from 'vue';
import { formatClock } from '@/utils/format';

/**
 * Round timer in the header. Counts down `remainingMs` while guessing (the store ticks it);
 * on the reveal it shows the time the answer took, or that the time ran out.
 */
const props = defineProps<{ remainingMs: number | null; spentMs: number | null; timedOut: boolean }>();

const URGENT_MS = 15_000;

const urgent = computed(() => props.timedOut || (props.remainingMs !== null && props.remainingMs <= URGENT_MS));

const label = computed(() => {
    if (props.timedOut) return 'Время вышло';
    // Round up so the clock starts at the full limit and reads 0:00 only when time is really over.
    if (props.remainingMs !== null) return formatClock(Math.ceil(props.remainingMs / 1000) * 1000);
    if (props.spentMs !== null) return formatClock(props.spentMs);
    return '—';
});
</script>

<template>
    <p
        class="flex items-center gap-2.5 rounded-full px-[18px] py-2 transition-colors"
        :class="[
            urgent ? 'bg-stamp text-paper-raised' : 'bg-hay text-ink-deep',
            urgent && !timedOut && remainingMs ? 'animate-pulse motion-reduce:animate-none' : '',
        ]"
        role="timer"
        :aria-label="remainingMs !== null ? 'Осталось времени на ответ' : 'Время на ответ'"
        :data-urgent="urgent || undefined"
    >
        <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
            aria-hidden="true"
        >
            <circle cx="12" cy="13" r="8" />
            <path d="M12 9v4l2.5 2.5M9 2h6" />
        </svg>
        <span
            class="min-w-[3.5ch] leading-none font-extrabold tabular-nums"
            :class="timedOut ? 'text-base' : 'text-xl'"
        >{{ label }}</span>
    </p>
</template>
