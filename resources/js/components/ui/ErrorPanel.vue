<script setup lang="ts">
import type { ApiError } from '@/api/client';

defineProps<{ error: ApiError; retrying?: boolean }>();
defineEmits<{ retry: [] }>();
</script>

<template>
    <div role="alert" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border border-stamp bg-paper px-4 py-3">
        <p class="font-semibold text-stamp">{{ error.status === 0 ? 'Нет связи с сервером. Проверьте интернет.' : error.message }}</p>
        <button
            v-if="error.isRetryable"
            type="button"
            class="btn-secondary min-h-9 px-3 text-sm"
            :disabled="retrying"
            @click="$emit('retry')"
        >
            Повторить
        </button>
    </div>
</template>
