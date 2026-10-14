<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { DEFAULT_YEAR } from '@/config/game';
import { formatCentury, formatYear } from '@/utils/year';

/**
 * Year picker: a ruler that is dragged under a fixed needle, with the chosen year set above it.
 * Drag (with inertia), click a mark, scroll, use ← → (Shift ×10), PageUp/PageDown or the ±100 buttons.
 * The model stays `null` until the player touches the ruler.
 */
const props = withDefaults(defineProps<{ disabled?: boolean; inputId?: string; min?: number; max?: number }>(), {
    disabled: false,
    inputId: 'year-input',
    min: -1000,
    max: 2020,
});
const model = defineModel<number | null>({ default: null });

const PX_PER_YEAR = 2;
const BIG_STEP = 100;

/* Ruler positions are continuous and skip the non-existent year 0: position 0 is 1 BC, position 1 is AD 1. */
function toPosition(year: number): number {
    return year < 0 ? year + 1 : year;
}
function toYear(position: number): number {
    const rounded = Math.round(position);
    return rounded <= 0 ? rounded - 1 : rounded;
}

const minPosition = computed(() => toPosition(props.min));
const maxPosition = computed(() => toPosition(props.max));
const clamp = (position: number): number => Math.min(maxPosition.value, Math.max(minPosition.value, position));

const position = ref(clamp(toPosition(model.value ?? DEFAULT_YEAR)));
const touched = ref(model.value !== null);
const dragging = ref(false);

const current = computed(() => toYear(position.value));
const absolute = computed(() => Math.abs(current.value));
const eraLabel = computed(() => (current.value < 0 ? 'до н. э.' : 'г.'));
const century = computed(() => formatCentury(current.value));

const ruler = ref<HTMLElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);

/* ───── model sync ───── */

// Years this component emitted: their (possibly late) echoes through the model must not pull the ruler back.
const echoes = new Set<number>();

function emit(year: number): void {
    echoes.add(year);
    model.value = year;
}

function touch(): void {
    if (!touched.value) {
        touched.value = true;
        emit(current.value);
    }
}

watch(current, (year) => {
    if (touched.value && year !== model.value) {
        emit(year);
    }
});

watch(model, (value) => {
    if (value !== null && echoes.has(value)) {
        if (value === current.value) {
            echoes.clear();
        }
        return;
    }
    echoes.clear();
    if (value === null) {
        // External reset (next question): back to the resting position.
        stopMotion();
        touched.value = false;
        position.value = clamp(toPosition(DEFAULT_YEAR));
    } else if (value !== current.value) {
        touched.value = true;
        moveTo(toPosition(value));
    }
});

/* ───── motion: inertia and snapping to whole years ───── */

const reduceMotion =
    typeof window === 'undefined' ||
    typeof window.requestAnimationFrame !== 'function' ||
    (typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

let frame = 0;
let velocity = 0; // positions per frame
let target: number | null = null;

function run(): void {
    if (!frame) {
        frame = requestAnimationFrame(tick);
    }
}

function stopMotion(): void {
    if (frame) {
        cancelAnimationFrame(frame);
        frame = 0;
    }
    velocity = 0;
    target = null;
}

function moveTo(next: number): void {
    velocity = 0;
    target = clamp(Math.round(next));
    if (reduceMotion) {
        position.value = target;
        target = null;
        return;
    }
    run();
}

function tick(): void {
    frame = 0;
    if (dragging.value) {
        return;
    }
    let next = position.value;
    if (velocity !== 0) {
        next = clamp(next + velocity);
        velocity *= 0.93;
        if (Math.abs(velocity) < 0.02 || next === minPosition.value || next === maxPosition.value) {
            velocity = 0;
            target = Math.round(next);
        }
    }
    if (target !== null) {
        const distance = target - next;
        next = Math.abs(distance) < 0.01 ? target : next + distance * 0.2;
        if (next === target) {
            target = null;
        }
    }
    position.value = next;
    if (velocity !== 0 || target !== null) {
        run();
    }
}

function step(delta: number): void {
    if (props.disabled) {
        return;
    }
    touch();
    moveTo((target ?? Math.round(position.value)) + delta);
}

/* ───── input ───── */

let startX = 0;
let startPosition = 0;
let lastX = 0;
let lastTime = 0;
let moved = false;

function onPointerDown(event: PointerEvent): void {
    if (props.disabled || event.button !== 0) {
        return;
    }
    ruler.value?.setPointerCapture?.(event.pointerId);
    stopMotion();
    dragging.value = true;
    moved = false;
    startX = lastX = event.clientX;
    startPosition = position.value;
    lastTime = performance.now();
}

function onPointerMove(event: PointerEvent): void {
    if (!dragging.value) {
        return;
    }
    if (!moved && Math.abs(event.clientX - startX) > 3) {
        moved = true;
        touch();
    }
    if (!moved) {
        return;
    }
    const now = performance.now();
    position.value = clamp(startPosition - (event.clientX - startX) / PX_PER_YEAR);
    const elapsed = Math.max(1, now - lastTime);
    velocity = 0.8 * velocity + 0.2 * (-(event.clientX - lastX) / PX_PER_YEAR) * (16 / elapsed);
    lastX = event.clientX;
    lastTime = now;
}

function onPointerUp(event: PointerEvent): void {
    if (!dragging.value) {
        return;
    }
    dragging.value = false;
    if (!moved && ruler.value) {
        // A click jumps to the mark under the pointer.
        const rect = ruler.value.getBoundingClientRect();
        touch();
        moveTo(position.value + (event.clientX - rect.left - rect.width / 2) / PX_PER_YEAR);
        return;
    }
    if (reduceMotion || performance.now() - lastTime > 80) {
        velocity = 0;
    }
    if (velocity === 0) {
        moveTo(position.value);
    } else {
        target = null;
        run();
    }
}

let wheelTimer: ReturnType<typeof setTimeout> | undefined;

function onWheel(event: WheelEvent): void {
    const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
    if (props.disabled || delta === 0) {
        return;
    }
    event.preventDefault();
    touch();
    stopMotion();
    position.value = clamp(position.value + delta / PX_PER_YEAR / 2);
    clearTimeout(wheelTimer);
    wheelTimer = setTimeout(() => moveTo(position.value), 120);
}

const KEY_STEPS: Record<string, number> = {
    ArrowLeft: -1,
    ArrowDown: -1,
    ArrowRight: 1,
    ArrowUp: 1,
    PageDown: -BIG_STEP,
    PageUp: BIG_STEP,
};

function onKeydown(event: KeyboardEvent): void {
    if (props.disabled) {
        return;
    }
    if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        touch();
        moveTo(event.key === 'Home' ? minPosition.value : maxPosition.value);
        return;
    }
    const delta = KEY_STEPS[event.key];
    if (delta === undefined) {
        return;
    }
    event.preventDefault();
    step(event.shiftKey && Math.abs(delta) === 1 ? delta * 10 : delta);
}

