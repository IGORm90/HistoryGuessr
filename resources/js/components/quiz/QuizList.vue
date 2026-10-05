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

        <ul v-if="quizzes.loading.value && !quizzes.data.value" class="space-y-3" aria-busy="true">
            <li v-for="n in 2" :key="n" class="h-24 animate-pulse rounded-md bg-rule/50" />
        </ul>

        <p v-else-if="quizzes.data.value?.length === 0" class="text-ink-soft">
            Подборки ещё готовятся. Загляните чуть позже.
        </p>

        <ul v-else class="divide-y divide-rule border-y border-rule">
            <li v-for="quiz in quizzes.data.value ?? []" :key="quiz.id">
                <button
                    type="button"
                    class="group flex w-full items-center gap-4 py-4 text-left disabled:opacity-60"
                    :disabled="starting !== null"
                    @click="play(quiz.id)"
                >
                    <img
                        v-if="quiz.cover_url"
                        :src="quiz.cover_url"
                        alt=""
                        class="h-16 w-24 shrink-0 rounded object-cover grayscale group-hover:grayscale-0"
                    >
                    <span class="min-w-0 flex-1">
                        <span class="block text-lg font-semibold group-hover:text-cyan">{{ quiz.title }}</span>
                        <span v-if="quiz.description" class="block text-ink-soft">{{ quiz.description }}</span>
                    </span>
                    <span class="shrink-0 text-sm text-ink-soft">
                        {{ starting === quiz.id ? 'Запускаем…' : plural(quiz.round_size, ['вопрос', 'вопроса', 'вопросов']) }}
                    </span>
                </button>
            </li>
        </ul>
    </div>
</template>
