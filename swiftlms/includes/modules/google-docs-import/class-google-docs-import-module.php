<?php
/**
 * Google Docs Import Module
 *
 * @package SwiftLMS\Modules\GoogleDocsImport
 */

namespace SwiftLMS\Modules\GoogleDocsImport;

use SwiftLMS\Abstracts\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Google_Docs_Import_Module class.
 *
 * Main module for importing courses from Google Docs.
 */
class Google_Docs_Import_Module extends AbstractModule {

    /**
     * Module ID.
     *
     * @var string
     */
    protected $id = 'google-docs-import';

    /**
     * Module name.
     *
     * @var string
     */
    protected $name = 'Google Docs Import';

    /**
     * Module description.
     *
     * @var string
     */
    protected $description = 'Import course content directly from Google Docs with automatic lesson splitting.';

    /**
     * Initialize the module.
     */
    public function init(): void {
        // Admin menu.
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

        // Handle OAuth callback.
        add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );

        // Enqueue assets.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_sfls_gdocs_search', array( $this, 'ajax_search_docs' ) );
        add_action( 'wp_ajax_sfls_gdocs_preview', array( $this, 'ajax_preview_document' ) );
        add_action( 'wp_ajax_sfls_gdocs_import', array( $this, 'ajax_import_document' ) );
        add_action( 'wp_ajax_sfls_gdocs_disconnect', array( $this, 'ajax_disconnect' ) );
        add_action( 'wp_ajax_sfls_gdocs_save_credentials', array( $this, 'ajax_save_credentials' ) );

