<script setup lang="ts">
import { computed } from 'vue';

/** A year set like an archive date stamp: large numerals, small era note that never wraps apart. */
const props = defineProps<{ year: number }>();

const absolute = computed(() => Math.abs(props.year));
const era = computed(() => (props.year < 0 ? 'до н. э.' : 'г.'));
</script>

<template>
    <span class="inline-flex items-baseline gap-[0.18em] whitespace-nowrap">
        <span class="year-stamp">{{ absolute }}</span>{{ ' ' }}
        <span class="text-[max(0.32em,0.75rem)] font-medium tracking-normal">{{ era }}</span>
    </span>
</template>
