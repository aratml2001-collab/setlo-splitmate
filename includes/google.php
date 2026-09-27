<?php
// "Continue with Google" (Google Identity Services): verifies the ID token the browser receives from Google.

declare(strict_types=1);

function google_client_id(): string
{
    return (string) config('google.client_id');
}

/**
 * Ask Google to validate the ID token, then check it was issued for *this* app and is still valid.
 * Returns the token claims (sub, email, name, …) or fails with an ApiError.
 */
function verify_google_id_token(string $idToken): array
{
    $clientId = google_client_id();
    if ($clientId === '') {
        fail('Google sign-in isn’t set up on this server yet.', 503);
    }
    if (strlen($idToken) < 20 || strlen($idToken) > 4096) {
        fail('Google sign-in failed. Please try again.', 401);
    }

    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 12]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        fail('Couldn’t reach Google to verify your sign-in. Check the internet connection.', 502);
    }

    $claims = json_decode((string) $body, true);
    $verified = ($claims['email_verified'] ?? '') === 'true' || ($claims['email_verified'] ?? false) === true;
    $ok = $status === 200 && is_array($claims)
        && hash_equals($clientId, (string) ($claims['aud'] ?? ''))
        && in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
        && (int) ($claims['exp'] ?? 0) > time()
        && !empty($claims['sub']) && !empty($claims['email']) && $verified;
    if (!$ok) {
        fail('Google sign-in failed. Please try again.', 401);
    }
    return $claims;
}