        // Add import button to course list.
        add_action( 'admin_footer-edit.php', array( $this, 'add_import_button' ) );
    }

    /**
     * Register admin menu.
     */
    public function register_admin_menu(): void {
        add_submenu_page(
            'swiftlms',
            __( 'Import from Google Docs', 'swiftlms' ),
            __( 'Google Docs Import', 'swiftlms' ),
            'edit_posts',
            'sfls-google-docs-import',
            array( $this, 'render_import_page' )
        );
    }

    /**
     * Handle OAuth callback.
     */
    public function handle_oauth_callback(): void {
        if ( ! isset( $_GET['page'] ) || 'sfls-google-docs-import' !== $_GET['page'] ) {
            return;
        }

        if ( ! isset( $_GET['action'] ) || 'oauth_callback' !== $_GET['action'] ) {
            return;
        }

        // Verify state/nonce.
        if ( ! isset( $_GET['state'] ) || ! wp_verify_nonce( $_GET['state'], 'sfls_google_oauth' ) ) {
            wp_die( __( 'Invalid OAuth state.', 'swiftlms' ) );
        }

        // Check for error.
        if ( isset( $_GET['error'] ) ) {
            wp_redirect( admin_url( 'admin.php?page=sfls-google-docs-import&auth_error=' . urlencode( $_GET['error'] ) ) );
            exit;
        }

        // Exchange code for tokens.
        if ( isset( $_GET['code'] ) ) {
            $result = Google_API_Client::exchange_code( $_GET['code'] );

            if ( is_wp_error( $result ) ) {
                wp_redirect( admin_url( 'admin.php?page=sfls-google-docs-import&auth_error=' . urlencode( $result->get_error_message() ) ) );
                exit;
            }

            wp_redirect( admin_url( 'admin.php?page=sfls-google-docs-import&auth_success=1' ) );
            exit;
        }
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Page hook.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( 'swiftlms_page_sfls-google-docs-import' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'sfls-google-docs-import',
            plugin_dir_url( __FILE__ ) . 'assets/css/google-docs-import.css',
            array(),
            SWIFTLMS_VERSION
        );

        wp_enqueue_script(
            'sfls-google-docs-import',
            plugin_dir_url( __FILE__ ) . 'assets/js/google-docs-import.js',
            array( 'jquery' ),
            SWIFTLMS_VERSION,
            true
        );

        wp_localize_script( 'sfls-google-docs-import', 'swiftlms_gdocs', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'sfls_gdocs_nonce' ),
            'i18n'     => array(
                'loading'       => __( 'Loading...', 'swiftlms' ),
                'importing'     => __( 'Importing...', 'swiftlms' ),
                'error'         => __( 'An error occurred. Please try again.', 'swiftlms' ),
                'confirm_import' => __( 'Are you sure you want to import this document?', 'swiftlms' ),
                'success'       => __( 'Course imported successfully!', 'swiftlms' ),
                'no_results'    => __( 'No documents found.', 'swiftlms' ),
            ),
        ) );
    }

    /**
     * Render import page.
     */
    public function render_import_page(): void {
        $has_credentials = Google_API_Client::has_credentials();
        $is_authenticated = Google_API_Client::is_authenticated();

        ?>
        <div class="wrap sfls-gdocs-wrap">
            <h1><?php esc_html_e( 'Import from Google Docs', 'swiftlms' ); ?></h1>

            <?php if ( isset( $_GET['auth_success'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Successfully connected to Google!', 'swiftlms' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( isset( $_GET['auth_error'] ) ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php printf( esc_html__( 'Authentication error: %s', 'swiftlms' ), esc_html( $_GET['auth_error'] ) ); ?></p>
                </div>
            <?php endif; ?>

            <div class="sfls-gdocs-container">
                <!-- Step 1: Setup -->
                <?php if ( ! $has_credentials ) : ?>
                    <?php $this->render_setup_step(); ?>
                <?php elseif ( ! $is_authenticated ) : ?>
                    <?php $this->render_connect_step(); ?>
                <?php else : ?>
                    <?php $this->render_import_step(); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render setup step (API credentials).
     */
    private function render_setup_step(): void {
        ?>
        <div class="sfls-gdocs-card sfls-gdocs-setup">
            <div class="sfls-gdocs-card-header">
                <span class="sfls-step-number">1</span>
                <h2><?php esc_html_e( 'Setup Google API Credentials', 'swiftlms' ); ?></h2>
            </div>
            <div class="sfls-gdocs-card-body">
                <div class="sfls-setup-instructions">
                    <p><?php esc_html_e( 'To import from Google Docs, you need to create a Google Cloud project and enable the Google Docs API.', 'swiftlms' ); ?></p>

                    <ol class="sfls-setup-steps">
                        <li>
                            <?php printf(
                                esc_html__( 'Go to the %s', 'swiftlms' ),
                                '<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">' . esc_html__( 'Google Cloud Console', 'swiftlms' ) . '</a>'
                            ); ?>
                        </li>
                        <li><?php esc_html_e( 'Create a new project or select an existing one', 'swiftlms' ); ?></li>
                        <li><?php esc_html_e( 'Enable the Google Docs API and Google Drive API', 'swiftlms' ); ?></li>
                        <li><?php esc_html_e( 'Go to "Credentials" and create an OAuth 2.0 Client ID', 'swiftlms' ); ?></li>
                        <li>
                            <?php esc_html_e( 'Set the authorized redirect URI to:', 'swiftlms' ); ?>
                            <code class="sfls-redirect-uri"><?php echo esc_url( Google_API_Client::get_redirect_uri() ); ?></code>
                            <button type="button" class="button button-small sfls-copy-btn" data-copy="<?php echo esc_attr( Google_API_Client::get_redirect_uri() ); ?>">
                                <?php esc_html_e( 'Copy', 'swiftlms' ); ?>
                            </button>
                        </li>
                        <li><?php esc_html_e( 'Copy the Client ID and Client Secret below', 'swiftlms' ); ?></li>
                    </ol>
                </div>

                <form id="sfls-gdocs-credentials-form" class="sfls-credentials-form">
                    <?php wp_nonce_field( 'sfls_gdocs_nonce', 'nonce' ); ?>

                    <div class="sfls-form-field">
                        <label for="client_id"><?php esc_html_e( 'Client ID', 'swiftlms' ); ?></label>
                        <input type="text" id="client_id" name="client_id" class="regular-text" required>
                    </div>

                    <div class="sfls-form-field">
                        <label for="client_secret"><?php esc_html_e( 'Client Secret', 'swiftlms' ); ?></label>
                        <input type="password" id="client_secret" name="client_secret" class="regular-text" required>
                    </div>

                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Save Credentials', 'swiftlms' ); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render connect step.
     */
    private function render_connect_step(): void {
        ?>
        <div class="sfls-gdocs-card sfls-gdocs-connect">
            <div class="sfls-gdocs-card-header">
                <span class="sfls-step-number">2</span>
                <h2><?php esc_html_e( 'Connect to Google', 'swiftlms' ); ?></h2>
            </div>
            <div class="sfls-gdocs-card-body">
                <p><?php esc_html_e( 'Click the button below to authorize SwiftLMS to access your Google Docs.', 'swiftlms' ); ?></p>
                <p class="sfls-permissions-note">
                    <strong><?php esc_html_e( 'Permissions requested:', 'swiftlms' ); ?></strong>
                    <?php esc_html_e( 'Read-only access to your Google Docs and Drive files.', 'swiftlms' ); ?>
                </p>

                <a href="<?php echo esc_url( Google_API_Client::get_auth_url() ); ?>" class="button button-primary button-hero sfls-connect-btn">
                    <span class="dashicons dashicons-google"></span>
                    <?php esc_html_e( 'Connect with Google', 'swiftlms' ); ?>
                </a>

                <p class="sfls-reconfigure-note">
                    <a href="#" id="sfls-reconfigure-btn"><?php esc_html_e( 'Reconfigure API credentials', 'swiftlms' ); ?></a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render import step.
     */
    private function render_import_step(): void {
        ?>
        <div class="sfls-gdocs-card sfls-gdocs-import">
            <div class="sfls-gdocs-card-header">
                <h2><?php esc_html_e( 'Select a Document to Import', 'swiftlms' ); ?></h2>
                <button type="button" id="sfls-disconnect-btn" class="button">
                    <?php esc_html_e( 'Disconnect', 'swiftlms' ); ?>
                </button>
            </div>
            <div class="sfls-gdocs-card-body">
                <!-- Search/URL Input -->
                <div class="sfls-doc-input">
                    <div class="sfls-input-tabs">
                        <button type="button" class="sfls-tab-btn active" data-tab="search">
                            <?php esc_html_e( 'Search Documents', 'swiftlms' ); ?>
                        </button>
                        <button type="button" class="sfls-tab-btn" data-tab="url">
                            <?php esc_html_e( 'Paste URL', 'swiftlms' ); ?>
                        </button>
                    </div>

                    <div class="sfls-tab-content active" id="tab-search">
                        <div class="sfls-search-box">
                            <input type="text" id="sfls-doc-search" placeholder="<?php esc_attr_e( 'Search your Google Docs...', 'swiftlms' ); ?>">
                            <button type="button" id="sfls-search-btn" class="button">
                                <span class="dashicons dashicons-search"></span>
                            </button>
                        </div>
                        <div id="sfls-search-results" class="sfls-search-results"></div>
                    </div>

                    <div class="sfls-tab-content" id="tab-url">
                        <div class="sfls-url-input">
                            <input type="url" id="sfls-doc-url" placeholder="<?php esc_attr_e( 'Paste Google Docs URL here...', 'swiftlms' ); ?>">
                            <button type="button" id="sfls-load-url-btn" class="button button-primary">
                                <?php esc_html_e( 'Load Document', 'swiftlms' ); ?>
                            </button>
                        </div>
                        <p class="sfls-url-hint">
                            <?php esc_html_e( 'Example: https://docs.google.com/document/d/abc123/edit', 'swiftlms' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Preview Section -->
                <div id="sfls-preview-section" class="sfls-preview-section" style="display: none;">
                    <h3><?php esc_html_e( 'Document Preview', 'swiftlms' ); ?></h3>
                    <div id="sfls-preview-content"></div>

                    <!-- Import Options -->
                    <div class="sfls-import-options">
                        <h4><?php esc_html_e( 'Import Options', 'swiftlms' ); ?></h4>

                        <div class="sfls-options-grid">
                            <div class="sfls-option-field">
                                <label for="split_by"><?php esc_html_e( 'Split Lessons By', 'swiftlms' ); ?></label>
                                <select id="split_by" name="split_by">
                                    <option value="heading_1"><?php esc_html_e( 'Heading 1 (H1)', 'swiftlms' ); ?></option>
                                    <option value="heading_2"><?php esc_html_e( 'Heading 2 (H2)', 'swiftlms' ); ?></option>
                                    <option value="heading_3"><?php esc_html_e( 'Heading 3 (H3)', 'swiftlms' ); ?></option>
                                    <option value="page_break"><?php esc_html_e( 'Page Breaks', 'swiftlms' ); ?></option>
                                </select>
                            </div>

                            <div class="sfls-option-field">
                                <label for="course_status"><?php esc_html_e( 'Course Status', 'swiftlms' ); ?></label>
                                <select id="course_status" name="course_status">
                                    <option value="draft"><?php esc_html_e( 'Draft', 'swiftlms' ); ?></option>
                                    <option value="publish"><?php esc_html_e( 'Published', 'swiftlms' ); ?></option>
                                </select>
                            </div>

                            <div class="sfls-option-field sfls-checkbox-field">
                                <label>
                                    <input type="checkbox" id="import_images" name="import_images" checked>
                                    <?php esc_html_e( 'Import images to Media Library', 'swiftlms' ); ?>
                                </label>
                            </div>

                            <div class="sfls-option-field sfls-checkbox-field">
                                <label>
                                    <input type="checkbox" id="create_quizzes" name="create_quizzes" checked>
                                    <?php esc_html_e( 'Create quizzes from [QUIZ] markers', 'swiftlms' ); ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="sfls-import-actions">
                        <button type="button" id="sfls-import-btn" class="button button-primary button-hero">
                            <?php esc_html_e( 'Import as Course', 'swiftlms' ); ?>
                        </button>
                        <button type="button" id="sfls-cancel-btn" class="button">
                            <?php esc_html_e( 'Cancel', 'swiftlms' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Import Progress -->
                <div id="sfls-import-progress" class="sfls-import-progress" style="display: none;">
                    <div class="sfls-progress-spinner"></div>
                    <p class="sfls-progress-text"><?php esc_html_e( 'Importing document...', 'swiftlms' ); ?></p>
                </div>

                <!-- Import Result -->
                <div id="sfls-import-result" class="sfls-import-result" style="display: none;"></div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="sfls-gdocs-card sfls-gdocs-help">
            <div class="sfls-gdocs-card-header">
                <h2><?php esc_html_e( 'Formatting Guide', 'swiftlms' ); ?></h2>
            </div>
            <div class="sfls-gdocs-card-body">
                <div class="sfls-help-columns">
                    <div class="sfls-help-column">
                        <h4><?php esc_html_e( 'Document Structure', 'swiftlms' ); ?></h4>
                        <ul>
                            <li><strong><?php esc_html_e( 'Document Title', 'swiftlms' ); ?></strong> → <?php esc_html_e( 'Course Title', 'swiftlms' ); ?></li>
                            <li><strong><?php esc_html_e( 'Text before first heading', 'swiftlms' ); ?></strong> → <?php esc_html_e( 'Course Description', 'swiftlms' ); ?></li>
                            <li><strong><?php esc_html_e( 'H1 Headings', 'swiftlms' ); ?></strong> → <?php esc_html_e( 'Lesson Titles (default split)', 'swiftlms' ); ?></li>
                            <li><strong><?php esc_html_e( 'Content under headings', 'swiftlms' ); ?></strong> → <?php esc_html_e( 'Lesson Content', 'swiftlms' ); ?></li>
                        </ul>
                    </div>

                    <div class="sfls-help-column">
                        <h4><?php esc_html_e( 'Special Markers', 'swiftlms' ); ?></h4>
                        <ul>
                            <li><code>[VIDEO] https://...</code> → <?php esc_html_e( 'Embedded video', 'swiftlms' ); ?></li>
                            <li><code>[NOTE] Text</code> → <?php esc_html_e( 'Note callout box', 'swiftlms' ); ?></li>
                            <li><code>[TIP] Text</code> → <?php esc_html_e( 'Tip callout box', 'swiftlms' ); ?></li>
                            <li><code>[WARNING] Text</code> → <?php esc_html_e( 'Warning callout box', 'swiftlms' ); ?></li>
                        </ul>
                    </div>

                    <div class="sfls-help-column">
                        <h4><?php esc_html_e( 'Quiz Markers', 'swiftlms' ); ?></h4>
                        <pre class="sfls-quiz-example">[QUIZ]
[Q] What is 2+2?
[A] 3
[A*] 4
[A] 5

[Q] True or False: The sky is blue
[A*] True
[A] False</pre>
                        <p class="sfls-hint"><?php esc_html_e( 'Use [A*] to mark correct answers', 'swiftlms' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Search documents.
     */
    public function ajax_search_docs(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $query = isset( $_POST['query'] ) ? sanitize_text_field( $_POST['query'] ) : '';

        if ( empty( $query ) ) {
            // Get recent docs.
            $result = Google_API_Client::list_recent_docs( 10 );
        } else {
            $result = Google_API_Client::search_docs( $query, 10 );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $docs = array();
        foreach ( $result['files'] ?? array() as $file ) {
            $docs[] = array(
                'id'        => $file['id'],
                'name'      => $file['name'],
                'thumbnail' => $file['thumbnailLink'] ?? '',
                'modified'  => $file['modifiedTime'] ?? '',
            );
        }

        wp_send_json_success( array( 'docs' => $docs ) );
    }

    /**
     * AJAX: Preview document.
     */
    public function ajax_preview_document(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $doc_id = isset( $_POST['doc_id'] ) ? sanitize_text_field( $_POST['doc_id'] ) : '';
        $doc_url = isset( $_POST['doc_url'] ) ? esc_url_raw( $_POST['doc_url'] ) : '';

        // Extract ID from URL if provided.
        if ( $doc_url && ! $doc_id ) {
            $doc_id = Google_API_Client::extract_document_id( $doc_url );
        }

        if ( empty( $doc_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid document ID or URL.', 'swiftlms' ) ) );
        }

        // Fetch document.
        $document = Google_API_Client::get_document( $doc_id );

        if ( is_wp_error( $document ) ) {
            wp_send_json_error( array( 'message' => $document->get_error_message() ) );
        }

        // Parse and preview.
        $parser = new Document_Parser( $document );
        $split_by = isset( $_POST['split_by'] ) ? sanitize_text_field( $_POST['split_by'] ) : 'heading_1';

        $parsed = $parser->parse( array(
            'split_by'       => $split_by,
            'import_images'  => false, // Don't import during preview.
            'create_quizzes' => true,
        ) );

        $importer = new Course_Importer( $parsed );
        $preview = $importer->preview();

        wp_send_json_success( array(
            'doc_id'    => $doc_id,
            'title'     => $document['title'],
            'preview'   => $preview,
            'structure' => $parser->get_structure_preview(),
            'stats'     => array(
                'word_count'    => $parser->get_word_count(),
                'heading_counts' => $parser->get_heading_counts(),
            ),
        ) );
    }

    /**
     * AJAX: Import document.
     */
    public function ajax_import_document(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $doc_id = isset( $_POST['doc_id'] ) ? sanitize_text_field( $_POST['doc_id'] ) : '';

        if ( empty( $doc_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid document ID.', 'swiftlms' ) ) );
        }

        // Fetch document.
        $document = Google_API_Client::get_document( $doc_id );

        if ( is_wp_error( $document ) ) {
            wp_send_json_error( array( 'message' => $document->get_error_message() ) );
        }

        // Parse options.
        $options = array(
            'split_by'        => isset( $_POST['split_by'] ) ? sanitize_text_field( $_POST['split_by'] ) : 'heading_1',
            'import_images'   => ! empty( $_POST['import_images'] ),
            'create_quizzes'  => ! empty( $_POST['create_quizzes'] ),
            'preserve_styles' => true,
        );

        // Parse document.
        $parser = new Document_Parser( $document );
        $parsed = $parser->parse( $options );

        // Import.
        $importer = new Course_Importer( $parsed, array(
            'course_status'  => isset( $_POST['course_status'] ) ? sanitize_text_field( $_POST['course_status'] ) : 'draft',
            'import_quizzes' => $options['create_quizzes'],
        ) );

        $result = $importer->import();

        if ( ! $result['success'] ) {
            wp_send_json_error( array(
                'message' => __( 'Import completed with errors.', 'swiftlms' ),
                'errors'  => $result['errors'],
                'result'  => $result,
            ) );
        }

        wp_send_json_success( array(
            'message'    => __( 'Course imported successfully!', 'swiftlms' ),
            'course_id'  => $result['course_id'],
            'course_url' => get_edit_post_link( $result['course_id'], 'raw' ),
            'result'     => $result,
        ) );
    }

    /**
     * AJAX: Disconnect from Google.
     */
    public function ajax_disconnect(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        Google_API_Client::disconnect();

        wp_send_json_success( array( 'message' => __( 'Disconnected from Google.', 'swiftlms' ) ) );
    }

    /**
     * AJAX: Save credentials.
     */
    public function ajax_save_credentials(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( $_POST['client_id'] ) : '';
        $client_secret = isset( $_POST['client_secret'] ) ? sanitize_text_field( $_POST['client_secret'] ) : '';

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            wp_send_json_error( array( 'message' => __( 'Both Client ID and Client Secret are required.', 'swiftlms' ) ) );
        }

        Google_API_Client::save_credentials( $client_id, $client_secret );

        wp_send_json_success( array( 'message' => __( 'Credentials saved.', 'swiftlms' ) ) );
    }

    /**
     * Add import button to course list.
     */
    public function add_import_button(): void {
        global $typenow;

        if ( 'sfls_course' !== $typenow ) {
            return;
        }

        ?>
        <script>
        jQuery(document).ready(function($) {
            var importBtn = '<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-google-docs-import' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Import from Google Docs', 'swiftlms' ); ?></a>';
            $('.page-title-action').after(importBtn);
        });
        </script>
        <?php
    }
}
