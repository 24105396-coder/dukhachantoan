<?php

return [
    'app' => [
        'notice' => 'Academic project for teaching purposes. Some alerts and all personal data are simulated.',
    ],
    'auth' => [
        'logged_in' => 'Signed in successfully. Welcome, :name!',
        'logged_out' => 'You have signed out.',
        'registered' => 'Your account has been created.',
        'inactive' => 'This account has been locked. Please contact an administrator.',
    ],
    'profile' => [
        'updated' => 'Profile updated.',
        'password_updated' => 'Password changed.',
    ],
    'admin' => [
        'created' => 'Created.',
        'updated' => 'Changes saved.',
        'deleted' => 'Deleted.',
        'user_updated' => 'User updated.',
        'cannot_demote_self' => 'You cannot demote or lock your own account.',
        'backup_ok' => 'Database backup completed.',
        'backup_failed' => 'Backup failed, see the server log.',
    ],
    'guides' => [
        'contacts_hint' => 'Emergency: call 112 (rescue), 113 (police), 114 (fire), 115 (ambulance). Provincial numbers are on the Emergency contacts page.',
    ],
    'notify' => [
        'match_body' => "Trip \":trip\" – leg :place is inside the affected area.\n:description\nAdvice: :instructions\nSource: :source",
        'digest_title' => 'Daily information digest :date (:count items)',
        'reminder_prefix' => '[Reminder]',
        'checkin_response_title' => 'The support agency replied to your safety check-in – :trip',
        'need_help_title' => '[EMERGENCY] Traveller needs help – :trip (:code)',
        'need_help_body' => "Contact: :contact\nMessage: :message\nLocation: :position",
    ],
    'alerts' => [
        'created' => 'Draft alert created.',
        'updated' => 'Alert saved.',
        'not_editable' => 'This alert has expired or was cancelled and can no longer be edited.',
        'invalid_transition' => 'Invalid status change.',
        'invalid_transition_from' => 'Cannot change the alert from ":from" to ":to".',
        'default_source' => 'Tourist support agency',
        'invalid_geometry' => 'Invalid geometry: use a GeoJSON Polygon/MultiPolygon or {type: Circle, center: {lat, lng}, radius_km}.',
        'transitioned' => 'Alert is now ":status" (new matches: :matches, notifications: :notifications).',
    ],
    'trips' => [
        'created' => 'Trip created.',
        'updated' => 'Trip updated.',
        'cancelled' => 'Trip cancelled; pending notifications were stopped.',
        'acknowledged' => 'Alert acknowledged.',
        'not_editable' => 'This trip is completed or cancelled and can no longer be edited.',
        'not_cancellable' => 'This trip is already completed or cancelled.',
        'leg_end_before_start' => 'The leg must end after it starts.',
        'leg_outside_dates' => 'The leg must be within the trip dates.',
        'leg_overlap' => 'This leg overlaps leg :other.',
    ],
    'checkins' => [
        'recorded' => 'Safety check-in recorded.',
        'responded' => 'Response saved.',
    ],
    'notifications' => [
        'all_read' => ':count notifications marked as read.',
        'requeued' => 'Notification queued for resending.',
        'broadcast' => 'Queued :notifications notifications for :recipients recipients.',
    ],
    'incidents' => [
        'reported' => 'Incident report sent; the support agency will verify it.',
        'reviewed' => 'Review saved.',
        'verify_first' => 'Only verified incidents can become alerts.',
        'alert_source' => 'Field report verified by :org',
        'alert_created' => 'Draft alert created from the incident; review and publish it.',
    ],
    'simulation' => [
        'no_steps' => 'This scenario has no steps.',
        'source_name' => 'Simulation scenario: :name',
        'already_finished' => 'This run has already finished.',
        'started' => 'Scenario started: :alerts alerts, :matches matches, :notifications notifications (delayed steps continue on schedule).',
        'finished' => 'Simulation finished; simulated alerts have expired.',
    ],
    'sources' => [
        'fetched' => 'Open-Meteo data fetched, :drafts new draft alerts.',
        'fetch_failed' => 'The analytics service did not respond; old data kept.',
    ],
    'locales' => [
        'vi' => 'Tiếng Việt',
        'en' => 'English',
    ],
];
