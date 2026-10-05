<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { gamesApi } from '@/api/games';
import { useAsyncState } from '@/composables/useAsyncState';
import { useGameStore } from '@/stores/game';
import AppHeader from '@/components/ui/AppHeader.vue';
import ErrorPanel from '@/components/ui/ErrorPanel.vue';
import NotFoundPage from '@/pages/NotFoundPage.vue';
import type { ApiError } from '@/api/client';
import { formatDistance, formatNumber, pluralYears } from '@/utils/format';
import YearStamp from '@/components/game/YearStamp.vue';

const props = defineProps<{ gameId: string }>();

const router = useRouter();
const store = useGameStore();
const result = useAsyncState(gamesApi.result);
const restarting = ref(false);
const restartError = ref<ApiError | null>(null);

const share = computed(() => {
    const data = result.data.value;
    return data ? Math.round((data.total_score / Math.max(1, data.max_score)) * 100) : 0;
});

onMounted(async () => {
    await result.run(props.gameId);
    // The game is still running: send the player back to it.
    if (result.error.value?.code === 'game_not_completed') {
        await router.replace({ name: 'game', params: { gameId: props.gameId } });
    }
});

async function playAgain(): Promise<void> {
    if (!result.data.value || restarting.value) return;
    restarting.value = true;
    restartError.value = null;
    try {
        const game = await store.start(result.data.value.quiz.id);
        await router.push({ name: 'game', params: { gameId: game.id } });
    } catch (e) {
        restartError.value = e as ApiError;
    } finally {
        restarting.value = false;
    }
}
</script>

<template>
    <NotFoundPage v-if="result.error.value?.status === 404" title="Игра не найдена" text="Ссылка устарела или игра началась в другом браузере." />

    <div v-else class="min-h-screen">
        <AppHeader />
        <main class="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
            <div class="max-w-3xl">
                <ErrorPanel
                    v-if="result.error.value && result.error.value.code !== 'game_not_completed'"
                    class="mt-8"
                    :error="result.error.value"
                    :retrying="result.loading.value"
                    @retry="result.retry()"
                />

                <div v-else-if="!result.data.value" class="mt-10 h-64 animate-pulse rounded-md bg-rule/50" aria-busy="true" />

                <template v-else>
                    <section class="py-10" aria-live="polite">
                        <p class="text-ink-soft">{{ result.data.value.quiz.title }}</p>
                        <p class="year-stamp mt-2 text-[clamp(3.5rem,12vw,6.5rem)] leading-none">
                            {{ formatNumber(result.data.value.total_score) }}
                        </p>
                        <p class="mt-2 text-lg text-ink-soft">
                            из {{ formatNumber(result.data.value.max_score) }} возможных, {{ share }}%
                        </p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <button
                                type="button"
                                class="btn-primary"
                                :disabled="restarting"
                                @click="playAgain"
                            >
                                {{ restarting ? 'Запускаем…' : 'Сыграть ещё раз' }}
                            </button>
                            <RouterLink :to="{ name: 'home' }" class="btn-secondary">Другие подборки</RouterLink>
                        </div>
                        <ErrorPanel
                            v-if="restartError"
                            class="mt-4"
                            :error="restartError"
                            @retry="playAgain"
                        />
                    </section>

                    <ol class="divide-y divide-rule border-y border-rule">
                        <li v-for="round in result.data.value.rounds" :key="round.position" class="flex gap-4 py-4">
                            <img
                                v-if="round.question.image_url"
                                :src="round.question.image_url"
                                alt=""
                                class="h-16 w-24 shrink-0 rounded object-cover"
                                loading="lazy"
                            >
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ round.question.location_name }}</p>
                                <p v-if="round.question.year !== null" class="text-3xl leading-tight text-stamp">
                                    <YearStamp :year="round.question.year" />
                                </p>
                                <p v-if="round.distance_meters !== null" class="text-sm text-ink-soft">
                                    {{ formatDistance(round.distance_meters) }} от цели,
                                    {{ round.year_difference === 0 ? 'год угадан' : `ошибка ${pluralYears(round.year_difference ?? 0)}` }}
                                </p>
                            </div>
                            <p class="shrink-0 text-right text-lg font-semibold tabular-nums">
                                {{ round.score.total !== null ? formatNumber(round.score.total) : '—' }}
                            </p>
                        </li>
                    </ol>
                </template>
            </div>
        </main>
    </div>
</template>
