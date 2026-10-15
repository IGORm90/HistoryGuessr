<script setup lang="ts">
import { computed, ref } from 'vue';
import type { PublicQuestion } from '@/types/game';

/** The question photo, filling its box: cropped (`cover`) or whole (`contain`, the pinned photo and the lightbox). */
const props = withDefaults(defineProps<{ image: PublicQuestion['image']; fit?: 'cover' | 'contain'; sizes?: string }>(), {
    fit: 'cover',
    sizes: '(min-width: 1024px) 380px, 130px',
});

const failed = ref(false);
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
    <figure class="relative h-full w-full overflow-hidden" :class="fit === 'cover' || failed ? 'bg-ink' : ''">
        <img
            v-if="!failed && (image.large_url || image.medium_url)"
            :src="image.medium_url ?? image.large_url ?? undefined"
            :srcset="srcset"
            :sizes="sizes"
            :width="image.width ?? undefined"
            :height="image.height ?? undefined"
            alt="Историческая фотография, которую нужно определить"
            class="absolute inset-0 h-full w-full"
            :class="fit === 'cover' ? 'object-cover' : 'object-contain'"
            decoding="async"
            @error="failed = true"
        >
        <div v-else class="absolute inset-0 flex items-center justify-center p-4 text-center text-sm text-paper">
            Фотография не загрузилась. Обновите страницу.
        </div>
    </figure>
</template>
