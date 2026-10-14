<script setup lang="ts">
import type { PublicQuestion } from '@/types/game';
import QuestionImage from './QuestionImage.vue';
import QuestionPrompt from './QuestionPrompt.vue';

/**
 * The question photo pinned over the map like a polaroid. Small on phones; on desktop it shrinks
 * (`compact`) once the answer is shown. A click opens it full screen.
 */
defineProps<{ image: PublicQuestion['image']; compact?: boolean }>();
defineEmits<{ open: [] }>();
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
        aria-label="Увеличить фотографию"
        @click="$emit('open')"
    >
        <span class="block h-[150px] w-full transition-[height] duration-300 ease-out" :class="compact ? 'lg:h-[240px]' : 'lg:h-[440px]'">
            <QuestionImage :image="image" />
        </span>
        <span v-if="!compact" class="mt-2.5 hidden flex-col gap-2.5 lg:flex">
            <QuestionPrompt />
            <span class="text-xs tracking-[0.1em] text-ink-soft uppercase">Нажмите, чтобы увеличить</span>
        </span>
        <span class="mt-1.5 block text-center text-[10px] tracking-[0.1em] uppercase lg:hidden">Развернуть фото</span>
    </button>
</template>
