<?php

namespace App\Services;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

/** Ghi lượt xem: mỗi phiên chỉ đếm 1 lần cho mỗi đối tượng; view_count cập nhật theo lô (SyncViewCounts). */
class PageViewRecorder
{
    public function record(Model $subject, Request $request): bool
    {
        $key = 'viewed.'.$subject->getMorphClass().'.'.$subject->getKey();
        if (! $request->hasSession() || $request->session()->has($key)) {
            return false;
        }

        try {
            PageView::create([
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'user_id' => $request->user()?->id,
                'locale' => app()->getLocale(),
                'viewed_on' => now()->toDateString(),
                'created_at' => now(),
            ]);
            // view_count cộng theo lô bởi app:sync-view-counts (mỗi phút): không UPDATE dòng nội dung
            // theo từng lượt xem → không tranh khóa khi nhiều khách cùng mở một trang
            $subject->view_count = (int) $subject->view_count + 1;
            $request->session()->put($key, true);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
