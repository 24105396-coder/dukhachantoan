<?php

namespace App\Services;

use App\Models\TourRoute;
use Illuminate\Support\Collection;

/** Xuất lịch khung giờ tuyến tham quan theo chuẩn iCalendar (RFC 5545). */
class IcalBuilder
{
    public function route(TourRoute $route, Collection $slots): string
    {
        $name = $route->name;
        $url = localized_route('tours.show', ['route' => $route->slug]);
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//DiSanSo//Tour Slots//VI',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($name),
        ];

        foreach ($slots as $slot) {
            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:slot-'.$slot->id.'@disanso',
                'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
                'DTSTART:'.$slot->starts_at->copy()->utc()->format('Ymd\THis\Z'),
                'DTEND:'.$slot->ends_at->copy()->utc()->format('Ymd\THis\Z'),
                'SUMMARY:'.$this->escape($name),
                'DESCRIPTION:'.$this->escape(__('messages.ical.description', [
                    'available' => $slot->available, 'capacity' => $slot->capacity, 'guide' => $slot->guide_name ?? '-',
                ])),
                'URL:'.$url,
                'STATUS:'.($slot->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'),
                'END:VEVENT',
            ]);
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn ($l) => $this->fold($l), $lines))."\r\n";
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    /** Gập dòng dài hơn 75 octet theo RFC 5545. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current.$char) > 74) {
                $out .= $current."\r\n ";
                $current = '';
            }
            $current .= $char;
        }

        return $out.$current;
    }
}
