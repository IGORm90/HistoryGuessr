<script setup lang="ts">
import { RouterLink } from 'vue-router';
import AppHeader from '@/components/ui/AppHeader.vue';

defineProps<{ title?: string; text?: string }>();
</script>

<template>
    <div class="min-h-screen bg-paper text-ink">
        <AppHeader />
        <main class="mx-auto max-w-[1376px] px-4 py-16 sm:px-8">
            <div class="sheet mx-auto max-w-xl p-8 text-center sm:p-10">
                <p class="year-stamp text-8xl leading-none text-stamp">404</p>
                <h1 class="mt-5 font-serif text-3xl font-semibold">{{ title ?? 'Такой страницы нет' }}</h1>
                <p class="mt-2 font-serif text-lg text-ink-soft">{{ text ?? 'Возможно, ссылка устарела.' }}</p>
                <RouterLink :to="{ name: 'home' }" class="btn-primary mt-8">К подборкам</RouterLink>
            </div>
        </main>
    </div>
</template>
