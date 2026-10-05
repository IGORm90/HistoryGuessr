<script setup lang="ts">
import AppHeader from '@/components/ui/AppHeader.vue';
import QuizList from '@/components/quiz/QuizList.vue';
</script>

<template>
    <div class="min-h-screen">
        <AppHeader />
        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <h1 class="mb-6 text-3xl font-semibold">Подборки</h1>
            <QuizList />
        </main>
    </div>
</template>
