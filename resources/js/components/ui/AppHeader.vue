<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <header class="bg-ink text-header-text">
        <div class="mx-auto flex max-w-[1376px] flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-3 sm:px-8 lg:grid lg:min-h-[72px] lg:grid-cols-[1fr_auto_1fr] lg:py-2">
            <div class="flex items-center gap-8">
                <RouterLink :to="{ name: 'home' }" class="flex items-center gap-2.5">
                    <span class="flex size-8 items-center justify-center rounded-full bg-hay" aria-hidden="true">
                        <svg
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.4"
                            stroke-linecap="round"
                            class="text-ink"
                        >
                            <circle cx="12" cy="12" r="8" />
                            <path d="M12 8v4l3 2" />
                        </svg>
                    </span>
                    <span class="font-serif text-xl font-bold">HistoryGuessr</span>
                </RouterLink>
                <nav aria-label="Основное меню" class="hidden text-[15px] font-semibold sm:block">
                    <RouterLink :to="{ name: 'quizzes' }" class="transition-colors hover:text-hay">Подборки</RouterLink>
                </nav>
            </div>
            <div v-if="$slots.center" class="order-last flex w-full justify-center sm:order-none sm:w-auto">
                <slot name="center" />
            </div>
            <div class="flex justify-end">
                <slot />
            </div>
        </div>
    </header>
</template>
