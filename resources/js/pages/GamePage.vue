<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
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
import GuessMap from '@/components/maps/GuessMap.vue';
import AnswerMap from '@/components/maps/AnswerMap.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';

const props = defineProps<{ gameId: string }>();

const router = useRouter();
const store = useGameStore();
const { game, guess, year, phase, canSubmit, submitting, advancing, error } = storeToRefs(store);

const mapExpanded = ref(false);
const loading = ref(false);
const notFound = computed(() => error.value?.status === 404);
const isLastQuestion = computed(() => game.value !== null && game.value.position >= game.value.question_count);

async function load(): Promise<void> {
    loading.value = true;
    await store.load(props.gameId);
    loading.value = false;
}

watch(() => props.gameId, load, { immediate: true });

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
    await store.advance();
    if (phase.value === 'guessing') {
        await nextTick();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function retry(): void {
    if (!game.value) void load();
    else if (phase.value === 'guessing') void submit();
    else void next();
}
</script>

<template>
    <NotFoundPage v-if="notFound" title="Игра не найдена" text="Ссылка устарела или игра началась в другом браузере." />

    <div v-else class="min-h-screen">
        <AppHeader>
            <RoundProgress
                v-if="game"
                class="ml-auto w-full max-w-md"
                :position="game.position"
                :total="game.question_count"
                :score="game.total_score"
                :answered="phase === 'revealed'"
            />
        </AppHeader>

        <main class="mx-auto max-w-6xl px-4 pb-12 sm:px-6">
            <div v-if="!game" class="py-10">
                <ErrorPanel
                    v-if="error"
                    :error="error"
                    :retrying="loading"
                    @retry="load"
                />
                <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(340px,1fr)]" aria-busy="true">
                    <div class="aspect-[3/2] animate-pulse rounded-md bg-rule/60" />
                    <div class="h-96 animate-pulse rounded-md bg-rule/40" />
                </div>
            </div>

            <div v-else-if="game.question" class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(340px,1fr)] lg:items-start">
                <div class="lg:sticky lg:top-4">
                    <QuestionImage :key="game.question.id" :image="game.question.image" />
                </div>

                <div class="space-y-5">
                    <QuestionPrompt :prompt="game.question.prompt" />

                    <form v-if="phase === 'guessing'" class="space-y-5" @submit.prevent="submit">
                        <div
                            :class="mapExpanded
                                ? 'fixed inset-0 z-[1000] flex flex-col bg-paper'
                                : 'relative h-64 overflow-hidden rounded-md border border-rule sm:h-80'"
                        >
                            <div v-if="mapExpanded" class="flex items-center justify-between gap-3 px-4 py-3">
                                <p class="text-sm text-ink-soft">{{ guess ? 'Отметка стоит. Можно передвинуть.' : 'Нажмите на место съёмки' }}</p>
                                <button type="button" class="btn-primary py-2" @click="mapExpanded = false">Готово</button>
                            </div>
                            <div class="min-h-0 flex-1" :class="mapExpanded ? '' : 'h-full'">
                                <GuessMap v-model="guess" :disabled="submitting" />
                            </div>
                            <button
                                v-if="!mapExpanded"
                                type="button"
                                class="absolute right-2 bottom-8 z-[500] rounded-md bg-paper-raised/95 px-3 py-2 text-sm font-medium shadow lg:hidden"
                                @click="mapExpanded = true"
                            >
                                Развернуть карту
                            </button>
                        </div>
                        <p class="-mt-3 text-sm text-ink-soft">
                            {{ guess ? 'Место отмечено.' : 'Отметьте место на карте.' }}
                        </p>

                        <YearInput v-model="year" :disabled="submitting" />

                        <ErrorPanel
                            v-if="error"
                            :error="error"
                            :retrying="submitting"
                            @retry="retry"
                        />

                        <button type="submit" class="btn-primary w-full" :disabled="!canSubmit">
                            {{ submitting ? 'Проверяем…' : 'Ответить' }}
                        </button>
                    </form>

                    <div v-else-if="phase === 'revealed' && game.answer" class="space-y-5">
                        <div class="h-64 overflow-hidden rounded-md border border-rule sm:h-72">
                            <AnswerMap :guess="game.answer.guess" :correct="game.answer.correct" />
                        </div>
                        <ScoreBreakdown :answer="game.answer" />
                        <ErrorPanel
                            v-if="error"
                            :error="error"
                            :retrying="advancing"
                            @retry="retry"
                        />
                        <button
                            type="button"
                            class="btn-primary w-full"
                            :disabled="advancing"
                            @click="next"
                        >
                            {{ advancing ? 'Загружаем…' : isLastQuestion ? 'Посмотреть итог' : 'Следующий вопрос' }}
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