/* ───── drawing ───── */

let context: CanvasRenderingContext2D | null = null;
let width = 0;
let height = 0;
const palette = { ink: '#3b2a1e', rule: '#e3d5bc', stamp: '#b3412a' };
let resizeObserver: ResizeObserver | null = null;

function readPalette(): void {
    const styles = getComputedStyle(document.documentElement);
    for (const key of Object.keys(palette) as (keyof typeof palette)[]) {
        const value = styles.getPropertyValue(`--color-${key}`).trim();
        if (value) {
            palette[key] = value;
        }
    }
}

function resize(): void {
    if (!canvas.value || !ruler.value || !context) {
        return;
    }
    const ratio = window.devicePixelRatio || 1;
    width = ruler.value.clientWidth;
    height = ruler.value.clientHeight;
    canvas.value.width = Math.round(width * ratio);
    canvas.value.height = Math.round(height * ratio);
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    draw();
}

function draw(): void {
    const ctx = context;
    if (!ctx || width === 0) {
        return;
    }
    const centre = width / 2;
    const base = height - 12;
    const at = position.value;
    ctx.clearRect(0, 0, width, height);

    ctx.globalAlpha = 1;
    ctx.fillStyle = palette.rule;
    ctx.fillRect(0, base, width, 1);

    // The BC / AD boundary: a thin brick-red line between 1 BC and AD 1.
    const boundary = centre + (0.5 - at) * PX_PER_YEAR;
    if (boundary > 0 && boundary < width && minPosition.value <= 0 && maxPosition.value >= 1) {
        ctx.globalAlpha = 0.55;
        ctx.fillStyle = palette.stamp;
        ctx.fillRect(Math.round(boundary), 8, 1, base - 8);
    }

    const from = Math.max(minPosition.value, Math.floor(at - centre / PX_PER_YEAR) - 1);
    const to = Math.min(maxPosition.value, Math.ceil(at + centre / PX_PER_YEAR) + 1);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    ctx.strokeStyle = palette.ink;
    ctx.fillStyle = palette.ink;

    for (let mark = from; mark <= to; mark++) {
        const year = mark <= 0 ? mark - 1 : mark;
        const x = Math.round(centre + (mark - at) * PX_PER_YEAR) + 0.5;
        const major = year % 100 === 0;
        const half = year % 50 === 0;
        if (year % 5 !== 0) {
            continue; // single years are too dense to draw at this scale
        }
        let length = 6;
        let alpha = 0.25;
        if (major) [length, alpha] = [24, 0.85];
        else if (half) [length, alpha] = [17, 0.55];
        else if (year % 10 === 0) [length, alpha] = [10, 0.4];

        ctx.globalAlpha = alpha;
        ctx.lineWidth = major ? 2 : 1;
        ctx.beginPath();
        ctx.moveTo(x, base);
        ctx.lineTo(x, base - length);
        ctx.stroke();

        if (half) {
            ctx.globalAlpha = major ? 0.9 : 0.55;
            ctx.font = `${major ? 700 : 500} ${major ? 12 : 11}px 'Manrope Variable', ui-sans-serif, sans-serif`;
            ctx.fillText(year < 0 ? `${-year} до н. э.` : String(year), x, base - length - 5);
        }
    }
    ctx.globalAlpha = 1;
}

