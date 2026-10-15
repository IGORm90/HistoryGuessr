<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import type { PublicQuestion } from '@/types/game';
import QuestionImage from './QuestionImage.vue';

/** The question photo over the whole screen. Esc, a click anywhere or the × button closes it. */
defineProps<{ image: PublicQuestion['image'] }>();
const open = defineModel<boolean>('open', { default: false });

const closeButton = ref<HTMLButtonElement | null>(null);
let returnFocus: HTMLElement | null = null;

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

watch(open, async (value) => {
    if (value) {
        returnFocus = document.activeElement as HTMLElement | null;
        window.addEventListener('keydown', onKeydown);
        await nextTick();
        closeButton.value?.focus();
    } else {
        window.removeEventListener('keydown', onKeydown);
        returnFocus?.focus();
        returnFocus = null;
    }
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[2000] bg-ink/85 p-4 sm:p-10"
            role="dialog"
            aria-modal="true"
            aria-label="Изображение"
            @click="open = false"
        >
            <QuestionImage :image="image" fit="contain" sizes="100vw" />
            <button
                ref="closeButton"
                type="button"
                class="absolute top-4 right-4 grid size-11 place-items-center border border-paper bg-ink text-paper hover:bg-stamp"
                aria-label="Закрыть изображение"
                @click.stop="open = false"
            >
                <svg
                    width="18"
                    height="18"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.4"
                    stroke-linecap="round"
                    aria-hidden="true"
                >
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>
    </Teleport>
</template>
