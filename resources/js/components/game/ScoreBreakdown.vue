<script setup lang="ts">
import { computed } from 'vue';
import type { AnswerReveal } from '@/types/game';
import { formatClock, formatDistance, formatNumber, pluralYears } from '@/utils/format';
import { formatYear } from '@/utils/year';
import YearStamp from './YearStamp.vue';
import YearScale from './YearScale.vue';

/** The reveal panel content: the correct answer, the year, points per part and the story of the photo. */
const props = defineProps<{ answer: AnswerReveal; position: number; total: number }>();

const yearVerdict = computed(() =>
    props.answer.year_difference === 0 ? 'точно в год' : `ошибка ${pluralYears(props.answer.year_difference ?? 0)}`,
);
</script>

<template>
    <section aria-live="polite" class="flex flex-col gap-[18px]">
        <p class="eyebrow flex justify-between gap-3">
            <span>Лист {{ position }} из {{ total }}</span>
            <span v-if="answer.duration_ms !== null">за {{ formatClock(answer.duration_ms) }}</span>
        </p>

        <div>
            <h2 class="font-serif text-[28px] leading-[1.1] font-semibold lg:text-[34px]">{{ answer.correct.answer }}</h2>
            <p class="mt-1.5 text-[17px] text-ink-soft">{{ answer.correct.location_name }}</p>
        </div>

        <div class="flex flex-col gap-2 border-t border-ink pt-3.5">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <span class="text-[44px] leading-none text-stamp lg:text-[56px]"><YearStamp :year="answer.correct.year" /></span>
                <span v-if="answer.guess" class="text-[15px] text-ink-soft">
                    ваш ответ {{ formatYear(answer.guess.year) }} · {{ yearVerdict }}
                </span>
            </div>
            <YearScale :correct="answer.correct.year" :guess="answer.guess?.year ?? null" />
        </div>

        <p v-if="!answer.guess" class="time-up-stamp self-start">
            Время вышло — 0 очков
        </p>
        <p v-else-if="answer.timed_out" class="time-up-stamp self-start text-xs">
            Время вышло — засчитан выбранный ответ
        </p>

        <dl class="grid grid-cols-3 border border-ink">
            <div class="border-r border-ink px-3 py-2.5">
                <dt class="text-xs tracking-[0.12em] text-ink-soft uppercase">Место</dt>
                <dd class="text-2xl font-semibold tabular-nums">+{{ formatNumber(answer.score.geo) }}</dd>
                <dd v-if="answer.guess && answer.distance_meters !== null" class="text-[13px] text-ink-soft">
                    {{ formatDistance(answer.distance_meters) }} от цели
                </dd>
            </div>
            <div class="border-r border-ink px-3 py-2.5">
                <dt class="text-xs tracking-[0.12em] text-ink-soft uppercase">Год</dt>
                <dd class="text-2xl font-semibold tabular-nums">+{{ formatNumber(answer.score.year) }}</dd>
            </div>
            <div class="bg-ink px-3 py-2.5 text-paper">
                <dt class="text-xs tracking-[0.12em] uppercase opacity-75">Итого</dt>
                <dd class="text-2xl font-semibold tabular-nums">+{{ formatNumber(answer.score.total) }}</dd>
            </div>
        </dl>

        <p class="font-serif text-[17px] leading-[1.55]">
            {{ answer.reveal_text }}
        </p>

        <p class="mt-auto text-[13px] text-ink-soft">
            Источник: {{ answer.attribution.author ?? 'автор неизвестен' }}, {{ answer.attribution.license ?? 'лицензия не указана' }}.
            <a
                v-if="answer.attribution.source_url"
                :href="answer.attribution.source_url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-stamp underline hover:text-ink"
            >Источник</a>
            <span v-if="answer.attribution.text" class="block">{{ answer.attribution.text }}</span>
        </p>
    </section>
</template>
