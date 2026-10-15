<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { gamesApi } from '@/api/games';
import { useAsyncState } from '@/composables/useAsyncState';
import { DESKTOP_QUERY, useMediaQuery } from '@/composables/useMediaQuery';
import { useGameStore } from '@/stores/game';
import ErrorPanel from '@/components/ui/ErrorPanel.vue';
import ResultMap, { type ResultPin } from '@/components/maps/ResultMap.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import type { ApiError } from '@/api/client';
import type { GameRound } from '@/types/game';
import { formatDistance, formatNumber, pluralYears } from '@/utils/format';
import { formatYear } from '@/utils/year';

const props = defineProps<{ gameId: string }>();

const router = useRouter();
const store = useGameStore();
const result = useAsyncState(gamesApi.result);
const restarting = ref(false);
const restartError = ref<ApiError | null>(null);
const highlighted = ref<number | null>(null);
const desktop = useMediaQuery(DESKTOP_QUERY);

const share = computed(() => {
    const data = result.data.value;
    return data ? Math.round((data.total_score / Math.max(1, data.max_score)) * 100) : 0;
});

const pins = computed<ResultPin[]>(() =>
    (result.data.value?.rounds ?? []).flatMap((round) =>
        round.question.latitude === null || round.question.longitude === null
            ? []
            : [
                  {
                      latitude: round.question.latitude,
                      longitude: round.question.longitude,
                      position: round.position,
                      // Grey: the round scored nothing because no point was picked in time.
                      timedOut: round.guess === null,
                      title: round.question.answer,
                      imageUrl: round.question.image_url,
                  },
              ],
    ),
);

const mapPadding = computed(() => (desktop.value ? { left: 400, right: 520 } : { top: 40, bottom: 40 }));

function roundSummary(round: GameRound): string {
    const year = round.question.year !== null ? formatYear(round.question.year) : null;
    if (round.guess === null || round.distance_meters === null) {
        return [year, 'время вышло'].filter(Boolean).join(' · ');
    }
    const verdict = round.year_difference === 0 ? 'год угадан' : pluralYears(round.year_difference ?? 0);
    return [year, `${formatDistance(round.distance_meters)}, ${verdict}`].filter(Boolean).join(' · ');
}

const roundItems = new Map<number, HTMLElement>();

function setRoundItem(position: number, element: unknown): void {
    if (element instanceof HTMLElement) roundItems.set(position, element);
    else roundItems.delete(position);
}

