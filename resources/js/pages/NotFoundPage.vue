<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <main class="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center gap-4 px-4 text-center">
        <h1 class="text-3xl font-semibold">Страница не найдена</h1>
        <RouterLink :to="{ name: 'home' }" class="underline">На главную</RouterLink>
    </main>
</template>
