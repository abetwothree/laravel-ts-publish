import { defineEnum } from '@tolki/ts';

/**
 * String-backed enum with both instance and static methods.
 *
 * @see Workbench\App\Enums\MediaType
 */
export const MediaType = defineEnum({
    Image: 'image',
    Video: 'video',
    Audio: 'audio',
    Document: 'document',
    Archive: 'archive',
    backed: true,
    /** Allowed file extensions for this media type */
    extensions: {
        Image: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        Video: ['mp4', 'webm', 'mov', 'avi'],
        Audio: ['mp3', 'wav', 'ogg', 'flac'],
        Document: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
        Archive: ['zip', 'tar', 'gz', 'rar', '7z'],
    },
    /** Maximum file size in MB */
    maxSizeMb: {
        Image: 10,
        Video: 500,
        Audio: 50,
        Document: 25,
        Archive: 100,
    },
    /** Icon name */
    icon: {
        Image: 'photo',
        Video: 'film',
        Audio: 'musical-note',
        Document: 'document-text',
        Archive: 'archive-box',
    },
    /** Get the MIME type prefixes */
    mimePrefixes: {image: 'image/', video: 'video/', audio: 'audio/', document: 'application/', archive: 'application/'},
    _cases: ['Image', 'Video', 'Audio', 'Document', 'Archive'],
    _methods: ['extensions', 'maxSizeMb', 'icon'],
    _static: ['mimePrefixes'],
} as const);

export type MediaTypeType = 'image' | 'video' | 'audio' | 'document' | 'archive';

export type MediaTypeKind = 'Image' | 'Video' | 'Audio' | 'Document' | 'Archive';
