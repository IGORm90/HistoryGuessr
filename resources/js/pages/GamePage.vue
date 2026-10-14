<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useGameStore } from '@/stores/game';
import AppHeader from '@/components/ui/AppHeader.vue';
import ErrorPanel from '@/components/ui/ErrorPanel.vue';
import QuestionImage from '@/components/game/QuestionImage.vue';
import QuestionPrompt from '@/components/game/QuestionPrompt.vue';
import YearInput from '@/components/game/YearInput.vue';
import ScoreBreakdown from '@/components/game/ScoreBreakdown.vue';
import RoundProgress from '@/components/game/RoundProgress.vue';
import RoundTimer from '@/components/game/RoundTimer.vue';
import GuessMap from '@/components/maps/GuessMap.vue';
import AnswerMap from '@/components/maps/AnswerMap.vue';
import ExpandableMap from '@/components/maps/ExpandableMap.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import { formatCoordinates } from '@/utils/format';

const props = defineProps<{ gameId: string }>();

const router = useRouter();
const store = useGameStore();
const { game, guess, year, phase, canSubmit, submitting, advancing, error, remainingMs, timeUp, notice } = storeToRefs(store);

const mapExpanded = ref(false);
const answerMapExpanded = ref(false);
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
    if (current === 'completed') {
        await router.replace({ name: 'result', params: { gameId: props.gameId } });
    }
});

async function submit(): Promise<void> {
    mapExpanded.value = false;
    await store.submit();
}

async function next(): Promise<void> {
    answerMapExpanded.value = false;
    await store.advance();
    if (phase.value === 'guessing') {
        await nextTick();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
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

    <div v-else class="min-h-screen">
        <AppHeader>
            <template v-if="game" #center>
                <RoundTimer
                    :remaining-ms="phase === 'guessing' ? remainingMs : null"
                    :spent-ms="game.answer?.duration_ms ?? null"
                    :timed-out="timeUp || (game.answer?.timed_out ?? false)"
                />
            </template>
            <RoundProgress
                v-if="game"
                :position="game.position"
                :total="game.question_count"
                :score="game.total_score"
            />
        </AppHeader>

        <main class="mx-auto max-w-[1376px] px-4 pt-7 pb-12 sm:px-8">
            <div v-if="!game" class="py-4">
                <ErrorPanel
                    v-if="error"
                    :error="error"
                    :retrying="loading"
                    @retry="load"
                />
                <div v-else class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_400px]" aria-busy="true">
                    <div class="aspect-[3/2] animate-pulse rounded-[20px] bg-hay-pale" />
                    <div class="h-96 animate-pulse rounded-[20px] bg-hay-pale/60" />
                </div>
            </div>

            <template v-else-if="game.question">
                <div
                    v-if="notice === 'previous_round_expired'"
                    class="card mb-4 flex items-center gap-3 border-l-4 border-l-stamp py-3"
                    role="status"
                >
                    <p class="flex-1 font-semibold text-stamp">Время на прошлый вопрос вышло — 0 очков</p>
                    <button
                        type="button"
                        class="grid size-9 shrink-0 place-items-center rounded-full text-ink-soft hover:bg-paper hover:text-ink"
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

                <div class="mb-4 space-y-1">
                    <p class="text-[0.8125rem] font-bold tracking-[0.12em] text-umber uppercase">Подборка «{{ game.quiz.title }}»</p>
                    <QuestionPrompt />
                </div>

                <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_400px]">
                    <div>
                        <QuestionImage :key="game.question.id" :image="game.question.image" fill />
                    </div>

                    <aside>
                        <form v-if="phase === 'guessing'" class="space-y-4" @submit.prevent="submit">
                            <section class="card" aria-labelledby="place-heading">
                                <div class="mb-3 flex items-baseline justify-between gap-3">
                                    <h2 id="place-heading" class="text-base font-extrabold">Место</h2>
                                    <span class="text-xs text-ink-soft tabular-nums">{{ guess ? formatCoordinates(guess) : 'не отмечено' }}</span>
                                </div>
                                <ExpandableMap v-model:expanded="mapExpanded">
                                    <GuessMap v-model="guess" :disabled="formLocked" />
                                </ExpandableMap>
                            </section>

                            <section class="card">
                                <YearInput v-model="year" :disabled="formLocked" />
                            </section>

                            <ErrorPanel
                                v-if="error"
                                :error="error"
                                :retrying="submitting || loading"
                                @retry="retry"
                            />

                            <button type="submit" class="btn-primary min-h-14 w-full text-[1.0625rem]" :disabled="!canSubmit">
                                {{ submitLabel }}
                            </button>
                        </form>

                        <div v-else-if="phase === 'revealed' && game.answer" class="space-y-4">
                            <section class="card space-y-4">
                                <ExpandableMap v-model:expanded="answerMapExpanded">
                                    <AnswerMap :guess="game.answer.guess" :correct="game.answer.correct" />
                                </ExpandableMap>
                                <ScoreBreakdown :answer="game.answer" />
                            </section>
                            <ErrorPanel
                                v-if="error"
                                :error="error"
                                :retrying="advancing"
                                @retry="retry"
                            />
                            <button
                                type="button"
                                class="btn-primary min-h-14 w-full text-[1.0625rem]"
                                :disabled="advancing"
                                @click="next"
                            >
                                {{ advancing ? 'Загружаем…' : isLastQuestion ? 'Посмотреть итог' : 'Следующий вопрос' }}
                            </button>
                        </div>
                    </aside>
                </div>
            </template>
        </main>
    </div>
</template>
