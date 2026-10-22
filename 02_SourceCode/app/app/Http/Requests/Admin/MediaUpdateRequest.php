<?php

namespace App\Http\Requests\Admin;

use App\Models\MediaAsset;
use App\Support\UploadPolicy;

/** Sửa tư liệu đã có: không đổi đối tượng và loại media; tệp mới là tùy chọn (giữ tệp cũ nếu để trống). */
class MediaUpdateRequest extends MediaStoreRequest
{
    public function rules(): array
    {
        /** @var MediaAsset $media */
        $media = $this->route('media');
        $rules = parent::rules();
        unset($rules['subject_type'], $rules['subject_id'], $rules['media_type']);
        $rules['file'] = ['nullable', 'file', ...UploadPolicy::rules($media->media_type), 'max:20480'];
        $rules['external_url'] = ['nullable', 'url:https', 'max:500'];

        return $rules;
    }
}
