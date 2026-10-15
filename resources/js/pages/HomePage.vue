<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import QuizList from '@/components/quiz/QuizList.vue';
import WorldBackdrop from '@/components/maps/WorldBackdrop.vue';
import ErrorPanel from '@/components/ui/ErrorPanel.vue';
import type { ApiError } from '@/api/client';
import { useGameStore } from '@/stores/game';
import { lastGame } from '@/utils/lastGame';
import { DESKTOP_QUERY, useMediaQuery } from '@/composables/useMediaQuery';

const router = useRouter();
const store = useGameStore();
const unfinished = ref(lastGame());
const starting = ref(false);
const startError = ref<ApiError | null>(null);
// On desktop the pins stay to the right of the hero card.
const desktop = useMediaQuery(DESKTOP_QUERY);

const steps = ['Рассмотрите изображение', 'Отметьте место на карте', 'Назовите год'];

// A game of random published questions, without a quiz.
async function play(): Promise<void> {
    if (starting.value) return;
    starting.value = true;
    startError.value = null;
    try {
        const game = await store.start();
        await router.push({ name: 'game', params: { gameId: game.id } });
    } catch (e) {
        startError.value = e as ApiError;
    } finally {
        starting.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-paper text-ink">
        <section class="relative overflow-hidden lg:min-h-[720px]">
            <WorldBackdrop class="absolute! inset-0" :padding="desktop ? { left: 660 } : {}" />
            <div class="sheet-frame z-[5]" />

            <header class="absolute z-10 top-7 right-7 left-7 flex items-center justify-between gap-4 lg:top-9 lg:right-10 lg:left-10">
                <RouterLink :to="{ name: 'home' }" class="bg-paper px-3 py-1 font-serif text-[22px] font-semibold sm:text-[26px]">HistoryGuessr</RouterLink>
                <RouterLink :to="{ name: 'quizzes' }" class="bg-paper px-3 py-1.5 text-[17px] font-semibold tracking-[0.12em] uppercase hover:text-stamp">
                    Подборки
                </RouterLink>
            </header>

            <div class="relative z-10 px-7 pt-28 pb-12 lg:px-14 lg:pt-[130px] lg:pb-14">
                <div class="sheet flex flex-col gap-5 p-6 sm:p-10 lg:w-[560px] lg:px-10 lg:py-9">
                    <p class="text-[15px] font-semibold tracking-[0.2em] text-stamp uppercase">Историческая викторина</p>
                    <h1 class="font-serif text-[44px] leading-none font-semibold tracking-[-0.01em] sm:text-[64px]">
                        Где и когда это было?
                    </h1>
                    <p class="font-serif text-lg leading-[1.55] text-ink-soft">
                        Перед вами историческое изображение. Отметьте на карте, где это происходит, и назовите год.
                        Чем ближе к правде, тем больше очков.
                    </p>
                    <ErrorPanel
                        v-if="startError"
                        :error="startError"
                        :retrying="starting"
                        @retry="play()"
                    />
                    <div class="flex flex-wrap gap-2.5">
                        <button
                            type="button"
                            class="btn-primary max-sm:w-full"
                            :disabled="starting"
                            @click="play()"
                        >
                            {{ starting ? 'Начинаем…' : 'Начать игру' }}
                        </button>
                        <RouterLink v-if="unfinished" :to="{ name: 'game', params: { gameId: unfinished } }" class="btn-secondary max-sm:w-full">
                            Продолжить
                        </RouterLink>
                    </div>
                    <ol class="grid grid-cols-3 gap-3 border-t border-ink pt-4" aria-label="Как играть">
                        <li v-for="(step, index) in steps" :key="step" class="text-[15px] leading-[1.3]">
                            <span class="block font-serif text-2xl font-semibold text-stamp">{{ index + 1 }}</span>
                            {{ step }}
                        </li>
                    </ol>
                </div>
            </div>
        </section>

        <section id="quizzes" aria-labelledby="quizzes-heading" class="scroll-mt-6 px-4 pt-10 pb-16 sm:px-14 sm:pt-14 sm:pb-[72px]">
            <div class="mb-7 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b-2 border-ink pb-2.5">
                <h2 id="quizzes-heading" class="font-serif text-[32px] font-semibold whitespace-nowrap sm:text-[40px]">Листы атласа</h2>
                <RouterLink :to="{ name: 'quizzes' }" class="text-[17px] font-semibold tracking-[0.1em] text-stamp uppercase hover:text-ink">
                    Все подборки →
                </RouterLink>
            </div>
            <QuizList />
        </section>
    </div>
</template>
