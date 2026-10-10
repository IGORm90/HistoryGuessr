<script setup lang="ts">
defineProps<{ prompt: string | null }>();
</script>

<template>
    <h1 class="font-serif text-2xl leading-tight font-bold text-ink sm:text-[2.125rem]">
        {{ prompt ?? 'Где и когда сделан этот снимок?' }}
    </h1>
</template>
