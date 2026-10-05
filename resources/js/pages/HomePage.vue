<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import AppHeader from '@/components/ui/AppHeader.vue';
import QuizList from '@/components/quiz/QuizList.vue';
import { lastGame } from '@/utils/lastGame';

const unfinished = ref(lastGame());
</script>

<template>
    <div class="min-h-screen">
        <AppHeader />
        <main class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
            <section class="grid gap-8 py-10 sm:py-16 lg:grid-cols-[1.2fr_1fr] lg:items-end">
                <div>
                    <p class="year-stamp text-[clamp(4rem,14vw,9rem)] leading-[0.85] text-ink">
                        Где?<br><span class="text-stamp">Когда?</span>
                    </p>
                </div>
                <div class="max-w-md space-y-5">
                    <p class="font-serif text-xl leading-relaxed" style="font-variation-settings: 'opsz' 24">
                        Вы видите историческую фотографию. Отметьте на карте, где её сделали, и назовите год.
                        Чем ближе к правде, тем больше очков.
                    </p>
                    <RouterLink
                        v-if="unfinished"
                        :to="{ name: 'game', params: { gameId: unfinished } }"
                        class="btn-secondary"
                    >
                        Продолжить игру
                    </RouterLink>
                </div>
            </section>

            <section aria-labelledby="quizzes-heading" class="max-w-3xl">
                <h2 id="quizzes-heading" class="mb-4 text-2xl font-semibold">Выберите подборку</h2>
                <QuizList />
            </section>
        </main>
    </div>
</template>
