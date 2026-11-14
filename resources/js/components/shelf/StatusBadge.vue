<script setup lang="ts">
import type { ReadingStatus } from '@/types/shelf';

defineProps<{ status: ReadingStatus; label: string }>();

const styles: Record<ReadingStatus, string> = {
    want_to_read: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    reading: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    read: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    abandoned: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
};
</script>

<template>
    <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', styles[status]]">{{ label }}</span>
</template>
