<script setup lang="ts">
import { computed } from 'vue';
import { formatClock } from '@/utils/format';

/**
 * Round timer in the game cartouche. Counts down `remainingMs` while guessing (the store ticks it);
 * on the reveal it shows the time the answer took, or that the time ran out. Pulses in the last 15 s.
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
    <span
        class="inline-block min-w-[3.5ch] leading-none font-semibold whitespace-nowrap tabular-nums"
        :class="[
            remainingMs !== null || timedOut ? 'text-stamp' : 'text-ink',
            timedOut ? 'text-base tracking-[0.08em] uppercase' : 'text-2xl',
            urgent && !timedOut && remainingMs ? 'animate-pulse motion-reduce:animate-none' : '',
        ]"
        role="timer"
        :aria-label="remainingMs !== null ? 'Осталось времени на ответ' : 'Время на ответ'"
        :data-urgent="urgent || undefined"
    >{{ label }}</span>
</template>
