<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { Book } from '@/types/shelf';
import { computed, ref, type HTMLAttributes } from 'vue';

const props = defineProps<{
    book: Pick<Book, 'title' | 'authors' | 'cover_url'>;
    class?: HTMLAttributes['class'];
}>();

const failed = ref(false);

// Books without a cover get a typographic one, tinted from the title so it stays stable.
const hue = computed(() => [...props.book.title].reduce((sum, char) => sum + char.charCodeAt(0), 0) % 360);
</script>

<template>
    <div :class="cn('relative aspect-[2/3] overflow-hidden rounded-md bg-muted shadow-sm ring-1 ring-black/5 dark:ring-white/10', props.class)">
        <img
            v-if="book.cover_url && !failed"
            :src="book.cover_url"
            :alt="`Cover of ${book.title}`"
            loading="lazy"
            class="size-full object-cover"
            @error="failed = true"
        />
        <div
            v-else
            class="flex size-full flex-col justify-between p-3 text-white"
            :style="{ background: `linear-gradient(160deg, hsl(${hue} 35% 38%), hsl(${(hue + 40) % 360} 40% 22%))` }"
        >
            <span class="line-clamp-4 font-serif text-sm font-semibold leading-snug">{{ book.title }}</span>
            <span class="line-clamp-2 text-[11px] opacity-80">{{ book.authors.join(', ') }}</span>
        </div>
    </div>
</template>
