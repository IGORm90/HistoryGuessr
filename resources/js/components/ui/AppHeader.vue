<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <header class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3 sm:px-6">
        <RouterLink :to="{ name: 'home' }" class="text-lg font-semibold tracking-tight">
            HistoryGuessr
        </RouterLink>
        <slot />
    </header>
</template>
