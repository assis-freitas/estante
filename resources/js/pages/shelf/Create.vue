<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import BookCover from '@/components/shelf/BookCover.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Book } from '@/types/shelf';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, PenLine, Search } from 'lucide-vue-next';
import { ref } from 'vue';

const page = usePage();
const isbn = ref('');
const looking = ref(false);
const lookupError = ref<string | null>(null);
const preview = ref<Book | null>(null);
const manual = ref(false);

const form = useForm({
    mode: 'isbn' as 'isbn' | 'manual',
    isbn: '',
    title: '',
    authors: '',
    page_count: null as number | null,
    published_year: null as number | null,
});

async function lookup() {
    if (!isbn.value.trim()) return;
    looking.value = true;
    lookupError.value = null;
    preview.value = null;

    try {
        const response = await fetch(route('shelf.lookup', { isbn: isbn.value }), {
            headers: { Accept: 'application/json' },
        });
        const body = await response.json();
        if (response.ok) {
            preview.value = body.book;
        } else {
            lookupError.value = body.errors?.isbn?.[0] ?? body.message ?? 'Something went wrong. Try again.';
        }
    } catch {
        lookupError.value = 'Could not reach the catalog. Check your connection.';
    } finally {
        looking.value = false;
    }
}

function addPreview() {
    form.mode = 'isbn';
    form.isbn = preview.value!.isbn!;
    form.post(route('shelf.store'));
}

function openManual() {
    manual.value = true;
    form.mode = 'manual';
    form.isbn = isbn.value;
}

function addManual() {
    form.post(route('shelf.store'));
}
</script>

<template>
    <Head title="Add a book" />

    <AppLayout
        :breadcrumbs="[
            { title: 'My shelf', href: route('shelf.index') },
            { title: 'Add a book', href: route('shelf.create') },
        ]"
    >
        <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 p-4 md:p-6">
            <header>
                <h1 class="text-2xl font-semibold tracking-tight">Add a book</h1>
                <p class="text-sm text-muted-foreground">Type the ISBN from the back cover or the copyright page. We'll fetch the rest.</p>
            </header>

            <form class="flex gap-2" @submit.prevent="lookup">
                <div class="flex-1">
                    <Label for="isbn" class="sr-only">ISBN</Label>
                    <Input id="isbn" v-model="isbn" inputmode="numeric" autocomplete="off" autofocus placeholder="e.g. 978-85-359-0277-8" />
                </div>
                <Button type="submit" :disabled="looking || !isbn.trim()">
                    <LoaderCircle v-if="looking" class="animate-spin" />
                    <Search v-else />
                    Look up
                </Button>
            </form>

            <div
                v-if="lookupError"
                class="flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-900 dark:bg-amber-950/40"
            >
                <p>{{ lookupError }}</p>
                <Button v-if="!manual" variant="outline" size="sm" class="self-start" @click="openManual"><PenLine /> Add it by hand</Button>
            </div>

            <InputError :message="page.props.errors.domain" />

            <article v-if="preview" class="flex gap-5 rounded-xl border bg-card p-4">
                <BookCover :book="preview" class="w-28 shrink-0" />
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <h2 class="text-lg font-semibold leading-snug">{{ preview.title }}</h2>
                    <p v-if="preview.subtitle" class="text-sm text-muted-foreground">{{ preview.subtitle }}</p>
                    <p class="text-sm">{{ preview.authors.join(', ') || 'Unknown author' }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            [preview.publisher, preview.published_year, preview.page_count && `${preview.page_count} pages`]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </p>
                    <div class="mt-auto pt-3">
                        <Button :disabled="form.processing" @click="addPreview">Add to my shelf</Button>
                    </div>
                </div>
            </article>

            <p v-if="!manual && !preview && !lookupError" class="text-sm text-muted-foreground">
                No ISBN?
                <button type="button" class="font-medium text-foreground underline underline-offset-4" @click="openManual">
                    Add the book by hand</button
                >.
            </p>

            <form v-if="manual" class="grid gap-4 rounded-xl border bg-card p-4" @submit.prevent="addManual">
                <h2 class="font-semibold">Add by hand</h2>
                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input id="title" v-model="form.title" required />
                    <InputError :message="form.errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="authors">Authors <span class="font-normal text-muted-foreground">(comma separated)</span></Label>
                    <Input id="authors" v-model="form.authors" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="grid gap-2">
                        <Label for="manual-isbn">ISBN</Label>
                        <Input id="manual-isbn" v-model="form.isbn" inputmode="numeric" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pages">Pages</Label>
                        <Input id="pages" v-model="form.page_count" type="number" min="1" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="year">Year</Label>
                        <Input id="year" v-model="form.published_year" type="number" />
                    </div>
                </div>
                <InputError :message="form.errors.isbn ?? form.errors.page_count ?? form.errors.published_year" />
                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">Add to my shelf</Button>
                    <Button type="button" variant="ghost" @click="manual = false">Cancel</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
