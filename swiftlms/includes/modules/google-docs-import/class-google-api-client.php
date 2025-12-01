<?php
/**
 * Google API Client
 *
 * @package SwiftLMS\Modules\GoogleDocsImport
 */

namespace SwiftLMS\Modules\GoogleDocsImport;

defined( 'ABSPATH' ) || exit;

/**
 * Google_API_Client class.
 *
 * Handles Google OAuth2 authentication and API requests.
 */
class Google_API_Client {

    /**
     * OAuth2 endpoints.
     */
    const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const DOCS_API  = 'https://docs.googleapis.com/v1/documents/';
    const DRIVE_API = 'https://www.googleapis.com/drive/v3/files/';

    /**
     * Required scopes.
     */
    const SCOPES = array(
        'https://www.googleapis.com/auth/documents.readonly',
        'https://www.googleapis.com/auth/drive.readonly',
    );

    /**
     * Option keys.
     */
    const OPTION_CREDENTIALS = 'swiftlms_google_credentials';
    const OPTION_TOKENS      = 'swiftlms_google_tokens';

    /**
     * Get client ID.
     *
     * @return string
     */
    public static function get_client_id(): string {
        $credentials = get_option( self::OPTION_CREDENTIALS, array() );
        return $credentials['client_id'] ?? '';
    }

    /**
     * Get client secret.
     *
     * @return string
     */
    public static function get_client_secret(): string {
        $credentials = get_option( self::OPTION_CREDENTIALS, array() );
        return $credentials['client_secret'] ?? '';
    }

    /**
     * Save credentials.
     *
     * @param string $client_id     Client ID.
     * @param string $client_secret Client secret.
     * @return bool
     */
    public static function save_credentials( string $client_id, string $client_secret ): bool {
        return update_option( self::OPTION_CREDENTIALS, array(
            'client_id'     => sanitize_text_field( $client_id ),
            'client_secret' => sanitize_text_field( $client_secret ),
        ) );
    }

    /**
     * Check if credentials are configured.
     *
     * @return bool
     */
    public static function has_credentials(): bool {
        return ! empty( self::get_client_id() ) && ! empty( self::get_client_secret() );
    }

    /**
     * Get OAuth2 authorization URL.
     *
     * @return string
     */
    public static function get_auth_url(): string {
        $redirect_uri = self::get_redirect_uri();

        $params = array(
            'client_id'     => self::get_client_id(),
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => implode( ' ', self::SCOPES ),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => wp_create_nonce( 'sfls_google_oauth' ),
        );

        return self::AUTH_URL . '?' . http_build_query( $params );
    }

