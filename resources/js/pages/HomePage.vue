<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import AppHeader from '@/components/ui/AppHeader.vue';
import QuizList from '@/components/quiz/QuizList.vue';
import { lastGame } from '@/utils/lastGame';

const unfinished = ref(lastGame());

const steps = [
    { title: 'Рассмотрите фотографию', text: 'Одежда, вывески, транспорт и дома подскажут эпоху и место.' },
    { title: 'Отметьте место на карте', text: 'Чем ближе отметка к месту съёмки, тем больше очков.' },
    { title: 'Назовите год', text: 'Очки за год считаются отдельно: можно угадать одно и промахнуться с другим.' },
];
</script>

<template>
    <div class="min-h-screen">
        <AppHeader />
        <main class="mx-auto max-w-[1376px] px-4 pb-16 sm:px-8">
            <section class="grid gap-8 py-10 sm:py-14 lg:grid-cols-[minmax(0,1fr)_440px] lg:items-center lg:gap-14">
                <div class="space-y-5">
                    <p class="text-[0.8125rem] font-bold tracking-[0.12em] text-umber uppercase">Историческая викторина</p>
                    <h1 class="font-serif text-4xl leading-[1.1] font-bold sm:text-6xl">
                        Где и когда<br>сделан этот снимок?
                    </h1>
                    <p class="max-w-xl text-lg leading-relaxed text-ink-soft">
                        Перед вами историческая фотография. Отметьте на карте, где её сделали, и назовите год.
                        Чем ближе к правде, тем больше очков.
                    </p>
                    <div class="flex flex-wrap gap-3 pt-1">
                        <a href="#quizzes" class="btn-primary min-h-14 px-7 text-[1.0625rem]">Выбрать подборку</a>
                        <RouterLink
                            v-if="unfinished"
                            :to="{ name: 'game', params: { gameId: unfinished } }"
                            class="btn-secondary min-h-14 px-7"
                        >
                            Продолжить игру
                        </RouterLink>
                    </div>
                </div>

                <section class="card p-6" aria-labelledby="how-heading">
                    <h2 id="how-heading" class="font-serif text-2xl font-bold">Как играть</h2>
                    <ol class="mt-5 space-y-5">
                        <li v-for="(step, index) in steps" :key="step.title" class="flex gap-4">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-hay font-extrabold text-ink-deep">{{ index + 1 }}</span>
                            <span>
                                <span class="block font-bold">{{ step.title }}</span>
                                <span class="block text-sm leading-relaxed text-ink-soft">{{ step.text }}</span>
                            </span>
                        </li>
                    </ol>
                </section>
            </section>

            <section id="quizzes" aria-labelledby="quizzes-heading" class="scroll-mt-6">
                <div class="mb-5 flex items-end justify-between gap-4">
                    <h2 id="quizzes-heading" class="font-serif text-3xl font-bold">Подборки</h2>
                    <RouterLink :to="{ name: 'quizzes' }" class="text-sm font-semibold text-umber hover:underline">Все подборки</RouterLink>
                </div>
                <QuizList />
            </section>
        </main>
    </div>
</template>
