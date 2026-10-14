<script setup lang="ts">
import AppHeader from '@/components/ui/AppHeader.vue';
import QuizList from '@/components/quiz/QuizList.vue';
</script>

<template>
    <div class="min-h-screen bg-paper text-ink">
        <AppHeader />
        <main class="mx-auto max-w-[1376px] px-4 pt-10 pb-16 sm:px-8">
            <div class="mb-7 space-y-2 border-b-2 border-ink pb-4">
                <p class="eyebrow text-stamp">Каталог</p>
                <h1 class="font-serif text-[40px] leading-tight font-semibold">Листы атласа</h1>
                <p class="max-w-2xl font-serif text-lg text-ink-soft">Каждая подборка — несколько фотографий одной темы. Выберите любую, чтобы начать игру.</p>
            </div>
            <QuizList />
        </main>
    </div>
</template>
