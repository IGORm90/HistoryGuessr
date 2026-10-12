<script setup lang="ts">
defineEmits<{ retry: [] }>();
</script>

<template>
    <div
        class="absolute inset-0 z-[3] flex flex-col items-center justify-center gap-3 bg-paper/90 p-4 text-center text-sm text-ink"
        role="alert"
    >
        <p>Карта не загрузилась.</p>
        <button type="button" class="btn-secondary" @click="$emit('retry')">Повторить</button>
    </div>
</template>
