<script setup lang="ts">
import { computed } from 'vue';

/**
 * A small scale bar under the correct year: the correct year (stamp red) and the player's guess
 * (teal) as ticks on a span of whole half-centuries around both.
 */
const props = defineProps<{ correct: number; guess: number | null }>();

const SEGMENT = 50;

const range = computed(() => {
    const years = props.guess === null ? [props.correct] : [props.correct, props.guess];
    const low = Math.min(...years);
    const high = Math.max(...years);
    const margin = Math.max(20, (high - low) * 0.15);
    const from = Math.floor((low - margin) / SEGMENT) * SEGMENT;
    const to = Math.ceil((high + margin) / SEGMENT) * SEGMENT;
    return { from, to };
});

function percent(year: number): string {
    const { from, to } = range.value;
    return `${((year - from) / (to - from)) * 100}%`;
}

// The bar pattern is two segments wide (ink + paper).
const step = computed(() => `${((2 * SEGMENT) / (range.value.to - range.value.from)) * 100}%`);

function label(year: number): string {
    return year < 0 ? `${-year} до н. э.` : String(year);
}
</script>

<template>
    <div class="relative h-[34px]" aria-hidden="true">
        <div class="scale-bar absolute inset-x-0 top-2 h-2" :style="{ '--scale-step': step }" />
        <span class="absolute top-0 h-6 w-[3px] -translate-x-1/2 bg-stamp" :style="{ left: percent(correct) }" data-test="correct-tick" />
        <span
            v-if="guess !== null"
            class="absolute top-0 h-6 w-[3px] -translate-x-1/2 bg-guess"
            :style="{ left: percent(guess) }"
            data-test="guess-tick"
        />
        <span class="absolute top-5 left-0 text-[11px]">{{ label(range.from) }}</span>
        <span class="absolute top-5 right-0 text-[11px]">{{ label(range.to) }}</span>
    </div>
</template>
