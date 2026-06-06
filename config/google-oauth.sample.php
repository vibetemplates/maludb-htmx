<?php
/**
 * Google OAuth 2.0 Configuration — SAMPLE
 *
 * Copy this file to config/google-oauth.php and fill in your credentials
 * from Google Cloud Console (APIs & Services > Credentials).
 * config/google-oauth.php is gitignored and must never be committed.
 */

define('GOOGLE_CLIENT_ID', 'your-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-client-secret');

// Redirect URI is built dynamically in helpers/google-auth.php based on the current host.
// You must register each domain's callback URL in Google Cloud Console:
//   - https://your-domain.com/google-callback.php
