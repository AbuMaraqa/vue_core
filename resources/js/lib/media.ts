import type { MediaInput } from '@/types';

/**
 * The value of a MediaUpload field before the user changes anything.
 */
export function emptyMediaInput(): MediaInput {
    return { uploads: [], library: [], removed: [] };
}