    /**
     * Get redirect URI.
     *
     * @return string
     */
    public static function get_redirect_uri(): string {
        return admin_url( 'admin.php?page=sfls-google-docs-import&action=oauth_callback' );
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param string $code Authorization code.
     * @return array|WP_Error
     */
    public static function exchange_code( string $code ) {
        $response = wp_remote_post( self::TOKEN_URL, array(
            'body' => array(
                'code'          => $code,
                'client_id'     => self::get_client_id(),
                'client_secret' => self::get_client_secret(),
                'redirect_uri'  => self::get_redirect_uri(),
                'grant_type'    => 'authorization_code',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return new \WP_Error( 'oauth_error', $body['error_description'] ?? $body['error'] );
        }

        // Save tokens.
        $tokens = array(
            'access_token'  => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? '',
            'expires_at'    => time() + ( $body['expires_in'] ?? 3600 ),
        );

        update_option( self::OPTION_TOKENS, $tokens );

        return $tokens;
    }

    /**
     * Get access token (refresh if needed).
     *
     * @return string|WP_Error
     */
    public static function get_access_token() {
        $tokens = get_option( self::OPTION_TOKENS, array() );

        if ( empty( $tokens['access_token'] ) ) {
            return new \WP_Error( 'not_authenticated', __( 'Not authenticated with Google.', 'swiftlms' ) );
        }

        // Check if token is expired.
        if ( isset( $tokens['expires_at'] ) && time() >= $tokens['expires_at'] - 60 ) {
            // Refresh token.
            $refreshed = self::refresh_token( $tokens['refresh_token'] ?? '' );
            if ( is_wp_error( $refreshed ) ) {
                return $refreshed;
            }
            return $refreshed['access_token'];
        }

        return $tokens['access_token'];
    }

    /**
     * Refresh access token.
     *
     * @param string $refresh_token Refresh token.
     * @return array|WP_Error
     */
    public static function refresh_token( string $refresh_token ) {
        if ( empty( $refresh_token ) ) {
            return new \WP_Error( 'no_refresh_token', __( 'No refresh token available.', 'swiftlms' ) );
        }

        $response = wp_remote_post( self::TOKEN_URL, array(
            'body' => array(
                'refresh_token' => $refresh_token,
                'client_id'     => self::get_client_id(),
                'client_secret' => self::get_client_secret(),
                'grant_type'    => 'refresh_token',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            // Clear tokens on error.
            delete_option( self::OPTION_TOKENS );
            return new \WP_Error( 'refresh_error', $body['error_description'] ?? $body['error'] );
        }

        // Update tokens.
        $tokens = array(
            'access_token'  => $body['access_token'],
            'refresh_token' => $refresh_token,
            'expires_at'    => time() + ( $body['expires_in'] ?? 3600 ),
        );

        update_option( self::OPTION_TOKENS, $tokens );

        return $tokens;
    }

    /**
     * Check if authenticated.
     *
     * @return bool
     */
    public static function is_authenticated(): bool {
        $tokens = get_option( self::OPTION_TOKENS, array() );
        return ! empty( $tokens['access_token'] );
    }

    /**
     * Disconnect from Google.
     *
     * @return bool
     */
    public static function disconnect(): bool {
        delete_option( self::OPTION_TOKENS );
        return true;
    }

    /**
     * Make authenticated API request.
     *
     * @param string $url    API URL.
     * @param array  $args   Request args.
     * @return array|WP_Error
     */
    public static function request( string $url, array $args = array() ) {
        $access_token = self::get_access_token();

        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $default_args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
        );

        $args = wp_parse_args( $args, $default_args );

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $error_message = $body['error']['message'] ?? __( 'API request failed.', 'swiftlms' );
            return new \WP_Error( 'api_error', $error_message, array( 'status' => $code ) );
        }

        return $body;
    }

    /**
     * Get Google Doc by ID.
     *
     * @param string $document_id Document ID.
     * @return array|WP_Error
     */
    public static function get_document( string $document_id ) {
        $url = self::DOCS_API . $document_id;
        return self::request( $url );
    }

    /**
     * Get file metadata from Drive.
     *
     * @param string $file_id File ID.
     * @return array|WP_Error
     */
    public static function get_file_metadata( string $file_id ) {
        $url = self::DRIVE_API . $file_id . '?fields=id,name,mimeType,thumbnailLink,modifiedTime';
        return self::request( $url );
    }

    /**
     * List recent Google Docs.
     *
     * @param int $limit Max results.
     * @return array|WP_Error
     */
    public static function list_recent_docs( int $limit = 20 ) {
        $url = self::DRIVE_API . '?' . http_build_query( array(
            'q'        => "mimeType='application/vnd.google-apps.document'",
            'orderBy'  => 'modifiedTime desc',
            'pageSize' => $limit,
            'fields'   => 'files(id,name,thumbnailLink,modifiedTime)',
        ) );

        return self::request( $url );
    }

    /**
     * Search Google Docs.
     *
     * @param string $query  Search query.
     * @param int    $limit  Max results.
     * @return array|WP_Error
     */
    public static function search_docs( string $query, int $limit = 20 ) {
        $url = self::DRIVE_API . '?' . http_build_query( array(
            'q'        => "mimeType='application/vnd.google-apps.document' and name contains '" . addslashes( $query ) . "'",
            'orderBy'  => 'modifiedTime desc',
            'pageSize' => $limit,
            'fields'   => 'files(id,name,thumbnailLink,modifiedTime)',
        ) );

        return self::request( $url );
    }

    /**
     * Download image from Google.
     *
     * @param string $url Image URL.
     * @return array|WP_Error Array with file path and type.
     */
    public static function download_image( string $url ) {
        $access_token = self::get_access_token();

        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }

        $response = wp_remote_get( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );

        // Determine extension.
        $extensions = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        );

        $ext = $extensions[ $content_type ] ?? 'jpg';

        // Save to temp file.
        $upload_dir = wp_upload_dir();
        $temp_file  = $upload_dir['basedir'] . '/swiftlms-temp-' . uniqid() . '.' . $ext;

        if ( ! file_put_contents( $temp_file, $body ) ) {
            return new \WP_Error( 'download_failed', __( 'Failed to save image.', 'swiftlms' ) );
        }

        return array(
            'file' => $temp_file,
            'type' => $content_type,
        );
    }

    /**
     * Extract document ID from URL.
     *
     * @param string $url Google Docs URL.
     * @return string|null
     */
    public static function extract_document_id( string $url ): ?string {
        // Match various Google Docs URL formats.
        $patterns = array(
            '/docs\.google\.com\/document\/d\/([a-zA-Z0-9_-]+)/',
            '/drive\.google\.com\/open\?id=([a-zA-Z0-9_-]+)/',
            '/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/',
        );

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $url, $matches ) ) {
                return $matches[1];
            }
        }

        // If URL is just the ID.
        if ( preg_match( '/^[a-zA-Z0-9_-]{20,}$/', $url ) ) {
            return $url;
        }

        return null;
    }
}
