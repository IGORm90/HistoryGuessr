<script setup lang="ts">
defineProps<{ prompt: string | null }>();
</script>

<template>
    <p class="font-serif text-xl leading-snug text-ink sm:text-2xl" style="font-variation-settings: 'opsz' 36">
        {{ prompt ?? 'Где и когда сделан этот снимок?' }}
    </p>
</template>
