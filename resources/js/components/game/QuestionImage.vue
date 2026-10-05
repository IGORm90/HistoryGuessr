<script setup lang="ts">
import { computed, ref } from 'vue';
import type { PublicQuestion } from '@/types/game';

const props = defineProps<{ image: PublicQuestion['image'] }>();

const failed = ref(false);
const aspectRatio = computed(() =>
    props.image.width && props.image.height ? `${props.image.width} / ${props.image.height}` : '3 / 2',
);
const srcset = computed(() =>
    [
        props.image.medium_url ? `${props.image.medium_url} 960w` : null,
        props.image.large_url ? `${props.image.large_url} 1600w` : null,
    ]
        .filter(Boolean)
        .join(', '),
);
</script>

<template>
    <figure class="overflow-hidden rounded-md bg-ink/90 shadow-[0_1px_0_rgb(0_0_0/0.05)]" :style="{ aspectRatio }">
        <img
            v-if="!failed && (image.large_url || image.medium_url)"
            :src="image.medium_url ?? image.large_url ?? undefined"
            :srcset="srcset"
            sizes="(min-width: 1024px) 60vw, 100vw"
            :width="image.width ?? undefined"
            :height="image.height ?? undefined"
            alt="Историческая фотография, которую нужно определить"
            class="h-full w-full object-contain"
            decoding="async"
            @error="failed = true"
        >
        <div v-else class="flex h-full items-center justify-center p-6 text-center text-sm text-white/70">
            Фотография не загрузилась. Обновите страницу.
        </div>
    </figure>
</template>
