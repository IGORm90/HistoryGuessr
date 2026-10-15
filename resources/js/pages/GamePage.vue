<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useGameStore } from '@/stores/game';
import { DESKTOP_QUERY, useMediaQuery } from '@/composables/useMediaQuery';
import ErrorPanel from '@/components/ui/ErrorPanel.vue';
import GameCartouche from '@/components/game/GameCartouche.vue';
import PinnedPhoto from '@/components/game/PinnedPhoto.vue';
import PhotoLightbox from '@/components/game/PhotoLightbox.vue';
import MapLegend from '@/components/game/MapLegend.vue';
import YearInput from '@/components/game/YearInput.vue';
import ScoreBreakdown from '@/components/game/ScoreBreakdown.vue';
import GameMap, { type MapReveal } from '@/components/maps/GameMap.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import { gameTitle } from '@/utils/format';

const props = defineProps<{ gameId: string }>();

const router = useRouter();
const store = useGameStore();
const { game, guess, year, phase, canSubmit, submitting, advancing, error, remainingMs, timeUp, notice } = storeToRefs(store);
const desktop = useMediaQuery(DESKTOP_QUERY);
const wide = useMediaQuery('(min-width: 1280px)');

const photoExpanded = ref(false);
const loading = ref(false);
const notFound = computed(() => error.value?.status === 404);
const formLocked = computed(() => submitting.value || timeUp.value);
const submitLabel = computed(() => {
    if (submitting.value) return 'Проверяем…';
    if (timeUp.value) return 'Показываем ответ…';
    if (!guess.value) return 'Отметьте место на карте';
    if (year.value === null) return 'Укажите год';
    return 'Ответить';
});

const isLastQuestion = computed(() => game.value !== null && game.value.position >= game.value.question_count);
const nextLabel = computed(() => (advancing.value ? 'Загружаем…' : isLastQuestion.value ? 'Посмотреть итог' : 'Следующий лист →'));
const revealed = computed(() => (phase.value === 'revealed' && game.value?.answer ? game.value.answer : null));

const mapReveal = computed<MapReveal | null>(() => {
    const answer = revealed.value;
    return answer
        ? {
              guess: answer.guess,
              correct: answer.correct,
              distanceMeters: answer.distance_meters,
              locationName: answer.correct.location_name,
          }
        : null;
});

// Keep the answer clear of the photo and the reveal panel (desktop) or the bottom sheet (phones).
const mapPadding = computed(() =>
    desktop.value
        ? { top: 90, bottom: 120, left: 300, right: wide.value ? 540 : 460 }
        : { top: 200, bottom: Math.round(window.innerHeight * 0.6) + 20, left: 40, right: 40 },
);

async function load(): Promise<void> {
    loading.value = true;
    await store.load(props.gameId);
    loading.value = false;
}

watch(() => props.gameId, load, { immediate: true });

// Timers are throttled in background tabs; catch up as soon as the player is back.
function onVisibilityChange(): void {
    if (document.visibilityState === 'visible') store.syncClock();
}

// The "previous round expired" notice hides by itself after a while.
const NOTICE_MS = 6000;
let noticeTimer: ReturnType<typeof setTimeout> | undefined;

watch(notice, (current) => {
    clearTimeout(noticeTimer);
    if (current) noticeTimer = setTimeout(() => store.dismissNotice(), NOTICE_MS);
});

onMounted(() => document.addEventListener('visibilitychange', onVisibilityChange));
onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    clearTimeout(noticeTimer);
});

watch(phase, async (current) => {
    photoExpanded.value = false;
    if (current === 'completed') {
        await router.replace({ name: 'result', params: { gameId: props.gameId } });
    }
});

async function submit(): Promise<void> {
    await store.submit();
}

async function next(): Promise<void> {
    await store.advance();
}

function retry(): void {
    if (!game.value) void load();
    else if (timeUp.value) void store.submitOnTimeout();
    else if (phase.value === 'guessing') void submit();
    else void next();
}
</script>

