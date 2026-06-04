/** @see Workbench\App\Http\Requests\FileRulesRequest */
export interface FileRulesRequest {
    document: File;
    avatar: File;
    small_attachment: File;
    banner: File;
    thumbnail: File;
    photo: File;
    csv_import: File;
    large_video?: File | null;
    video?: File | null;
    report: File;
    exact_size_file?: File | null;
}
