<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <main class="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center gap-6 px-4 text-center">
        <h1 class="text-4xl font-semibold">HistoryGuessr</h1>
        <p class="text-lg text-stone-600">Угадайте, где и когда было сделано историческое фото.</p>
        <RouterLink
            :to="{ name: 'quizzes' }"
            class="rounded-lg bg-stone-900 px-6 py-3 font-medium text-white hover:bg-stone-700"
        >
            Играть
        </RouterLink>
    </main>
</template>