watch(position, draw);

onMounted(() => {
    context = canvas.value?.getContext('2d') ?? null;
    if (!context) {
        return;
    }
    readPalette();
    resize();
    if (typeof ResizeObserver !== 'undefined' && ruler.value) {
        resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe(ruler.value);
    }
    void document.fonts?.ready.then(draw);
});

onBeforeUnmount(() => {
    stopMotion();
    clearTimeout(wheelTimer);
    resizeObserver?.disconnect();
});
</script>

<template>
    <div :class="{ 'opacity-60': disabled }">
        <div class="flex items-baseline justify-between gap-3">
            <span :id="`${inputId}-label`" class="text-base font-extrabold">Год</span>
            <span class="text-xs text-ink-soft" data-test="century">{{ touched ? century : 'не выбран' }}</span>
        </div>

        <p
            class="mt-2 flex items-baseline justify-center gap-1.5 whitespace-nowrap transition-colors"
            :class="touched ? 'text-ink' : 'text-ink/30'"
            aria-hidden="true"
            data-test="year"
        >
            <span class="year-stamp text-5xl leading-none">{{ absolute }}</span>
            <span class="text-sm font-semibold" :class="touched ? 'text-ink-soft' : ''">{{ eraLabel }}</span>
        </p>

        <div class="relative mt-3">
            <div
                :id="inputId"
                ref="ruler"
                role="slider"
                :tabindex="disabled ? -1 : 0"
                :aria-labelledby="`${inputId}-label`"
                :aria-valuemin="min"
                :aria-valuemax="max"
                :aria-valuenow="touched ? current : undefined"
                :aria-valuetext="touched ? formatYear(current) : 'не выбран'"
                :aria-disabled="disabled || undefined"
                class="year-ruler relative h-16 overflow-hidden rounded-xl border bg-paper transition-colors"
                :class="[
                    disabled ? 'cursor-not-allowed border-rule' : dragging ? 'cursor-grabbing border-ink-soft' : 'cursor-grab border-rule hover:border-ink-soft',
                ]"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
                @wheel="onWheel"
                @keydown="onKeydown"
            >
                <canvas ref="canvas" class="year-ruler__canvas" aria-hidden="true" />
            </div>
            <div class="year-ruler__needle" aria-hidden="true"><i /><i /></div>
            <button
                type="button"
                class="year-ruler__step left-2"
                :disabled="disabled"
                aria-label="На 100 лет раньше"
                @click="step(-BIG_STEP)"
            >
                −100
            </button>
            <button
                type="button"
                class="year-ruler__step right-2"
                :disabled="disabled"
                aria-label="На 100 лет позже"
                @click="step(BIG_STEP)"
            >
                +100
            </button>
        </div>

        <p class="mt-2 text-center text-xs text-ink-soft">
            {{ touched ? 'Тяните линейку · ← → · Shift ×10' : 'Потяните линейку, чтобы выбрать год' }}
        </p>
    </div>
</template>

<style scoped>
.year-ruler {
    touch-action: none;
    user-select: none;
    -webkit-user-select: none;
}

.year-ruler__canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    mask-image: linear-gradient(90deg, transparent 0, #000 16%, #000 84%, transparent 100%);
}

/* Fixed needle: a hay line with diamond ends, the colour of the player's guesses. */
.year-ruler__needle {
    position: absolute;
    top: -5px;
    bottom: -5px;
    left: 50%;
    width: 0;
    pointer-events: none;
}

.year-ruler__needle::before {
    content: '';
    position: absolute;
    top: 5px;
    bottom: 5px;
    left: -1px;
    width: 2px;
    background: var(--color-hay-deep);
}

.year-ruler__needle i {
    position: absolute;
    left: -6px;
    width: 12px;
    height: 12px;
    transform: rotate(45deg);
    border: 2px solid var(--color-ink);
    border-radius: 2px;
    background: var(--color-hay);
}

.year-ruler__needle i:first-child {
    top: 0;
}

.year-ruler__needle i:last-child {
    bottom: 0;
}

.year-ruler__step {
    position: absolute;
    top: 7px;
    z-index: 1;
    padding: 5px 8px;
    border: 1px solid var(--color-rule);
    border-radius: 8px;
    background: var(--color-paper-raised);
    color: var(--color-ink);
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    transition: border-color 0.15s;
}

.year-ruler__step:hover:not(:disabled) {
    border-color: var(--color-ink);
}

.year-ruler__step:disabled {
    cursor: not-allowed;
}
</style>
