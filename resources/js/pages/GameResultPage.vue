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
        <main class="mx-auto max-w-[1376px] px-4 pt-8 pb-16 sm:px-8">
            <ErrorPanel
                v-if="result.error.value && result.error.value.code !== 'game_not_completed'"
                :error="result.error.value"
                :retrying="result.loading.value"
                @retry="result.retry()"
            />

            <div v-else-if="!result.data.value" class="grid gap-7 lg:grid-cols-[400px_minmax(0,1fr)]" aria-busy="true">
                <div class="h-80 animate-pulse rounded-[20px] bg-hay-pale/70" />
                <div class="h-96 animate-pulse rounded-[20px] bg-hay-pale/50" />
            </div>

            <template v-else>
                <div class="mb-6 space-y-1">
                    <p class="text-[0.8125rem] font-bold tracking-[0.12em] text-umber uppercase">Подборка «{{ result.data.value.quiz.title }}»</p>
                    <h1 class="font-serif text-3xl font-bold sm:text-[2.125rem]">Итоги игры</h1>
                </div>

                <div class="grid gap-7 lg:grid-cols-[400px_minmax(0,1fr)] lg:items-start">
                    <section class="card p-6 lg:sticky lg:top-4" aria-live="polite">
                        <p class="text-sm font-semibold text-ink-soft">Ваш счёт</p>
                        <p class="year-stamp mt-1 text-6xl leading-none sm:text-7xl">{{ formatNumber(result.data.value.total_score) }}</p>
                        <p class="mt-2 text-ink-soft">из {{ formatNumber(result.data.value.max_score) }} возможных</p>
                        <div
                            class="mt-5 h-3 overflow-hidden rounded-full bg-hay-pale"
                            role="progressbar"
                            :aria-valuenow="share"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="`${share}% от максимума`"
                        >
                            <div class="h-full rounded-full bg-hay" :style="{ width: `${share}%` }" />
                        </div>
                        <p class="mt-2 text-sm font-semibold">{{ share }}% от максимума</p>
                        <div class="mt-6 grid gap-3">
                            <button
                                type="button"
                                class="btn-primary min-h-14 text-[1.0625rem]"
                                :disabled="restarting"
                                @click="playAgain"
                            >
                                {{ restarting ? 'Запускаем…' : 'Сыграть ещё раз' }}
                            </button>
                            <RouterLink :to="{ name: 'home' }" class="btn-secondary min-h-14">Другие подборки</RouterLink>
                        </div>
                        <ErrorPanel
                            v-if="restartError"
                            class="mt-4"
                            :error="restartError"
                            @retry="playAgain"
                        />
                    </section>

                    <section class="card p-2 sm:p-3" aria-labelledby="rounds-heading">
                        <h2 id="rounds-heading" class="px-3 pt-2 pb-1 text-base font-extrabold">Раунды</h2>
                        <ol class="divide-y divide-rule">
                            <li v-for="round in result.data.value.rounds" :key="round.position" class="flex items-center gap-4 p-3">
                                <span class="block h-20 w-28 shrink-0 overflow-hidden rounded-xl bg-photo">
                                    <img
                                        v-if="round.question.image_url"
                                        :src="round.question.image_url"
                                        alt=""
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    >
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-ink-soft">Раунд {{ round.position }}</p>
                                    <p class="truncate font-bold">{{ round.question.location_name }}</p>
                                    <p v-if="round.question.year !== null" class="text-2xl leading-tight text-stamp">
                                        <YearStamp :year="round.question.year" />
                                    </p>
                                    <p v-if="round.distance_meters !== null" class="text-sm text-ink-soft">
                                        {{ formatDistance(round.distance_meters) }} от цели,
                                        {{ round.year_difference === 0 ? 'год угадан' : `ошибка ${pluralYears(round.year_difference ?? 0)}` }}
                                    </p>
                                </div>
                                <p class="shrink-0 rounded-full bg-paper px-3 py-1.5 text-right font-extrabold tabular-nums">
                                    {{ round.score.total !== null ? `+${formatNumber(round.score.total)}` : '—' }}
                                </p>
                            </li>
                        </ol>
                    </section>
                </div>
            </template>
        </main>
    </div>
</template>
