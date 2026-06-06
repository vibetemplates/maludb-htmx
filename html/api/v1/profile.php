<?php
/**
 * GET /api/v1/profile.php — Professional profile for the authenticated user's restaurant
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../helpers/professional-availability.php';

api_require_method('GET');
$auth = api_authenticate();

$profile = getProfessionalProfile($auth['restaurant_id']);

if (!$profile) {
    api_error('Professional profile not configured.', 'NOT_FOUND', 404);
}

api_success([
    'profile' => [
        'id'                            => (int)$profile['id'],
        'business_name'                 => $profile['business_name'],
        'display_name'                  => $profile['display_name'],
        'business_phone'                => $profile['business_phone'],
        'business_email'                => $profile['business_email'],
        'timezone'                      => $profile['timezone'],
        'booking_slug'                  => $profile['booking_slug'],
        'slot_interval_minutes'         => (int)$profile['slot_interval_minutes'],
        'default_buffer_before_minutes' => (int)$profile['default_buffer_before_minutes'],
        'default_buffer_after_minutes'  => (int)$profile['default_buffer_after_minutes'],
        'minimum_booking_notice_hours'  => (int)$profile['minimum_booking_notice_hours'],
        'maximum_booking_horizon_days'  => (int)$profile['maximum_booking_horizon_days'],
        'default_location_type'         => $profile['default_location_type'],
        'default_location_label'        => $profile['default_location_label'],
        'booking_instructions'          => $profile['booking_instructions'],
        'cancellation_policy'           => $profile['cancellation_policy'],
        'cancellation_notice_hours'     => (int)$profile['cancellation_notice_hours'],
        'is_public_booking_enabled'     => (bool)$profile['is_public_booking_enabled'],
    ],
]);
