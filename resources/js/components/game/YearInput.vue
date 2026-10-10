<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { MAX_YEAR, MIN_YEAR, formatCentury, fromSignedYear, toSignedYear, type Era } from '@/utils/year';

const props = withDefaults(defineProps<{ disabled?: boolean; inputId?: string }>(), {
    disabled: false,
    inputId: 'year-input',
});
const model = defineModel<number | null>({ default: null });

// Where the −/+ buttons start from while the field is still empty.
const STEP_FROM = 1900;
// The slider is a shortcut for the photographic era; any other year is typed in.
const RANGE_MIN = 1800;
const RANGE_MAX = 2025;
const RANGE_TICKS = [1800, 1850, 1900, 1950, 2000];

const raw = ref(model.value === null ? '' : String(fromSignedYear(model.value).absolute));
const era = ref<Era>(model.value !== null && model.value < 0 ? 'bce' : 'ce');

const digitsOnly = computed(() => raw.value.replace(/\D/g, ''));
const signed = computed(() => (digitsOnly.value === '' ? null : toSignedYear(Number(digitsOnly.value), era.value)));
const showError = computed(() => digitsOnly.value !== '' && signed.value === null);
const errorText = computed(() =>
    era.value === 'bce' ? `Год до н. э. — от 1 до ${Math.abs(MIN_YEAR)}` : `Год — от 1 до ${MAX_YEAR}`,
);
const rangeValue = computed(() => Math.min(RANGE_MAX, Math.max(RANGE_MIN, signed.value ?? STEP_FROM)));
const century = computed(() => (signed.value === null ? '' : formatCentury(signed.value)));

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

function onRange(event: Event): void {
    if (props.disabled) {
        return;
    }
    era.value = 'ce';
    raw.value = (event.target as HTMLInputElement).value;
}

/** Moves the year by `delta`, skipping the non-existent year 0. */
function step(delta: number): void {
    if (props.disabled) {
        return;
    }
    const base = signed.value ?? STEP_FROM;
    let next = base + delta;
    if (base > 0 && next <= 0) next -= 1;
    if (base < 0 && next >= 0) next += 1;
    next = Math.min(MAX_YEAR, Math.max(MIN_YEAR, next));
    const { absolute, era: nextEra } = fromSignedYear(next);
    era.value = nextEra;
    raw.value = String(absolute);
}
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between gap-3">
            <label :for="inputId" class="text-base font-extrabold">Год</label>
            <span class="text-xs text-ink-soft">{{ century }}</span>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <button
                type="button"
                class="size-11 shrink-0 rounded-xl border border-rule bg-paper-raised text-lg font-bold transition-colors hover:border-ink disabled:opacity-50"
                :disabled="disabled"
                aria-label="На год раньше"
                @click="step(-1)"
            >
                −
            </button>
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
                class="year-stamp h-14 w-full min-w-0 rounded-xl border border-transparent bg-transparent px-2 text-center text-5xl leading-none text-ink placeholder:text-ink/25 hover:border-rule focus:border-umber focus:bg-paper focus:outline-none disabled:opacity-60"
                @input="onInput"
            >
            <button
                type="button"
                class="size-11 shrink-0 rounded-xl border border-rule bg-paper-raised text-lg font-bold transition-colors hover:border-ink disabled:opacity-50"
                :disabled="disabled"
                aria-label="На год позже"
                @click="step(1)"
            >
                +
            </button>
        </div>
        <div class="mt-3">
            <input
                type="range"
                class="year-range"
                :min="RANGE_MIN"
                :max="RANGE_MAX"
                :value="rangeValue"
                :disabled="disabled"
                aria-label="Год, ползунок"
                @input="onRange"
            >
            <div class="mt-1 flex justify-between text-xs text-ink-soft" aria-hidden="true">
                <span v-for="tick in RANGE_TICKS" :key="tick">{{ tick }}</span>
            </div>
        </div>
        <div role="radiogroup" aria-label="Эра" class="mt-3 flex gap-1 rounded-xl border border-rule bg-paper p-1">
            <button
                v-for="option in ([['ce', 'н. э.'], ['bce', 'до н. э.']] as const)"
                :key="option[0]"
                type="button"
                role="radio"
                :aria-checked="era === option[0]"
                :disabled="disabled"
                class="min-h-10 flex-1 rounded-lg text-sm font-semibold transition-colors disabled:opacity-60"
                :class="era === option[0] ? 'bg-ink text-header-text' : 'text-ink-soft hover:text-ink'"
                @click="setEra(option[0])"
            >
                {{ option[1] }}
            </button>
        </div>
        <p v-if="showError" :id="`${inputId}-error`" class="mt-1.5 text-sm text-stamp">{{ errorText }}</p>
    </div>
</template>
