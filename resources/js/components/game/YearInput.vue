<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { DEFAULT_YEAR } from '@/config/game';
import { formatCentury, formatYear } from '@/utils/year';

/**
 * Year picker: a scale bar (like the one on a map) dragged under a fixed needle, with the chosen year
 * set above it (`stack`) or to its left (`inline`, the year panel over the game map).
 * Drag (with inertia), click a mark, scroll, use ← → (Shift ×10), PageUp/PageDown or the ±100 buttons.
 * The model stays `null` until the player touches the ruler.
 */
const props = withDefaults(
    defineProps<{ disabled?: boolean; inputId?: string; min?: number; max?: number; layout?: 'stack' | 'inline' }>(),
    {
        disabled: false,
        inputId: 'year-input',
        min: -1000,
        max: 2020,
        layout: 'stack',
    },
);
const model = defineModel<number | null>({ default: null });

// About 220 years across the ~760 px ruler of the desktop year panel.
const PX_PER_YEAR = 3.5;
const SEGMENT = 50;
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
const palette = { ink: '#1d2b2a', paper: '#f1eee3', stamp: '#c8442b' };
let resizeObserver: ResizeObserver | null = null;

const BAR_TOP = 12;
const BAR_HEIGHT = 10;

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
    const height = ruler.value.clientHeight;
    canvas.value.width = Math.round(width * ratio);
    canvas.value.height = Math.round(height * ratio);
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    draw();
}

/** Ruler position of a segment boundary; the boundary at «year 0» lies between 1 BC and AD 1. */
function boundaryPosition(year: number): number {
    return year === 0 ? 0.5 : toPosition(year);
}

function draw(): void {
    const ctx = context;
    if (!ctx || width === 0) {
        return;
    }
    const centre = width / 2;
    const at = position.value;
    const x = (pos: number): number => centre + (pos - at) * PX_PER_YEAR;
    ctx.clearRect(0, 0, width, ctx.canvas.height);

    // Alternating ink / paper segments of 50 years, like the scale bar of a map.
    const firstYear = Math.max(props.min, toYear(at - centre / PX_PER_YEAR) - SEGMENT);
    const lastYear = Math.min(props.max, toYear(at + centre / PX_PER_YEAR) + SEGMENT);
    const minX = x(minPosition.value);
    const maxX = x(maxPosition.value);
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';

    for (let year = Math.floor(firstYear / SEGMENT) * SEGMENT; year <= lastYear; year += SEGMENT) {
        const left = Math.max(minX, x(boundaryPosition(year)));
        const right = Math.min(maxX, x(boundaryPosition(year + SEGMENT)));
        if (right > left) {
            ctx.fillStyle = Math.floor(year / SEGMENT) % 2 === 0 ? palette.ink : palette.paper;
            ctx.fillRect(left, BAR_TOP, right - left, BAR_HEIGHT);
        }

        const mark = x(boundaryPosition(year));
        if (year === 0 || mark < minX - 1 || mark > maxX + 1) {
            continue;
        }
        const major = year % 100 === 0;
        ctx.fillStyle = palette.ink;
        ctx.font = `${major ? 700 : 500} 12px 'Barlow Condensed', ui-sans-serif, sans-serif`;
        ctx.fillText(year < 0 ? `${-year} до н. э.` : String(year), mark, BAR_TOP + BAR_HEIGHT + 4);
    }

    ctx.strokeStyle = palette.ink;
    ctx.lineWidth = 1;
    ctx.strokeRect(Math.max(0, minX) + 0.5, BAR_TOP + 0.5, Math.min(width, maxX) - Math.max(0, minX) - 1, BAR_HEIGHT - 1);

    // The BC / AD boundary: a brick-red tick through the bar.
    const boundary = x(0.5);
    if (boundary > 0 && boundary < width && minPosition.value <= 0 && maxPosition.value >= 1) {
        ctx.fillStyle = palette.stamp;
        ctx.fillRect(Math.round(boundary) - 1, BAR_TOP - 4, 2, BAR_HEIGHT + 8);
    }
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
    <div
        :class="[
            { 'opacity-60': disabled },
            layout === 'inline' ? 'grid grid-cols-[auto_minmax(0,1fr)] items-center gap-4 lg:gap-7' : 'flex flex-col gap-2',
        ]"
    >
        <div :class="layout === 'stack' ? 'flex items-baseline justify-between gap-3' : ''">
            <p class="eyebrow text-xs">
                <span :id="`${inputId}-label`">Год</span> ·
                <span data-test="century">{{ touched ? century : 'не выбран' }}</span>
            </p>
            <p
                class="flex items-baseline gap-1.5 whitespace-nowrap transition-colors"
                :class="touched ? 'text-ink' : 'text-ink/30'"
                aria-hidden="true"
                data-test="year"
            >
                <span class="year-stamp text-[44px] leading-none" :class="{ 'lg:text-[64px]': layout === 'inline' }">{{ absolute }}</span>
                <span class="font-serif text-base font-semibold" :class="touched ? 'text-ink-soft' : ''">{{ eraLabel }}</span>
            </p>
        </div>

        <div class="flex min-w-0 flex-col gap-1.5">
            <div class="relative">
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
                    class="year-ruler relative h-10"
                    :class="disabled ? 'cursor-not-allowed' : dragging ? 'cursor-grabbing' : 'cursor-grab'"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="onPointerUp"
                    @wheel="onWheel"
                    @keydown="onKeydown"
                >
                    <canvas ref="canvas" class="year-ruler__canvas" aria-hidden="true" />
                </div>
                <div class="year-ruler__needle" aria-hidden="true" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <span class="text-xs tracking-[0.1em] text-ink-soft uppercase">
                    {{ touched ? 'Тяните шкалу · ← → · Shift ×10' : 'Потяните шкалу, чтобы выбрать год' }}
                </span>
                <span class="flex shrink-0 gap-1.5">
                    <button
                        type="button"
                        class="year-ruler__step"
                        :disabled="disabled"
                        aria-label="На 100 лет раньше"
                        @click="step(-BIG_STEP)"
                    >
                        −100
                    </button>
                    <button
                        type="button"
                        class="year-ruler__step"
                        :disabled="disabled"
                        aria-label="На 100 лет позже"
                        @click="step(BIG_STEP)"
                    >
                        +100
                    </button>
                </span>
            </div>
        </div>
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
    mask-image: linear-gradient(90deg, transparent 0, #000 12%, #000 88%, transparent 100%);
}

/* Fixed needle: a brick-red line with a diamond above the bar. */
.year-ruler__needle {
    position: absolute;
    top: 0;
    left: 50%;
    width: 2px;
    height: 28px;
    margin-left: -1px;
    background: var(--color-stamp);
    pointer-events: none;
}

.year-ruler__needle::before {
    content: '';
    position: absolute;
    top: -6px;
    left: -5px;
    width: 12px;
    height: 12px;
    transform: rotate(45deg);
    background: var(--color-stamp);
}

.year-ruler__step {
    padding: 4px 8px;
    border: 1px solid var(--color-ink);
    background: var(--color-paper);
    color: var(--color-ink);
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    transition:
        background-color 0.15s,
        color 0.15s;
}

.year-ruler__step:hover:not(:disabled) {
    background: var(--color-ink);
    color: var(--color-paper);
}

.year-ruler__step:disabled {
    cursor: not-allowed;
}
</style>
