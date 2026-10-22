<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** MediaItem; với audio bổ sung AudioItem: locale, duration_sec, transcript, mode. */
class MediaItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $caption = $this->caption();
        $data = [
            'id' => $this->id,
            'url' => $this->url,
            'media_type' => $this->media_type,
            'caption' => $caption?->caption,
            'license' => $this->license,
            'license_label' => __('enums.licenses.'.$this->license),
            'source_name' => $this->source_name,
            'source_url' => $this->source_url,
            'author' => $this->author,
            'rights_holder' => $this->rights_holder,
            'captured_at' => $this->captured_at?->toDateString(),
            'provenance_note' => $this->provenance_note,
            'is_cover' => (bool) $this->is_cover,
            'width' => $this->width,
            'height' => $this->height,
            // Dữ liệu cho form sửa trong quản trị (trang công khai bỏ qua)
            'external_url' => $this->external_url,
            'locale' => $this->locale,
            'caption_vi' => $this->caption('vi')?->caption,
            'caption_en' => $this->caption('en')?->caption,
            'transcript_vi' => $this->caption('vi')?->transcript,
            'transcript_en' => $this->caption('en')?->transcript,
        ];

        if ($this->media_type === 'audio') {
            $data += [
                'duration_sec' => $this->duration_sec,
                'transcript' => $caption?->transcript,
                'mode' => $this->url ? 'file' : 'tts',
            ];
        }

        return $data;
    }
}