<template>
    <NotFoundPage v-if="notFound" title="Игра не найдена" text="Ссылка устарела или игра началась в другом браузере." />

    <div v-else class="relative h-dvh overflow-hidden bg-paper text-ink">
        <GameMap
            v-model="guess"
            class="map-fullscreen absolute! inset-0"
            :disabled="formLocked || phase !== 'guessing'"
            :reveal="mapReveal"
            :padding="mapPadding"
        />
        <div class="sheet-frame z-[5] hidden lg:block" />

        <!-- Above the map controls (MapLibre gives them z-index 2). -->
        <div class="pointer-events-none absolute inset-0 z-10 *:pointer-events-auto">
            <!-- On desktop the reveal panel takes over the cartouche's corner and repeats its data. -->
            <GameCartouche
                v-if="game && (!desktop || !revealed)"
                class="absolute top-[calc(env(safe-area-inset-top)+8px)] right-3 left-3 lg:top-10 lg:right-10 lg:left-auto"
                :compact="!desktop"
                :quiz-title="gameTitle(game.quiz)"
                :position="game.position"
                :total="game.question_count"
                :score="game.total_score"
                :remaining-ms="phase === 'guessing' ? remainingMs : null"
                :spent-ms="game.answer?.duration_ms ?? null"
                :timed-out="timeUp || (game.answer?.timed_out ?? false)"
            />

            <!-- Loading and load errors: the map is already there, the sheet is not. -->
            <template v-if="!game">
                <div v-if="error" class="sheet absolute top-1/2 left-1/2 w-[min(440px,calc(100%-32px))] -translate-1/2 p-5">
                    <ErrorPanel :error="error" :retrying="loading" @retry="load" />
                </div>
                <div
                    v-else
                    class="polaroid absolute top-[calc(env(safe-area-inset-top)+60px)] left-[18px] w-[130px] -rotate-2 lg:top-11 lg:left-11 lg:w-[380px]"
                    aria-busy="true"
                >
                    <div class="skeleton h-[150px] lg:h-[440px]" />
                </div>
            </template>

            <template v-else-if="game.question">
                <PinnedPhoto
                    :key="game.question.id"
                    class="absolute top-[calc(env(safe-area-inset-top)+60px)] left-[18px] lg:top-11 lg:left-11"
                    :image="game.question.image"
                    :compact="revealed !== null"
                    @open="photoExpanded = true"
                />

                <div
                    v-if="notice === 'previous_round_expired'"
                    class="sheet absolute top-[calc(env(safe-area-inset-top)+60px)] right-3 left-[160px] flex items-center gap-3 px-4 py-2.5 lg:top-10 lg:right-auto lg:left-1/2 lg:-translate-x-1/2"
                    role="status"
                >
                    <p class="flex-1 font-semibold text-stamp">Время на прошлый вопрос вышло — 0 очков</p>
                    <button
                        type="button"
                        class="grid size-8 shrink-0 place-items-center text-ink-soft hover:text-stamp"
                        aria-label="Закрыть"
                        @click="store.dismissNotice()"
                    >
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.4"
                            stroke-linecap="round"
                            aria-hidden="true"
                        >
                            <path d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>

                <!-- Guessing: the year panel and the legend with the answer button. -->
                <form
                    v-if="phase === 'guessing'"
                    class="pointer-events-none! absolute right-3 bottom-[calc(env(safe-area-inset-bottom)+14px)] left-3 grid items-end gap-5 lg:right-11 lg:bottom-10 lg:left-11 lg:grid-cols-[minmax(0,1fr)_300px]"
                    @submit.prevent="submit"
                >
                    <template v-if="desktop">
                        <section class="panel pointer-events-auto px-[22px] pt-3.5 pb-3">
                            <YearInput v-model="year" layout="inline" :disabled="formLocked" />
                        </section>
                        <MapLegend class="pointer-events-auto">
                            <ErrorPanel
                                v-if="error"
                                :error="error"
                                :retrying="submitting || loading"
                                @retry="retry"
                            />
                            <button type="submit" class="btn-primary w-full px-3 text-base tracking-[0.1em] whitespace-nowrap" :disabled="!canSubmit">
                                {{ submitLabel }}
                            </button>
                        </MapLegend>
                    </template>
                    <section v-else class="pointer-events-auto flex flex-col gap-2.5 border border-ink bg-paper p-3.5">
                        <YearInput v-model="year" :disabled="formLocked" />
                        <ErrorPanel
                            v-if="error"
                            :error="error"
                            :retrying="submitting || loading"
                            @retry="retry"
                        />
                        <button type="submit" class="btn-primary min-h-[52px] w-full px-3 text-base tracking-[0.1em] whitespace-nowrap" :disabled="!canSubmit">
                            {{ submitLabel }}
                        </button>
                    </section>
                </form>

                <!-- Revealed: the answer panel on the right (desktop) or a bottom sheet (phones). -->
                <Transition
                    appear
                    enter-active-class="transition duration-[250ms] ease-out"
                    enter-from-class="translate-x-6 opacity-0 max-lg:translate-x-0 max-lg:translate-y-6"
                >
                    <aside
                        v-if="revealed"
                        :key="revealed.question_id"
                        class="sheet absolute right-3 bottom-[calc(env(safe-area-inset-bottom)+14px)] left-3 flex max-h-[60dvh] flex-col lg:top-10 lg:right-10 lg:bottom-10 lg:left-auto lg:max-h-none lg:w-[380px] xl:w-[460px]"
                    >
                        <div class="flex min-h-0 flex-1 flex-col overflow-y-auto px-5 pt-5 lg:px-7 lg:pt-[26px]">
                            <ScoreBreakdown
                                class="flex-1"
                                :answer="revealed"
                                :position="game.position"
                                :total="game.question_count"
                            />
                        </div>
                        <div class="flex flex-col gap-3 px-5 pt-4 pb-5 lg:px-7 lg:pb-[26px]">
                            <ErrorPanel
                                v-if="error"
                                :error="error"
                                :retrying="advancing"
                                @retry="retry"
                            />
                            <button
                                type="button"
                                class="btn-primary min-h-14 w-full"
                                :disabled="advancing"
                                @click="next"
                            >
                                {{ nextLabel }}
                            </button>
                        </div>
                    </aside>
                </Transition>
            </template>
        </div>

        <PhotoLightbox v-if="game?.question" v-model:open="photoExpanded" :image="game.question.image" />
    </div>
</template>
