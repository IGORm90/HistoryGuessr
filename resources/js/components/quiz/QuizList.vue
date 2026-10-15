<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { quizzesApi } from '@/api/quizzes';
import { useAsyncState } from '@/composables/useAsyncState';
import { useGameStore } from '@/stores/game';
import ErrorPanel from '@/components/ui/ErrorPanel.vue';
import type { ApiError } from '@/api/client';
import { plural } from '@/utils/format';

const router = useRouter();
const store = useGameStore();
const quizzes = useAsyncState(quizzesApi.list);
const starting = ref<string | null>(null);
const startError = ref<ApiError | null>(null);

onMounted(() => quizzes.run());

async function play(quizId: string): Promise<void> {
    if (starting.value) return;
    starting.value = quizId;
    startError.value = null;
    try {
        const game = await store.start(quizId);
        await router.push({ name: 'game', params: { gameId: game.id } });
    } catch (e) {
        startError.value = e as ApiError;
    } finally {
        starting.value = null;
    }
}

function sheetNumber(index: number): string {
    return String(index + 1).padStart(2, '0');
}
</script>

<template>
    <div>
        <ErrorPanel
            v-if="quizzes.error.value"
            :error="quizzes.error.value"
            :retrying="quizzes.loading.value"
            @retry="quizzes.retry()"
        />
        <ErrorPanel
            v-if="startError"
            :error="startError"
            class="mb-4"
            @retry="startError = null"
        />

        <ul v-if="quizzes.loading.value && !quizzes.data.value" class="grid gap-7 sm:grid-cols-2 lg:grid-cols-3" aria-busy="true">
            <li v-for="n in 3" :key="n" class="skeleton h-80 border border-ink" />
        </ul>

        <p v-else-if="quizzes.data.value?.length === 0" class="border border-ink p-5 font-serif text-lg text-ink-soft">
            Подборки ещё готовятся. Загляните чуть позже.
        </p>

        <ul v-else class="grid gap-7 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="(quiz, index) in quizzes.data.value ?? []" :key="quiz.id">
                <button
                    type="button"
                    class="flex h-full w-full flex-col border border-ink bg-paper text-left transition-shadow duration-200 hover:shadow-[6px_6px_0_var(--color-ink)] disabled:opacity-60"
                    :disabled="starting !== null"
                    @click="play(quiz.id)"
                >
                    <span class="skeleton relative block aspect-[3/2] w-full border-b border-ink">
                        <img
                            v-if="quiz.cover_url"
                            :src="quiz.cover_url"
                            alt=""
                            class="absolute inset-0 h-full w-full object-cover"
                            loading="lazy"
                        >
                        <span v-else class="absolute inset-0 flex items-center justify-center text-[13px] tracking-[0.1em] text-ink-soft uppercase">
                            Обложка подборки
                        </span>
                        <span class="absolute top-3 left-3 bg-ink px-2 py-[3px] text-[13px] font-semibold tracking-[0.12em] text-paper uppercase">
                            Лист {{ sheetNumber(index) }}
                        </span>
                    </span>
                    <span class="flex flex-1 flex-col gap-1.5 px-5 py-[18px]">
                        <span class="font-serif text-[26px] leading-tight font-semibold">{{ quiz.title }}</span>
                        <span v-if="quiz.description" class="line-clamp-3 font-serif text-[15px] leading-normal text-ink-soft">{{ quiz.description }}</span>
                        <span class="mt-auto flex items-center justify-between gap-3 pt-2 text-[15px] font-semibold tracking-[0.1em] uppercase">
                            <span>{{ plural(quiz.round_size, ['вопрос', 'вопроса', 'вопросов']) }}</span>
                            <span class="text-stamp">{{ starting === quiz.id ? 'Запускаем…' : 'Играть →' }}</span>
                        </span>
                    </span>
                </button>
            </li>
        </ul>
    </div>
</template>
