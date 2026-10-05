<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <main class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="mb-6 text-2xl font-semibold">Подборки</h1>
        <p class="text-stone-600">Скоро здесь появятся подборки.</p>
        <RouterLink :to="{ name: 'home' }" class="mt-6 inline-block underline">На главную</RouterLink>
    </main>
</template>
