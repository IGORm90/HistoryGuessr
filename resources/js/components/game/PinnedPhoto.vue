<script setup lang="ts">
import { computed } from 'vue';
import type { PublicQuestion } from '@/types/game';
import QuestionImage from './QuestionImage.vue';
import QuestionPrompt from './QuestionPrompt.vue';

/**
 * The question photo pinned over the map like a polaroid. Small on phones; on desktop it shrinks
 * (`compact`) once the answer is shown. A click opens it full screen.
 *
 * The photo is never cropped: the frame takes the photo's own proportions (capped in height),
 * and a photo taller than the cap is shown whole, letterboxed inside the frame.
 */
const props = defineProps<{ image: PublicQuestion['image']; compact?: boolean }>();
defineEmits<{ open: [] }>();

const aspectRatio = computed(() =>
    props.image.width && props.image.height ? `${props.image.width} / ${props.image.height}` : null,
);
</script>

<template>
    <button
        type="button"
        class="polaroid flex flex-col text-left transition-[width,padding,rotate] duration-300 ease-out"
        :class="
            compact
                ? 'w-[130px] -rotate-2 p-1.5 pb-2 lg:w-[200px] lg:p-2'
                : 'w-[130px] -rotate-2 p-1.5 pb-2 lg:w-[380px] lg:-rotate-[1.5deg] lg:p-[10px] lg:pb-[14px]'
        "
        aria-label="Увеличить изображение"
        @click="$emit('open')"
    >
        <span
            class="block w-full transition-[height,max-height] duration-300 ease-out"
            :class="[
                aspectRatio ? 'max-h-[150px]' : 'h-[150px]',
                aspectRatio ? (compact ? 'lg:max-h-[240px]' : 'lg:max-h-[440px]') : compact ? 'lg:h-[240px]' : 'lg:h-[440px]',
            ]"
            :style="aspectRatio ? { aspectRatio } : undefined"
        >
            <QuestionImage :image="image" fit="contain" />
        </span>
        <span v-if="!compact" class="mt-2.5 hidden flex-col gap-2.5 lg:flex">
            <QuestionPrompt />
            <span class="text-xs tracking-[0.1em] text-ink-soft uppercase">Нажмите, чтобы увеличить</span>
        </span>
        <span class="mt-1.5 block text-center text-[10px] tracking-[0.1em] uppercase lg:hidden">Развернуть изображение</span>
    </button>
</template>
