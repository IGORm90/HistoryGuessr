<script setup lang="ts">
import { computed } from 'vue';
import type { AnswerReveal } from '@/types/game';
import { formatDistance, formatNumber, pluralYears } from '@/utils/format';
import { formatYear } from '@/utils/year';
import YearStamp from './YearStamp.vue';

const props = defineProps<{ answer: AnswerReveal }>();

const yearVerdict = computed(() =>
    props.answer.year_difference === 0 ? 'точно в год' : `ошибка ${pluralYears(props.answer.year_difference)}`,
);
</script>

<template>
    <section aria-live="polite" class="space-y-5">
        <div class="flex items-end justify-between gap-4 border-b border-rule pb-4">
            <div>
                <p class="text-sm text-ink-soft">{{ answer.correct.location_name }}</p>
                <p class="text-6xl leading-none text-stamp sm:text-7xl">
                    <YearStamp :year="answer.correct.year" />
                </p>
            </div>
            <p class="text-right">
                <span class="block text-sm text-ink-soft">за вопрос</span>
                <span class="text-3xl font-semibold tabular-nums">+{{ formatNumber(answer.score.total) }}</span>
            </p>
        </div>

        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-ink-soft">Место</dt>
                <dd class="mt-0.5 text-base">{{ formatDistance(answer.distance_meters) }} от цели</dd>
                <dd class="font-semibold tabular-nums">+{{ formatNumber(answer.score.geo) }}</dd>
            </div>
            <div>
                <dt class="text-ink-soft">Год</dt>
                <dd class="mt-0.5 text-base">{{ formatYear(answer.guess.year) }}, {{ yearVerdict }}</dd>
                <dd class="font-semibold tabular-nums">+{{ formatNumber(answer.score.year) }}</dd>
            </div>
        </dl>

        <p class="font-serif text-base leading-relaxed text-ink" style="font-variation-settings: 'opsz' 14">
            {{ answer.reveal_text }}
        </p>

        <p class="text-xs text-ink-soft">
            Фото: {{ answer.attribution.author ?? 'автор неизвестен' }}, {{ answer.attribution.license ?? 'лицензия не указана' }}.
            <a
                v-if="answer.attribution.source_url"
                :href="answer.attribution.source_url"
                target="_blank"
                rel="noopener noreferrer"
                class="underline hover:text-ink"
            >Источник</a>
            <span v-if="answer.attribution.text" class="block">{{ answer.attribution.text }}</span>
        </p>
    </section>
</template>
