<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripLeg;

/** Xuất các chặng hành trình theo chuẩn iCalendar (RFC 5545). */
class IcalBuilder
{
    public function trip(Trip $trip): string
    {
        $trip->loadMissing('legs.zone', 'legs.destination.translations');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//DuKhachAnToan//Trip Legs//VI',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($trip->title),
        ];

        foreach ($trip->legs as $leg) {
            if ($leg->starts_at === null) {
                continue;
            }
            $lines = array_merge($lines, $this->event($trip, $leg));
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn ($l) => $this->fold($l), $lines))."\r\n";
    }

    private function event(Trip $trip, TripLeg $leg): array
    {
        $end = $leg->ends_at ?? $leg->starts_at->copy()->addHour();
        $place = $leg->destination?->name ?? $leg->zone?->localizedName() ?? '';
        $summary = $leg->title ?: $place;

        return array_filter([
            'BEGIN:VEVENT',
            'UID:leg-'.$leg->id.'-'.$trip->code.'@dukhach',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$leg->starts_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($summary),
            'LOCATION:'.$this->escape($place),
            $leg->note ? 'DESCRIPTION:'.$this->escape($leg->note) : null,
            'STATUS:'.($trip->status === Trip::STATUS_CANCELLED ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT',
        ]);
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
