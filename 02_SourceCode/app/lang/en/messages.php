<?php

return [
    'app' => [
        'notice' => 'Academic product – for educational purposes only',
    ],
    'auth' => [
        'logged_in' => 'Signed in successfully. Welcome, :name!',
        'logged_out' => 'You have been signed out.',
        'registered' => 'Your account has been created.',
        'inactive' => 'This account is disabled. Please contact an administrator.',
    ],
    'profile' => [
        'updated' => 'Profile updated.',
        'password_updated' => 'Password changed.',
    ],
    'favorites' => [
        'added' => 'Added to your favourites.',
        'removed' => 'Removed from your favourites.',
    ],
    'contributions' => [
        'submitted' => 'Thank you! Your contribution has been submitted for review.',
        'reviewed' => 'Review result saved.',
    ],
    'booking' => [
        'too_many_active' => 'You already have :max unpaid bookings. Please pay for or cancel one before holding new seats.',
        'daily_seat_limit' => 'Each account can book at most :max seats per day (:left left).',
        'held' => 'Your seats are held for :minutes minutes. Please pay to confirm.',
        'only_left' => 'Only :count seat(s) left.',
        'slot_unavailable' => 'This time slot is no longer open for booking.',
        'invalid_transition' => 'Cannot change booking status from ":from" to ":to".',
        'cannot_cancel' => 'This booking can no longer be cancelled.',
        'cannot_pay' => 'This booking cannot be paid (hold expired or already processed).',
        'cancelled' => 'Booking cancelled.',
        'refunded' => 'Booking cancelled and refunded (simulated).',
        'status_updated' => 'Booking status updated.',
    ],
    'payment' => [
        'late_refunded' => 'Your hold expired before the payment was recorded; the transaction has been refunded (simulated).',
        'unavailable' => 'The payment gateway is currently unavailable.',
        'success' => 'Payment successful. Your booking is confirmed.',
        'failed' => 'Payment failed. You may retry while your seats are held.',
        'cancelled' => 'You cancelled the payment.',
        'expired' => 'The hold has expired, so the transaction was not accepted.',
    ],
    'admin' => [
        'created' => 'Created.',
        'updated' => 'Changes saved.',
        'deleted' => 'Deleted.',
        'slot_created' => 'Time slot added.',
        'slot_deleted' => 'Time slot removed.',
        'slot_has_bookings' => 'Cannot delete a time slot that already has bookings.',
        'media_uploaded' => 'Media uploaded.',

        'media_updated' => 'Media updated.',
        'user_updated' => 'User updated.',
        'cannot_demote_self' => 'You cannot demote or deactivate yourself.',
        'quality_done' => 'Data quality check completed.',
        'quality_failed' => 'Could not reach the analytics service. Please try again later.',
    ],
    'search' => [
        'fallback_notice' => 'Using basic search because the analytics service is temporarily unavailable.',
    ],
    'ical' => [
        'description' => ':available/:capacity seats left. Guide: :guide',
    ],
    'locales' => [
        'vi' => 'Tiếng Việt',
        'en' => 'English',
    ],
];
