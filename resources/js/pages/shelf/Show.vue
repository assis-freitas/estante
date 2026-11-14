<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BookCover from '@/components/shelf/BookCover.vue';
import StarRating from '@/components/shelf/StarRating.vue';
import StatusBadge from '@/components/shelf/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData } from '@/types';
import type { Copy, ReadingStatus, StatusOption } from '@/types/shelf';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ copy: Copy; statuses: StatusOption[] }>();

const page = usePage<SharedData>();
const book = computed(() => props.copy.book);
const today = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD in local time
const statusDate = ref(today);

// Starting or finishing asks "when?"; the other moves happen right away.
const datedStatuses: ReadingStatus[] = ['reading', 'read'];
const pending = ref<ReadingStatus | null>(null);

function choose(status: ReadingStatus) {
    if (status === props.copy.status) return;
    if (datedStatuses.includes(status)) {
        pending.value = status;
        statusDate.value = today;
        return;
    }
    move(status);
}

function move(status: ReadingStatus, on?: string) {
    router.patch(route('shelf.status', { copy: props.copy.id }), { status, on }, { preserveScroll: true, onSuccess: () => (pending.value = null) });
}

const review = useForm({ rating: props.copy.rating, notes: props.copy.notes ?? '' });

// After a status change the server may clear the rating; keep the form in step.
watch(
    () => props.copy,
    (copy) => {
        review.defaults({ rating: copy.rating, notes: copy.notes ?? '' });
        review.reset();
    },
);

function saveRating(rating: number | null) {
    review.rating = rating;
    review.patch(route('shelf.review', { copy: props.copy.id }), { preserveScroll: true });
}

function remove() {
    if (confirm(`Remove “${book.value.title}” from your shelf?`)) {
        router.delete(route('shelf.destroy', { copy: props.copy.id }));
    }
}

const formatDate = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { dateStyle: 'medium' });
</script>

<template>
    <Head :title="book.title" />

    <AppLayout
        :breadcrumbs="[
            { title: 'My shelf', href: route('shelf.index') },
            { title: book.title, href: route('shelf.show', { copy: copy.id }) },
        ]"
    >
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-8 p-4 md:flex-row md:p-6">
            <aside class="flex shrink-0 flex-col gap-3 md:w-52">
                <BookCover :book="book" class="mx-auto w-40 md:w-full" />
                <dl class="grid grid-cols-[auto,1fr] gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    <template v-if="book.isbn"
                        ><dt>ISBN</dt>
                        <dd class="font-mono">{{ book.isbn }}</dd></template
                    >
                    <template v-if="book.publisher"
                        ><dt>Publisher</dt>
                        <dd>{{ book.publisher }}</dd></template
                    >
                    <template v-if="book.published_year"
                        ><dt>Year</dt>
                        <dd>{{ book.published_year }}</dd></template
                    >
                    <template v-if="book.page_count"
                        ><dt>Pages</dt>
                        <dd>{{ book.page_count }}</dd></template
                    >
                    <dt>Added</dt>
                    <dd>{{ formatDate(copy.added_at) }}</dd>
                </dl>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col gap-6">
                <header class="space-y-1">
                    <StatusBadge :status="copy.status" :label="copy.status_label" />
                    <h1 class="text-2xl font-semibold leading-tight tracking-tight">{{ book.title }}</h1>
                    <p v-if="book.subtitle" class="text-muted-foreground">{{ book.subtitle }}</p>
                    <p>{{ book.authors.join(', ') || 'Unknown author' }}</p>
                </header>

                <div
                    v-if="page.props.flash.success"
                    class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                >
                    <CheckCircle2 class="size-4" /> {{ page.props.flash.success }}
                </div>
                <InputError :message="(page.props.errors as Record<string, string>).domain" />

                <section class="space-y-3">
                    <h2 class="text-sm font-medium">Reading</h2>
                    <div class="flex flex-wrap gap-2" role="group" aria-label="Reading status">
                        <Button
                            v-for="option in statuses"
                            :key="option.value"
                            size="sm"
                            :variant="option.value === copy.status ? 'default' : 'outline'"
                            :aria-pressed="option.value === copy.status"
                            @click="choose(option.value)"
                        >
                            {{ option.label }}
                        </Button>
                    </div>

                    <form
                        v-if="pending"
                        class="flex flex-wrap items-end gap-2 rounded-lg border bg-muted/40 p-3"
                        @submit.prevent="move(pending, statusDate)"
                    >
                        <div class="grid gap-1.5">
                            <Label for="status-date">{{ pending === 'reading' ? 'Started on' : 'Finished on' }}</Label>
                            <Input id="status-date" v-model="statusDate" type="date" :max="today" class="w-44" />
                        </div>
                        <Button type="submit" size="sm">Save</Button>
                        <Button type="button" size="sm" variant="ghost" @click="pending = null">Cancel</Button>
                    </form>

                    <p class="text-sm text-muted-foreground">
                        <template v-if="copy.started_on">Started {{ formatDate(copy.started_on) }}</template>
                        <template v-if="copy.started_on && copy.finished_on"> · </template>
                        <template v-if="copy.finished_on">Finished {{ formatDate(copy.finished_on) }}</template>
                    </p>
                </section>

                <section v-if="copy.status === 'read'" class="space-y-2">
                    <h2 class="text-sm font-medium">Your rating</h2>
                    <StarRating :model-value="review.rating" @update:model-value="saveRating" />
                </section>

                <form class="space-y-2" @submit.prevent="review.patch(route('shelf.review', { copy: copy.id }), { preserveScroll: true })">
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        v-model="review.notes"
                        rows="5"
                        placeholder="Quotes, thoughts, who recommended it…"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <Button type="submit" size="sm" variant="secondary" :disabled="review.processing || !review.isDirty">Save notes</Button>
                </form>

                <p v-if="book.description" class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">{{ book.description }}</p>

                <footer class="border-t pt-4">
                    <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="remove">
                        <Trash2 /> Remove from shelf
                    </Button>
                </footer>
            </div>
        </div>
    </AppLayout>
</template>
