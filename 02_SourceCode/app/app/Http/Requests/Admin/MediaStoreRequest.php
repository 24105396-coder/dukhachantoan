<?php

namespace App\Http\Requests\Admin;

use App\Models\MediaAsset;
use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $type = (string) $this->input('media_type');
        // Kiểm cả phần mở rộng lẫn nội dung thật (MIME/magic bytes); SVG/HTML không bao giờ hợp lệ
        $fileRules = ['nullable', 'required_without:external_url', 'file', ...UploadPolicy::rules(in_array($type, UploadPolicy::MEDIA_TYPES, true) ? $type : 'image'), 'max:20480'];

        return [
            'subject_type' => ['required', Rule::in(['site', 'artifact', 'figure', 'route'])],
            'subject_id' => ['required', 'integer', 'min:1'],
            'media_type' => ['required', Rule::in(UploadPolicy::MEDIA_TYPES)],
            'file' => $fileRules,
            'external_url' => ['nullable', 'required_without:file', 'url:https', 'max:500'],
            'locale' => ['nullable', Rule::in(['vi', 'en'])],
            'caption_vi' => ['required', 'string', 'max:500'],
            'caption_en' => ['nullable', 'string', 'max:500'],
            'transcript_vi' => ['nullable', 'string', 'max:20000'],
            'transcript_en' => ['nullable', 'string', 'max:20000'],
            'source_name' => ['required', 'string', 'max:190'],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'author' => ['nullable', 'string', 'max:150'],
            'license' => ['required', Rule::in(MediaAsset::LICENSES)],
            'rights_holder' => ['nullable', 'string', 'max:190'],
            'captured_at' => ['nullable', 'date', 'before_or_equal:today'],
            'provenance_note' => ['nullable', 'string', 'max:2000'],
            'is_cover' => ['boolean'],
        ];
    }
}