/** A pin hovered on the map highlights its row and brings it into view in the list. */
async function onPinHover(position: number | null): Promise<void> {
    highlighted.value = position;
    if (position === null) return;
    await nextTick();
    roundItems.get(position)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

onMounted(async () => {
    await result.run(props.gameId);
    // The game is still running: send the player back to it.
    if (result.error.value?.code === 'game_not_completed') {
        await router.replace({ name: 'game', params: { gameId: props.gameId } });
    }
});

async function playAgain(): Promise<void> {
    if (!result.data.value || restarting.value) return;
    restarting.value = true;
    restartError.value = null;
    try {
        const game = await store.start(result.data.value.quiz.id);
        await router.push({ name: 'game', params: { gameId: game.id } });
    } catch (e) {
        restartError.value = e as ApiError;
    } finally {
        restarting.value = false;
    }
}
</script>

<template>
    <NotFoundPage v-if="result.error.value?.status === 404" title="Игра не найдена" text="Ссылка устарела или игра началась в другом браузере." />

    <div v-else class="relative min-h-dvh bg-paper text-ink lg:h-dvh lg:overflow-hidden">
        <div class="relative h-[42dvh] lg:absolute lg:inset-0 lg:h-auto">
            <ResultMap
                class="absolute! inset-0"
                :pins="pins"
                :highlighted="highlighted"
                :padding="mapPadding"
                @hover="onPinHover"
            />
            <div class="sheet-frame z-[5] hidden lg:block" />
        </div>

        <div class="relative z-10 flex flex-col gap-6 p-4 lg:pointer-events-none lg:absolute lg:inset-0 lg:block lg:p-0 lg:*:pointer-events-auto">
            <ErrorPanel
                v-if="result.error.value && result.error.value.code !== 'game_not_completed'"
                class="lg:absolute lg:top-11 lg:left-11 lg:w-[400px]"
                :error="result.error.value"
                :retrying="result.loading.value"
                @retry="result.retry()"
            />

            <div v-else-if="!result.data.value" class="sheet skeleton h-56 lg:absolute lg:top-11 lg:left-11 lg:w-[340px]" aria-busy="true" />

            <template v-else>
                <section class="sheet px-[26px] py-5 lg:absolute lg:top-11 lg:left-11" aria-live="polite">
                    <h1 class="eyebrow">
                        <RouterLink :to="{ name: 'home' }" class="hover:text-stamp">Итоги</RouterLink> · {{ result.data.value.quiz.title }}
                    </h1>
                    <p class="year-stamp mt-1.5 text-[64px] leading-none sm:text-[80px]">{{ formatNumber(result.data.value.total_score) }}</p>
                    <p class="mt-1 text-[17px] text-ink-soft">из {{ formatNumber(result.data.value.max_score) }} · {{ share }}%</p>
                    <div
                        class="scale-bar relative mt-3 h-2.5 w-full max-w-[280px] lg:w-[280px]"
                        role="progressbar"
                        :aria-valuenow="share"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-label="`${share}% от максимума`"
                    >
                        <div class="absolute inset-y-0 right-0 bg-paper" :style="{ left: `${share}%` }" />
                    </div>
                </section>

                <aside class="sheet flex flex-col px-6 py-[22px] lg:absolute lg:top-10 lg:right-10 lg:bottom-10 lg:w-[440px]" aria-labelledby="rounds-heading">
                    <h2 id="rounds-heading" class="border-b-2 border-ink pb-2.5 text-[13px] font-semibold tracking-[0.14em] uppercase">Указатель точек</h2>
                    <ol class="-mx-3 min-h-0 flex-1 overflow-x-hidden overflow-y-auto">
                        <li
                            v-for="round in result.data.value.rounds"
                            :key="round.position"
                            :ref="(el) => setRoundItem(round.position, el)"
                            class="round-item grid grid-cols-[30px_minmax(0,1fr)_auto] items-center gap-3 border-b border-rule py-3"
                            :class="{ 'is-active': highlighted === round.position }"
                            :aria-current="highlighted === round.position ? 'true' : undefined"
                            @mouseenter="highlighted = round.position"
                            @mouseleave="highlighted = null"
                        >
                            <span
                                class="grid size-[26px] place-items-center rounded-full text-[13px] font-bold text-white"
                                :class="round.guess ? 'bg-stamp' : 'bg-muted-pin'"
                            >{{ round.position }}</span>
                            <span class="min-w-0">
                                <b class="block font-serif text-lg leading-[1.2] font-semibold">{{ round.question.answer }}</b>
                                <span class="text-sm text-ink-soft">{{ roundSummary(round) }}</span>
                            </span>
                            <span class="text-xl font-semibold tabular-nums">
                                {{ round.guess ? `+${formatNumber(round.score.total ?? 0)}` : '—' }}
                            </span>
                        </li>
                    </ol>
                    <div class="mt-5 flex flex-col gap-2.5">
                        <ErrorPanel v-if="restartError" :error="restartError" @retry="playAgain" />
                        <button
                            type="button"
                            class="btn-primary"
                            :disabled="restarting"
                            @click="playAgain"
                        >
                            {{ restarting ? 'Запускаем…' : 'Сыграть ещё раз' }}
                        </button>
                        <RouterLink :to="{ name: 'home' }" class="btn-secondary">Другие подборки</RouterLink>
                    </div>
                </aside>
            </template>
        </div>
    </div>
</template>
