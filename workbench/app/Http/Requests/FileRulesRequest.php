<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Exercises all file-category validation rules:
 * between, dimensions, encoding, extensions, file, image,
 * max, mimetypes, mimes, size.
 */
class FileRulesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // file — must be a successfully uploaded file
            'document' => ['required', 'file'],

            // image — must be an image (jpg, jpeg, png, bmp, gif, or webp)
            'avatar' => ['required', 'image'],

            // file + between — file size between min and max kilobytes
            'small_attachment' => [
                'required',
                File::types(['pdf', 'docx'])->min(1)->max(512),
            ],

            // image + dimensions — image with dimension constraints
            'banner' => [
                'required',
                File::image()
                    ->min(10)
                    ->max(5 * 1024)
                    ->dimensions(Rule::dimensions()->minWidth(800)->minHeight(200)),
            ],

            // image + dimensions:ratio — thumbnail with exact ratio constraint
            'thumbnail' => [
                'required',
                'image',
                'dimensions:ratio=16/9',
            ],

            // extensions — file must have one of the listed user-assigned extensions
            'photo' => ['required', 'extensions:jpg,jpeg,png,webp'],

            // encoding — file must match the specified character encoding
            'csv_import' => [
                'required',
                File::types(['csv'])->encoding('utf-8'),
            ],

            // max — file must not exceed the given size in kilobytes
            'large_video' => ['nullable', 'file', 'max:102400'],

            // mimetypes — file must match one of the given MIME types
            'video' => ['nullable', 'mimetypes:video/avi,video/mpeg,video/quicktime'],

            // mimes — file MIME type must correspond to one of the given extensions
            'report' => ['required', 'mimes:pdf,docx,xlsx'],

            // file + size — file must be exactly this size in kilobytes
            'exact_size_file' => ['nullable', 'file', 'size:512'],
        ];
    }
}
