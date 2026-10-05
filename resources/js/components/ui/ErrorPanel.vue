<script setup lang="ts">
import type { ApiError } from '@/api/client';

defineProps<{ error: ApiError; retrying?: boolean }>();
defineEmits<{ retry: [] }>();
</script>

<template>
    <div role="alert" class="rounded-md border border-stamp/40 bg-stamp/5 px-4 py-3 text-sm">
        <p class="text-ink">{{ error.status === 0 ? 'Нет связи с сервером. Проверьте интернет.' : error.message }}</p>
        <button
            v-if="error.isRetryable"
            type="button"
            class="mt-2 font-semibold text-cyan underline disabled:opacity-60"
            :disabled="retrying"
            @click="$emit('retry')"
        >
            Повторить
        </button>
    </div>
</template>
