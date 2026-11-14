<script setup lang="ts">
import BookCover from '@/components/shelf/BookCover.vue';
import StatusBadge from '@/components/shelf/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Copy, ReadingStatus, StatusOption } from '@/types/shelf';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { LibraryBig, Plus, Search, Star } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    copies: Copy[];
    counts: Record<ReadingStatus, number>;
    statuses: StatusOption[];
    filters: { status: ReadingStatus | null; q: string };
}>();

const search = ref(props.filters.q);
const total = computed(() => Object.values(props.counts).reduce((a, b) => a + b, 0));

function visit(params: { status?: ReadingStatus | null; q?: string }) {
    const query = { status: props.filters.status, q: search.value, ...params };
    router.get(route('shelf.index'), Object.fromEntries(Object.entries(query).filter(([, v]) => v)), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const searchNow = useDebounceFn(() => visit({ q: search.value }), 300);
</script>

<template>
    <Head title="My shelf" />

    <AppLayout :breadcrumbs="[{ title: 'My shelf', href: route('shelf.index') }]">
        <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">My shelf</h1>
                    <p class="text-sm text-muted-foreground">{{ total }} {{ total === 1 ? 'book' : 'books' }}</p>
                </div>
                <Button as-child>
                    <Link :href="route('shelf.create')"><Plus /> Add a book</Link>
                </Button>
            </header>

            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1" aria-label="Filter by status">
                    <button
                        type="button"
                        class="shrink-0 rounded-full px-3 py-1.5 text-sm transition-colors"
                        :class="filters.status === null ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent'"
                        @click="visit({ status: null })"
                    >
                        All <span class="opacity-70">{{ total }}</span>
                    </button>
                    <button
                        v-for="option in statuses"
                        :key="option.value"
                        type="button"
                        class="shrink-0 rounded-full px-3 py-1.5 text-sm transition-colors"
                        :class="filters.status === option.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent'"
                        @click="visit({ status: option.value })"
                    >
                        {{ option.label }} <span class="opacity-70">{{ counts[option.value] }}</span>
                    </button>
                </nav>

                <label class="relative md:w-72">
                    <span class="sr-only">Search your shelf</span>
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" type="search" placeholder="Title, author or ISBN" class="pl-9" @update:model-value="searchNow" />
                </label>
            </div>

            <div v-if="copies.length" class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                <Link v-for="copy in copies" :key="copy.id" :href="route('shelf.show', { copy: copy.id })" class="group flex flex-col gap-2">
                    <BookCover :book="copy.book" class="transition-transform group-hover:-translate-y-1 group-hover:shadow-md" />
                    <div class="min-w-0 space-y-1">
                        <p class="line-clamp-2 text-sm font-medium leading-snug">{{ copy.book.title }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ copy.book.authors.join(', ') || 'Unknown author' }}</p>
                        <div class="flex items-center gap-2">
                            <StatusBadge :status="copy.status" :label="copy.status_label" />
                            <span v-if="copy.rating" class="flex items-center gap-0.5 text-xs text-muted-foreground">
                                <Star class="size-3 fill-amber-400 text-amber-400" />{{ copy.rating }}
                            </span>
                        </div>
                    </div>
                </Link>
            </div>

            <div v-else class="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-16 text-center">
                <LibraryBig class="size-10 text-muted-foreground" />
                <template v-if="total === 0">
                    <p class="font-medium">Your shelf is empty</p>
                    <p class="max-w-sm text-sm text-muted-foreground">Add your first book by typing the ISBN printed on the back cover.</p>
                    <Button as-child class="mt-2">
                        <Link :href="route('shelf.create')"><Plus /> Add a book</Link>
                    </Button>
                </template>
                <template v-else>
                    <p class="font-medium">No books match</p>
                    <Button variant="outline" @click="((search = ''), visit({ status: null, q: '' }))">Clear filters</Button>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
