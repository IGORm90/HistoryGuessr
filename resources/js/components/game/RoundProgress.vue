<script setup lang="ts">
import { plural } from '@/utils/format';

defineProps<{ position: number; total: number; score: number }>();
</script>

<template>
    <div class="flex items-center gap-4 text-sm sm:gap-5">
        <p>Раунд <b class="text-hay">{{ position }}</b> из {{ total }}</p>
        <p class="rounded-lg bg-header-text/10 px-3 py-1.5">
            <b class="tabular-nums">{{ plural(score, ['очко', 'очка', 'очков']) }}</b>
        </p>
    </div>
</template>
