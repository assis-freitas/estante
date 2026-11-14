<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{ modelValue: number | null; readonly?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>();

const hovered = ref<number | null>(null);

function pick(stars: number) {
    if (props.readonly) return;
    // Clicking the current rating again clears it.
    emit('update:modelValue', props.modelValue === stars ? null : stars);
}
</script>

<template>
    <div class="flex items-center gap-0.5" role="radiogroup" aria-label="Rating" @mouseleave="hovered = null">
        <button
            v-for="stars in 5"
            :key="stars"
            type="button"
            role="radio"
            :aria-checked="modelValue === stars"
            :aria-label="`${stars} star${stars > 1 ? 's' : ''}`"
            :disabled="readonly"
            class="rounded p-0.5 transition-transform enabled:hover:scale-110 disabled:cursor-default"
            @mouseenter="!readonly && (hovered = stars)"
            @click="pick(stars)"
        >
            <Star class="size-6" :class="stars <= (hovered ?? modelValue ?? 0) ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/40'" />
        </button>
    </div>
</template>
