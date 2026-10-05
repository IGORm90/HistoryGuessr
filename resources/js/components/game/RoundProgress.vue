<script setup lang="ts">
import { formatNumber } from '@/utils/format';

defineProps<{ position: number; total: number; score: number; answered: boolean }>();
</script>

<template>
    <div class="flex items-center gap-4">
        <ol class="flex gap-1" :aria-label="`Вопрос ${position} из ${total}`">
            <li
                v-for="step in total"
                :key="step"
                class="h-1.5 w-6 rounded-full sm:w-8"
                :class="step < position || (step === position && answered) ? 'bg-ink' : step === position ? 'bg-cyan' : 'bg-rule'"
            />
        </ol>
        <p class="text-sm text-ink-soft">
            <span class="sr-only">Вопрос </span>{{ position }}/{{ total }}
        </p>
        <p class="ml-auto text-sm">
            <span class="text-ink-soft"><span class="hidden sm:inline">Очки </span><span class="sr-only sm:hidden">Очки </span></span>
            <span class="font-semibold tabular-nums">{{ formatNumber(score) }}</span>
        </p>
    </div>
</template>
