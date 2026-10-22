<?php

namespace App\Services\Notifications\Channels;

use App\Models\Notification;
use App\Services\Notifications\ChannelResult;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/** Email văn bản thuần; môi trường demo dùng MAIL_MAILER=log (ghi vào nhật ký, không gửi thật). */
class EmailChannel implements NotificationChannel
{
    public function send(Notification $notification): ChannelResult
    {
        $email = $notification->user?->email;
        if (! $email) {
            return ChannelResult::fail('recipient has no email', 422);
        }
        try {
            $text = trim($notification->body."\n\n".($notification->action_url ?? ''));
            Mail::raw($text, function (Message $message) use ($notification, $email) {
                $message->to($email)->subject('[DuKhachAnToan] '.$notification->title);
            });

            return ChannelResult::ok('mail queued to '.config('mail.default'), 'mail-'.Str::lower(Str::random(12)), 250);
        } catch (Throwable $e) {
            return ChannelResult::fail(Str::limit($e->getMessage(), 250), 500);
        }
    }
}
