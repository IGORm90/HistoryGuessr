<script setup lang="ts">
import AppHeader from '@/components/ui/AppHeader.vue';
import QuizList from '@/components/quiz/QuizList.vue';
</script>

<template>
    <div class="min-h-screen">
        <AppHeader />
        <main class="mx-auto max-w-[1376px] px-4 pt-8 pb-16 sm:px-8">
            <div class="mb-7 space-y-2">
                <p class="text-[0.8125rem] font-bold tracking-[0.12em] text-umber uppercase">Каталог</p>
                <h1 class="font-serif text-3xl font-bold sm:text-[2.125rem]">Подборки</h1>
                <p class="max-w-2xl text-ink-soft">Каждая подборка — несколько фотографий одной темы. Выберите любую, чтобы начать игру.</p>
            </div>
            <QuizList />
        </main>
    </div>
</template>
