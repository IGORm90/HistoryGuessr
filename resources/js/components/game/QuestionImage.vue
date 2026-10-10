<script setup lang="ts">
import { computed, ref } from 'vue';
import type { PublicQuestion } from '@/types/game';

/** `fill`: on wide screens the photo takes the height of its grid row instead of its own aspect ratio. */
const props = withDefaults(defineProps<{ image: PublicQuestion['image']; fill?: boolean }>(), { fill: false });

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
    <figure
        class="relative aspect-(--photo-ratio) overflow-hidden rounded-[20px] bg-photo"
        :class="fill ? 'lg:aspect-auto lg:h-full lg:min-h-[28rem]' : ''"
        :style="{ '--photo-ratio': aspectRatio }"
    >
        <img
            v-if="!failed && (image.large_url || image.medium_url)"
            :src="image.medium_url ?? image.large_url ?? undefined"
            :srcset="srcset"
            sizes="(min-width: 1024px) 60vw, 100vw"
            :width="image.width ?? undefined"
            :height="image.height ?? undefined"
            alt="Историческая фотография, которую нужно определить"
            class="absolute inset-0 h-full w-full object-contain"
            decoding="async"
            @error="failed = true"
        >
        <div v-else class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-hay-pale">
            Фотография не загрузилась. Обновите страницу.
        </div>
    </figure>
</template>
