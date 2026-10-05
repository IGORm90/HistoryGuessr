export type Quiz = {
    id: string;
    slug: string;
    title: string;
    description: string | null;
    cover_url: string | null;
    round_size: number;
};
