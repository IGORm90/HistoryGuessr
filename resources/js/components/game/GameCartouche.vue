<script setup lang="ts">
import { RouterLink } from 'vue-router';
import { formatNumber } from '@/utils/format';
import RoundTimer from './RoundTimer.vue';

/**
 * The title block of the game sheet: logo, «sheet N of M», score and the round timer.
 * `compact` is the one-line strip of the phone layout.
 */
defineProps<{
    compact: boolean;
    quizTitle: string;
    position: number;
    total: number;
    score: number;
    remainingMs: number | null;
    spentMs: number | null;
    timedOut: boolean;
}>();
</script>

<template>
    <header
        v-if="compact"
        class="flex items-center justify-between gap-3 border border-ink bg-paper px-3 py-2 text-sm font-semibold tracking-[0.1em] uppercase"
    >
        <span>Лист {{ position }} / {{ total }}</span>
        <span class="tabular-nums">{{ formatNumber(score) }} очк.</span>
        <RoundTimer
            class="text-base!"
            :remaining-ms="remainingMs"
            :spent-ms="spentMs"
            :timed-out="timedOut"
        />
    </header>

    <header v-else class="sheet flex items-center gap-6 px-5 py-3.5">
        <div class="min-w-0">
            <RouterLink :to="{ name: 'home' }" class="font-serif text-[22px] leading-none font-semibold hover:text-stamp">HistoryGuessr</RouterLink>
            <p class="eyebrow mt-1 max-w-[340px] truncate">Лист {{ position }} из {{ total }} · {{ quizTitle }}</p>
        </div>
        <div class="w-px self-stretch bg-ink/30" />
        <div class="text-center">
            <p class="text-[11px] tracking-[0.14em] text-ink-soft uppercase">Очки</p>
            <p class="text-2xl leading-none font-semibold tabular-nums">{{ formatNumber(score) }}</p>
        </div>
        <div class="min-w-[4.5rem] text-center">
            <p class="text-[11px] tracking-[0.14em] text-stamp uppercase">Время</p>
            <RoundTimer :remaining-ms="remainingMs" :spent-ms="spentMs" :timed-out="timedOut" />
        </div>
    </header>
</template>
