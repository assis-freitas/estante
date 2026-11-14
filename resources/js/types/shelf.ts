export type ReadingStatus = 'want_to_read' | 'reading' | 'read' | 'abandoned';

export interface StatusOption {
    value: ReadingStatus;
    label: string;
}

export interface Book {
    id: number;
    isbn: string | null;
    title: string;
    subtitle: string | null;
    authors: string[];
    publisher: string | null;
    published_year: number | null;
    page_count: number | null;
    cover_url: string | null;
    description: string | null;
}

export interface Copy {
    id: number;
    status: ReadingStatus;
    status_label: string;
    started_on: string | null;
    finished_on: string | null;
    rating: number | null;
    notes: string | null;
    added_at: string;
    book: Book;
}
