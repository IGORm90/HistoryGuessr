<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <header class="border-b-2 border-ink bg-paper">
        <div class="mx-auto flex max-w-[1376px] items-center justify-between gap-6 px-4 py-3 sm:px-8">
            <RouterLink :to="{ name: 'home' }" class="font-serif text-[26px] leading-none font-semibold hover:text-stamp">HistoryGuessr</RouterLink>
            <nav aria-label="Основное меню">
                <RouterLink :to="{ name: 'quizzes' }" class="text-[17px] font-semibold tracking-[0.12em] uppercase hover:text-stamp">Подборки</RouterLink>
            </nav>
        </div>
    </header>
</template>
