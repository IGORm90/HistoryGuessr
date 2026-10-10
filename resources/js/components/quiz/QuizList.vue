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

        <ul v-if="quizzes.loading.value && !quizzes.data.value" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" aria-busy="true">
            <li v-for="n in 3" :key="n" class="h-80 animate-pulse rounded-[20px] bg-hay-pale/70" />
        </ul>

        <p v-else-if="quizzes.data.value?.length === 0" class="card text-ink-soft">
            Подборки ещё готовятся. Загляните чуть позже.
        </p>

        <ul v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="quiz in quizzes.data.value ?? []" :key="quiz.id">
                <button
                    type="button"
                    class="group flex h-full w-full flex-col overflow-hidden rounded-[20px] border border-rule bg-paper-raised text-left transition-colors hover:border-ink disabled:opacity-60"
                    :disabled="starting !== null"
                    @click="play(quiz.id)"
                >
                    <span class="block aspect-[3/2] w-full overflow-hidden bg-photo">
                        <img
                            v-if="quiz.cover_url"
                            :src="quiz.cover_url"
                            alt=""
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                            loading="lazy"
                        >
                        <span v-else class="flex h-full items-center justify-center text-hay-pale" aria-hidden="true">
                            <svg
                                width="40"
                                height="40"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            >
                                <rect
                                    x="3"
                                    y="5"
                                    width="18"
                                    height="14"
                                    rx="2"
                                />
                                <circle cx="9" cy="10" r="1.6" />
                                <path d="M21 16l-5-5-8 8" />
                            </svg>
                        </span>
                    </span>
                    <span class="flex flex-1 flex-col gap-2 p-5">
                        <span class="font-serif text-xl font-bold">{{ quiz.title }}</span>
                        <span v-if="quiz.description" class="line-clamp-2 text-sm leading-relaxed text-ink-soft">{{ quiz.description }}</span>
                        <span class="mt-auto flex items-center justify-between gap-3 pt-3">
                            <span class="rounded-full bg-paper px-3 py-1 text-xs font-semibold text-ink-soft">
                                {{ plural(quiz.round_size, ['вопрос', 'вопроса', 'вопросов']) }}
                            </span>
                            <span class="font-bold text-umber group-hover:underline">
                                {{ starting === quiz.id ? 'Запускаем…' : 'Играть →' }}
                            </span>
                        </span>
                    </span>
                </button>
            </li>
        </ul>
    </div>
</template>
