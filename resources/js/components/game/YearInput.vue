<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { MAX_YEAR, MIN_YEAR, fromSignedYear, toSignedYear, type Era } from '@/utils/year';

const props = withDefaults(defineProps<{ disabled?: boolean; inputId?: string }>(), {
    disabled: false,
    inputId: 'year-input',
});
const model = defineModel<number | null>({ default: null });

const raw = ref(model.value === null ? '' : String(fromSignedYear(model.value).absolute));
const era = ref<Era>(model.value !== null && model.value < 0 ? 'bce' : 'ce');

const digitsOnly = computed(() => raw.value.replace(/\D/g, ''));
const signed = computed(() => (digitsOnly.value === '' ? null : toSignedYear(Number(digitsOnly.value), era.value)));
const showError = computed(() => digitsOnly.value !== '' && signed.value === null);
const errorText = computed(() =>
    era.value === 'bce' ? `Год до н. э. — от 1 до ${Math.abs(MIN_YEAR)}` : `Год — от 1 до ${MAX_YEAR}`,
);

watch(signed, (value) => {
    if (value !== model.value) {
        model.value = value;
    }
});

// External resets (next question) clear the field.
watch(model, (value) => {
    if (value === null && signed.value !== null) {
        raw.value = '';
    }
});

function onInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    raw.value = input.value.replace(/\D/g, '').slice(0, 5);
    input.value = raw.value;
}

function setEra(next: Era): void {
    if (!props.disabled) {
        era.value = next;
    }
}
</script>

<template>
    <div>
        <label :for="inputId" class="mb-1.5 block text-sm font-medium text-ink-soft">Год</label>
        <div class="flex items-stretch gap-2">
            <input
                :id="inputId"
                :value="raw"
                type="text"
                inputmode="numeric"
                autocomplete="off"
                placeholder="1921"
                maxlength="5"
                :disabled="disabled"
                :aria-invalid="showError"
                :aria-describedby="showError ? `${inputId}-error` : undefined"
                class="year-stamp w-full min-w-0 rounded-md border border-rule bg-paper-raised px-3 py-2 text-2xl text-ink placeholder:text-ink/25 focus:border-cyan focus:outline-none disabled:opacity-60"
                @input="onInput"
            >
            <div role="radiogroup" aria-label="Эра" class="flex shrink-0 rounded-md border border-rule bg-paper-raised p-1">
                <button
                    v-for="option in ([['ce', 'н. э.'], ['bce', 'до н. э.']] as const)"
                    :key="option[0]"
                    type="button"
                    role="radio"
                    :aria-checked="era === option[0]"
                    :disabled="disabled"
                    class="rounded px-3 text-sm font-medium transition-colors disabled:opacity-60"
                    :class="era === option[0] ? 'bg-ink text-white' : 'text-ink-soft hover:text-ink'"
                    @click="setEra(option[0])"
                >
                    {{ option[1] }}
                </button>
            </div>
        </div>
        <p v-if="showError" :id="`${inputId}-error`" class="mt-1.5 text-sm text-stamp">{{ errorText }}</p>
    </div>
</template>
