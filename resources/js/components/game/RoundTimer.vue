<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { formatClock } from '@/utils/format';

/**
 * Time spent on the current question. Counts up while `stoppedAt` is null and freezes at the answer.
 * Without a start time (a reloaded, already answered question) it shows a dash.
 */
const props = defineProps<{ startedAt: number | null; stoppedAt: number | null }>();

const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onBeforeUnmount(() => clearInterval(timer));

const label = computed(() => (props.startedAt ? formatClock((props.stoppedAt ?? now.value) - props.startedAt) : '—'));
</script>

<template>
    <p class="flex items-center gap-2.5 rounded-full bg-hay px-[18px] py-2 text-ink-deep" role="timer" aria-label="Время на ответ">
        <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
            aria-hidden="true"
        >
            <circle cx="12" cy="13" r="8" />
            <path d="M12 9v4l2.5 2.5M9 2h6" />
        </svg>
        <span class="min-w-[3.5ch] text-xl leading-none font-extrabold tabular-nums">{{ label }}</span>
    </p>
</template>
