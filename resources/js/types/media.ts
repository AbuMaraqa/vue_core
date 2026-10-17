import type { ResourceAbilities } from './admin';

export type MediaItem = {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    is_image: boolean;
    size: number;
    human_size: string;
    url: string;
    collection: string;
    owner: {
        type: string;
        id: number | string;
    };
    created_at: string | null;
    can: Pick<ResourceAbilities, 'delete'>;
};

/**
 * The value of a MediaUpload field, sent to the server when the form is saved.
 */
export type MediaInput = {
    uploads: string[];
    library: number[];
    removed: number[];
};
