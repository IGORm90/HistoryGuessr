<template>
    <div class="flex flex-col gap-2.5 border border-ink bg-paper p-3.5">
        <p class="text-xs font-semibold tracking-[0.14em] uppercase">Условные обозначения</p>
        <p class="flex gap-4 text-sm">
            <span class="flex items-center gap-1.5"><i class="size-3 rounded-full bg-guess" />ваша отметка</span>
            <span class="flex items-center gap-1.5"><i class="size-3 rounded-full bg-stamp" />место съёмки</span>
        </p>
        <slot />
    </div>
</template>
