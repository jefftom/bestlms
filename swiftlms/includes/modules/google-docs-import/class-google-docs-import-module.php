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

        // AJAX handlers - Google Docs.
        add_action( 'wp_ajax_sfls_gdocs_search', array( $this, 'ajax_search_docs' ) );
        add_action( 'wp_ajax_sfls_gdocs_preview', array( $this, 'ajax_preview_document' ) );
        add_action( 'wp_ajax_sfls_gdocs_import', array( $this, 'ajax_import_document' ) );
        add_action( 'wp_ajax_sfls_gdocs_disconnect', array( $this, 'ajax_disconnect' ) );
        add_action( 'wp_ajax_sfls_gdocs_save_credentials', array( $this, 'ajax_save_credentials' ) );

        // AJAX handlers - Google Sheets.
        add_action( 'wp_ajax_sfls_sheets_load', array( $this, 'ajax_load_spreadsheet' ) );
        add_action( 'wp_ajax_sfls_sheets_preview', array( $this, 'ajax_preview_sheet' ) );
        add_action( 'wp_ajax_sfls_sheets_import', array( $this, 'ajax_import_sheet' ) );
        add_action( 'wp_ajax_sfls_sheets_template', array( $this, 'ajax_download_template' ) );

        // AJAX handlers - Presets.
        add_action( 'wp_ajax_sfls_preset_save', array( $this, 'ajax_save_preset' ) );
        add_action( 'wp_ajax_sfls_preset_delete', array( $this, 'ajax_delete_preset' ) );
        add_action( 'wp_ajax_sfls_preset_load', array( $this, 'ajax_load_preset' ) );

        // AJAX handlers - File Upload.
        add_action( 'wp_ajax_sfls_file_upload', array( $this, 'ajax_file_upload' ) );
        add_action( 'wp_ajax_sfls_file_preview', array( $this, 'ajax_file_preview' ) );
        add_action( 'wp_ajax_sfls_file_import', array( $this, 'ajax_file_import' ) );

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
        $presets = Parsing_Options::get_all_presets();
        ?>
        <!-- Primary Import Type Tabs -->
        <div class="sfls-primary-tabs">
            <button type="button" class="sfls-primary-tab active" data-mode="docs">
                <span class="dashicons dashicons-media-document"></span>
                <?php esc_html_e( 'Google Docs', 'swiftlms' ); ?>
            </button>
            <button type="button" class="sfls-primary-tab" data-mode="sheets">
                <span class="dashicons dashicons-editor-table"></span>
                <?php esc_html_e( 'Google Sheets', 'swiftlms' ); ?>
            </button>
            <button type="button" class="sfls-primary-tab" data-mode="upload">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e( 'File Upload', 'swiftlms' ); ?>
            </button>
        </div>

        <!-- Google Docs Import Section -->
        <div id="sfls-mode-docs" class="sfls-mode-content active">
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
        </div><!-- End Google Docs Mode -->

        <!-- Google Sheets Import Section -->
        <div id="sfls-mode-sheets" class="sfls-mode-content">
            <?php $this->render_sheets_import_section(); ?>
        </div>

        <!-- File Upload Section -->
        <div id="sfls-mode-upload" class="sfls-mode-content">
            <?php $this->render_file_upload_section(); ?>
        </div>
        <?php
    }

    /**
     * Render file upload section.
     */
    private function render_file_upload_section(): void {
        $import_types = Sheets_Parser::get_all_import_types();
        ?>
        <div class="sfls-gdocs-card sfls-file-upload">
            <div class="sfls-gdocs-card-header">
                <h2><?php esc_html_e( 'Upload Word or Excel Files', 'swiftlms' ); ?></h2>
            </div>
            <div class="sfls-gdocs-card-body">
                <!-- File Type Selection -->
                <div class="sfls-file-type-tabs">
                    <button type="button" class="sfls-file-type-tab active" data-type="word">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e( 'Word Document (.docx)', 'swiftlms' ); ?>
                    </button>
                    <button type="button" class="sfls-file-type-tab" data-type="excel">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php esc_html_e( 'Excel/CSV (.xlsx, .csv)', 'swiftlms' ); ?>
                    </button>
                </div>

                <!-- Word Upload Section -->
                <div id="sfls-upload-word" class="sfls-upload-type-content active">
                    <div class="sfls-dropzone" id="sfls-word-dropzone">
                        <div class="sfls-dropzone-content">
                            <span class="dashicons dashicons-upload"></span>
                            <p><?php esc_html_e( 'Drag & drop your Word document here', 'swiftlms' ); ?></p>
                            <p class="sfls-dropzone-hint"><?php esc_html_e( 'or click to browse', 'swiftlms' ); ?></p>
                            <input type="file" id="sfls-word-file" accept=".docx,.doc" style="display: none;">
                        </div>
                        <div class="sfls-dropzone-loading" style="display: none;">
                            <div class="sfls-loading-spinner"></div>
                            <p><?php esc_html_e( 'Processing document...', 'swiftlms' ); ?></p>
                        </div>
                    </div>

                    <div class="sfls-file-info" id="sfls-word-info" style="display: none;">
                        <div class="sfls-file-info-header">
                            <span class="dashicons dashicons-media-document"></span>
                            <span class="sfls-file-name"></span>
                            <button type="button" class="sfls-file-remove" title="<?php esc_attr_e( 'Remove', 'swiftlms' ); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Word Preview Section -->
                    <div id="sfls-word-preview" class="sfls-word-preview" style="display: none;">
                        <h4><?php esc_html_e( 'Document Preview', 'swiftlms' ); ?></h4>
                        <div id="sfls-word-preview-content"></div>

                        <!-- Import Options -->
                        <div class="sfls-import-options">
                            <h4><?php esc_html_e( 'Import Options', 'swiftlms' ); ?></h4>

                            <div class="sfls-options-grid">
                                <div class="sfls-option-field">
                                    <label for="word_split_by"><?php esc_html_e( 'Split Lessons By', 'swiftlms' ); ?></label>
                                    <select id="word_split_by" name="word_split_by">
                                        <option value="heading_1"><?php esc_html_e( 'Heading 1 (H1)', 'swiftlms' ); ?></option>
                                        <option value="heading_2"><?php esc_html_e( 'Heading 2 (H2)', 'swiftlms' ); ?></option>
                                        <option value="heading_3"><?php esc_html_e( 'Heading 3 (H3)', 'swiftlms' ); ?></option>
                                    </select>
                                </div>

                                <div class="sfls-option-field">
                                    <label for="word_course_status"><?php esc_html_e( 'Course Status', 'swiftlms' ); ?></label>
                                    <select id="word_course_status" name="word_course_status">
                                        <option value="draft"><?php esc_html_e( 'Draft', 'swiftlms' ); ?></option>
                                        <option value="publish"><?php esc_html_e( 'Published', 'swiftlms' ); ?></option>
                                    </select>
                                </div>

                                <div class="sfls-option-field sfls-checkbox-field">
                                    <label>
                                        <input type="checkbox" id="word_import_images" name="word_import_images" checked>
                                        <?php esc_html_e( 'Import images to Media Library', 'swiftlms' ); ?>
                                    </label>
                                </div>

                                <div class="sfls-option-field sfls-checkbox-field">
                                    <label>
                                        <input type="checkbox" id="word_create_quizzes" name="word_create_quizzes" checked>
                                        <?php esc_html_e( 'Create quizzes from [QUIZ] markers', 'swiftlms' ); ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="sfls-import-actions">
                            <button type="button" id="sfls-word-import-btn" class="button button-primary button-hero">
                                <?php esc_html_e( 'Import as Course', 'swiftlms' ); ?>
                            </button>
                            <button type="button" id="sfls-word-cancel-btn" class="button">
                                <?php esc_html_e( 'Cancel', 'swiftlms' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Import Progress -->
                    <div id="sfls-word-progress" class="sfls-import-progress" style="display: none;">
                        <div class="sfls-progress-spinner"></div>
                        <p class="sfls-progress-text"><?php esc_html_e( 'Importing document...', 'swiftlms' ); ?></p>
                    </div>

                    <!-- Import Result -->
                    <div id="sfls-word-result" class="sfls-import-result" style="display: none;"></div>
                </div>

                <!-- Excel Upload Section -->
                <div id="sfls-upload-excel" class="sfls-upload-type-content">
                    <div class="sfls-dropzone" id="sfls-excel-dropzone">
                        <div class="sfls-dropzone-content">
                            <span class="dashicons dashicons-upload"></span>
                            <p><?php esc_html_e( 'Drag & drop your Excel or CSV file here', 'swiftlms' ); ?></p>
                            <p class="sfls-dropzone-hint"><?php esc_html_e( 'or click to browse', 'swiftlms' ); ?></p>
                            <input type="file" id="sfls-excel-file" accept=".xlsx,.xls,.csv" style="display: none;">
                        </div>
                        <div class="sfls-dropzone-loading" style="display: none;">
                            <div class="sfls-loading-spinner"></div>
                            <p><?php esc_html_e( 'Processing spreadsheet...', 'swiftlms' ); ?></p>
                        </div>
                    </div>

                    <div class="sfls-file-info" id="sfls-excel-info" style="display: none;">
                        <div class="sfls-file-info-header">
                            <span class="dashicons dashicons-media-spreadsheet"></span>
                            <span class="sfls-file-name"></span>
                            <button type="button" class="sfls-file-remove" title="<?php esc_attr_e( 'Remove', 'swiftlms' ); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Excel Config -->
                    <div id="sfls-excel-config" class="sfls-excel-config" style="display: none;">
                        <div class="sfls-options-grid">
                            <div class="sfls-option-field" id="sfls-excel-sheet-field" style="display: none;">
                                <label for="sfls-excel-sheet"><?php esc_html_e( 'Select Sheet', 'swiftlms' ); ?></label>
                                <select id="sfls-excel-sheet"></select>
                            </div>

                            <div class="sfls-option-field">
                                <label for="sfls-excel-import-type"><?php esc_html_e( 'Import Type', 'swiftlms' ); ?></label>
                                <select id="sfls-excel-import-type">
                                    <option value=""><?php esc_html_e( '— Select import type —', 'swiftlms' ); ?></option>
                                    <?php foreach ( $import_types as $type => $config ) : ?>
                                        <option value="<?php echo esc_attr( $type ); ?>">
                                            <?php echo esc_html( $config['label'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="sfls-sheets-actions">
                            <button type="button" id="sfls-excel-preview-btn" class="button" disabled>
                                <?php esc_html_e( 'Preview Data', 'swiftlms' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Column Mapping -->
                    <div id="sfls-excel-mapping" class="sfls-column-mapping" style="display: none;">
                        <h4><?php esc_html_e( 'Column Mapping', 'swiftlms' ); ?></h4>
                        <p class="sfls-mapping-hint">
                            <?php esc_html_e( 'Map your spreadsheet columns to the required fields. Required fields are marked with *.', 'swiftlms' ); ?>
                        </p>
                        <div id="sfls-excel-mapping-fields" class="sfls-mapping-fields"></div>
                    </div>

                    <!-- Data Preview -->
                    <div id="sfls-excel-preview" class="sfls-sheets-preview" style="display: none;">
                        <h4><?php esc_html_e( 'Data Preview', 'swiftlms' ); ?></h4>
                        <div class="sfls-validation-summary"></div>
                        <div class="sfls-preview-table-wrap">
                            <table id="sfls-excel-preview-table" class="widefat striped"></table>
                        </div>

                        <div class="sfls-import-options">
                            <h4><?php esc_html_e( 'Import Options', 'swiftlms' ); ?></h4>
                            <div class="sfls-options-grid" id="sfls-excel-options"></div>
                        </div>

                        <div class="sfls-import-actions">
                            <button type="button" id="sfls-excel-import-btn" class="button button-primary button-hero">
                                <?php esc_html_e( 'Import Data', 'swiftlms' ); ?>
                            </button>
                            <button type="button" id="sfls-excel-cancel-btn" class="button">
                                <?php esc_html_e( 'Cancel', 'swiftlms' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Import Progress -->
                    <div id="sfls-excel-progress" class="sfls-import-progress" style="display: none;">
                        <div class="sfls-progress-spinner"></div>
                        <p class="sfls-progress-text"><?php esc_html_e( 'Importing data...', 'swiftlms' ); ?></p>
                    </div>

                    <!-- Import Result -->
                    <div id="sfls-excel-result" class="sfls-import-result" style="display: none;"></div>
                </div>
            </div>
        </div>

        <!-- File Upload Help -->
        <div class="sfls-gdocs-card sfls-gdocs-help">
            <div class="sfls-gdocs-card-header">
                <h2><?php esc_html_e( 'File Upload Guide', 'swiftlms' ); ?></h2>
            </div>
            <div class="sfls-gdocs-card-body">
                <div class="sfls-help-columns">
                    <div class="sfls-help-column">
                        <h4><?php esc_html_e( 'Word Documents', 'swiftlms' ); ?></h4>
                        <ul>
                            <li><?php esc_html_e( 'Supports .docx format', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Headings become lessons', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Images are imported', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Formatting preserved', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Tables supported', 'swiftlms' ); ?></li>
                        </ul>
                    </div>

                    <div class="sfls-help-column">
                        <h4><?php esc_html_e( 'Excel/CSV Files', 'swiftlms' ); ?></h4>
                        <ul>
                            <li><?php esc_html_e( 'Supports .xlsx and .csv', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Bulk import students', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Import enrollments', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Create courses in bulk', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Import quiz questions', 'swiftlms' ); ?></li>
                        </ul>
                    </div>

                    <div class="sfls-help-column">
                        <h4><?php esc_html_e( 'Tips', 'swiftlms' ); ?></h4>
                        <ul>
                            <li><?php esc_html_e( 'Max file size: 50MB', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'First row = headers', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Preview before importing', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Use [QUIZ] markers in Word', 'swiftlms' ); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Google Sheets import section.
     */
    private function render_sheets_import_section(): void {
        $import_types = Sheets_Parser::get_all_import_types();
        ?>
        <div class="sfls-gdocs-card sfls-sheets-import">
            <div class="sfls-gdocs-card-header">
                <h2><?php esc_html_e( 'Bulk Import from Google Sheets', 'swiftlms' ); ?></h2>
            </div>
            <div class="sfls-gdocs-card-body">
                <!-- Spreadsheet URL Input -->
                <div class="sfls-sheets-url-input">
                    <label for="sfls-sheets-url"><?php esc_html_e( 'Google Sheets URL', 'swiftlms' ); ?></label>
                    <div class="sfls-url-input">
                        <input type="url" id="sfls-sheets-url" placeholder="<?php esc_attr_e( 'Paste Google Sheets URL here...', 'swiftlms' ); ?>">
                        <button type="button" id="sfls-load-sheets-btn" class="button button-primary">
                            <?php esc_html_e( 'Load Spreadsheet', 'swiftlms' ); ?>
                        </button>
                    </div>
                    <p class="sfls-url-hint">
                        <?php esc_html_e( 'Example: https://docs.google.com/spreadsheets/d/abc123/edit', 'swiftlms' ); ?>
                    </p>
                </div>

                <!-- Spreadsheet Loaded -->
                <div id="sfls-sheets-config" class="sfls-sheets-config" style="display: none;">
                    <div class="sfls-sheets-header">
                        <h3 id="sfls-sheets-title"></h3>
                        <button type="button" id="sfls-sheets-change" class="button button-small">
                            <?php esc_html_e( 'Change', 'swiftlms' ); ?>
                        </button>
                    </div>

                    <div class="sfls-options-grid">
                        <div class="sfls-option-field">
                            <label for="sfls-sheet-select"><?php esc_html_e( 'Select Sheet', 'swiftlms' ); ?></label>
                            <select id="sfls-sheet-select"></select>
                        </div>

                        <div class="sfls-option-field">
                            <label for="sfls-import-type"><?php esc_html_e( 'Import Type', 'swiftlms' ); ?></label>
                            <select id="sfls-import-type">
                                <option value=""><?php esc_html_e( '— Select import type —', 'swiftlms' ); ?></option>
                                <?php foreach ( $import_types as $type => $config ) : ?>
                                    <option value="<?php echo esc_attr( $type ); ?>">
                                        <?php echo esc_html( $config['label'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="sfls-sheets-actions">
                        <button type="button" id="sfls-sheets-preview-btn" class="button" disabled>
                            <?php esc_html_e( 'Preview Data', 'swiftlms' ); ?>
                        </button>
                        <a href="#" id="sfls-download-template" class="button" style="display:none;">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e( 'Download Template', 'swiftlms' ); ?>
                        </a>
                    </div>
                </div>

                <!-- Column Mapping -->
                <div id="sfls-column-mapping" class="sfls-column-mapping" style="display: none;">
                    <h4><?php esc_html_e( 'Column Mapping', 'swiftlms' ); ?></h4>
                    <p class="sfls-mapping-hint">
                        <?php esc_html_e( 'Map your spreadsheet columns to the required fields. Required fields are marked with *.', 'swiftlms' ); ?>
                    </p>
                    <div id="sfls-mapping-fields" class="sfls-mapping-fields"></div>
                </div>

                <!-- Data Preview -->
                <div id="sfls-sheets-preview" class="sfls-sheets-preview" style="display: none;">
                    <h4><?php esc_html_e( 'Data Preview', 'swiftlms' ); ?></h4>
                    <div class="sfls-validation-summary"></div>
                    <div class="sfls-preview-table-wrap">
                        <table id="sfls-preview-table" class="widefat striped"></table>
                    </div>

                    <div class="sfls-import-options">
                        <h4><?php esc_html_e( 'Import Options', 'swiftlms' ); ?></h4>
                        <div class="sfls-options-grid" id="sfls-sheets-options">
                            <!-- Options populated by JS based on import type -->
                        </div>
                    </div>

                    <div class="sfls-import-actions">
                        <button type="button" id="sfls-sheets-import-btn" class="button button-primary button-hero">
                            <?php esc_html_e( 'Import Data', 'swiftlms' ); ?>
                        </button>
                        <button type="button" id="sfls-sheets-cancel-btn" class="button">
                            <?php esc_html_e( 'Cancel', 'swiftlms' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Import Progress -->
                <div id="sfls-sheets-progress" class="sfls-import-progress" style="display: none;">
                    <div class="sfls-progress-spinner"></div>
                    <p class="sfls-progress-text"><?php esc_html_e( 'Importing data...', 'swiftlms' ); ?></p>
                </div>

                <!-- Import Result -->
                <div id="sfls-sheets-result" class="sfls-import-result" style="display: none;"></div>
            </div>
        </div>

        <!-- Sheets Help Section -->
        <div class="sfls-gdocs-card sfls-gdocs-help">
            <div class="sfls-gdocs-card-header">
                <h2><?php esc_html_e( 'Bulk Import Guide', 'swiftlms' ); ?></h2>
            </div>
            <div class="sfls-gdocs-card-body">
                <div class="sfls-help-columns">
                    <div class="sfls-help-column">
                        <h4><?php esc_html_e( 'Available Import Types', 'swiftlms' ); ?></h4>
                        <ul>
                            <li><strong><?php esc_html_e( 'Students', 'swiftlms' ); ?></strong> — <?php esc_html_e( 'Import user accounts', 'swiftlms' ); ?></li>
                            <li><strong><?php esc_html_e( 'Enrollments', 'swiftlms' ); ?></strong> — <?php esc_html_e( 'Enroll students in courses', 'swiftlms' ); ?></li>
                            <li><strong><?php esc_html_e( 'Courses', 'swiftlms' ); ?></strong> — <?php esc_html_e( 'Create courses', 'swiftlms' ); ?></li>
                            <li><strong><?php esc_html_e( 'Lessons', 'swiftlms' ); ?></strong> — <?php esc_html_e( 'Add lessons to courses', 'swiftlms' ); ?></li>
                            <li><strong><?php esc_html_e( 'Quiz Questions', 'swiftlms' ); ?></strong> — <?php esc_html_e( 'Import quiz questions', 'swiftlms' ); ?></li>
                            <li><strong><?php esc_html_e( 'Coupons', 'swiftlms' ); ?></strong> — <?php esc_html_e( 'Create discount coupons', 'swiftlms' ); ?></li>
                        </ul>
                    </div>

                    <div class="sfls-help-column">
                        <h4><?php esc_html_e( 'Getting Started', 'swiftlms' ); ?></h4>
                        <ol>
                            <li><?php esc_html_e( 'Download a template for your import type', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Fill in your data in Google Sheets', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Paste the spreadsheet URL above', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Map columns and preview data', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Import!', 'swiftlms' ); ?></li>
                        </ol>
                    </div>

                    <div class="sfls-help-column">
                        <h4><?php esc_html_e( 'Tips', 'swiftlms' ); ?></h4>
                        <ul>
                            <li><?php esc_html_e( 'First row should contain headers', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Use common header names for auto-mapping', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Check validation before importing', 'swiftlms' ); ?></li>
                            <li><?php esc_html_e( 'Test with a few rows first', 'swiftlms' ); ?></li>
                        </ul>
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
            var importBtn = '<a href="<?php echo esc_url( admin_url( 'admin.php?page=sfls-google-docs-import' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Import from Google', 'swiftlms' ); ?></a>';
            $('.page-title-action').after(importBtn);
        });
        </script>
        <?php
    }

    /**
     * AJAX: Load spreadsheet.
     */
    public function ajax_load_spreadsheet(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
        $spreadsheet_id = Sheets_Parser::extract_spreadsheet_id( $url );

        if ( ! $spreadsheet_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid spreadsheet URL.', 'swiftlms' ) ) );
        }

        $metadata = Sheets_Parser::get_sheets_list( $spreadsheet_id );

        if ( is_wp_error( $metadata ) ) {
            wp_send_json_error( array( 'message' => $metadata->get_error_message() ) );
        }

        wp_send_json_success( array(
            'spreadsheet_id' => $spreadsheet_id,
            'title'          => $metadata['title'],
            'sheets'         => $metadata['sheets'],
            'import_types'   => Sheets_Parser::get_all_import_types(),
        ) );
    }

    /**
     * AJAX: Preview sheet data.
     */
    public function ajax_preview_sheet(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $spreadsheet_id = isset( $_POST['spreadsheet_id'] ) ? sanitize_text_field( $_POST['spreadsheet_id'] ) : '';
        $sheet_name = isset( $_POST['sheet_name'] ) ? sanitize_text_field( $_POST['sheet_name'] ) : '';
        $import_type = isset( $_POST['import_type'] ) ? sanitize_text_field( $_POST['import_type'] ) : '';

        if ( empty( $spreadsheet_id ) || empty( $import_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'swiftlms' ) ) );
        }

        // Get sheet data.
        $range = $sheet_name ? $sheet_name . '!A1:Z1000' : 'A1:Z1000';
        $raw_data = Sheets_Parser::get_spreadsheet_data( $spreadsheet_id, $range );

        if ( is_wp_error( $raw_data ) ) {
            wp_send_json_error( array( 'message' => $raw_data->get_error_message() ) );
        }

        $values = $raw_data['values'] ?? array();
        if ( empty( $values ) ) {
            wp_send_json_error( array( 'message' => __( 'Sheet is empty.', 'swiftlms' ) ) );
        }

        $headers = $values[0];
        $column_map = Sheets_Parser::auto_detect_columns( $headers, $import_type );
        $parsed = Sheets_Parser::parse_sheet_data( $raw_data, $column_map );
        $validation = Sheets_Parser::validate_data( $parsed, $import_type );

        wp_send_json_success( array(
            'headers'     => $headers,
            'column_map'  => $column_map,
            'sample_rows' => array_slice( $parsed, 0, 5 ),
            'validation'  => $validation,
            'type_config' => Sheets_Parser::get_import_type_config( $import_type ),
        ) );
    }

    /**
     * AJAX: Import sheet data.
     */
    public function ajax_import_sheet(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $spreadsheet_id = isset( $_POST['spreadsheet_id'] ) ? sanitize_text_field( $_POST['spreadsheet_id'] ) : '';
        $sheet_name = isset( $_POST['sheet_name'] ) ? sanitize_text_field( $_POST['sheet_name'] ) : '';
        $import_type = isset( $_POST['import_type'] ) ? sanitize_text_field( $_POST['import_type'] ) : '';
        $column_map = isset( $_POST['column_map'] ) ? json_decode( stripslashes( $_POST['column_map'] ), true ) : array();
        $options = isset( $_POST['options'] ) ? json_decode( stripslashes( $_POST['options'] ), true ) : array();

        if ( empty( $spreadsheet_id ) || empty( $import_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required parameters.', 'swiftlms' ) ) );
        }

        // Get sheet data.
        $range = $sheet_name ? $sheet_name . '!A1:Z1000' : 'A1:Z1000';
        $raw_data = Sheets_Parser::get_spreadsheet_data( $spreadsheet_id, $range );

        if ( is_wp_error( $raw_data ) ) {
            wp_send_json_error( array( 'message' => $raw_data->get_error_message() ) );
        }

        // Parse data.
        $parsed = Sheets_Parser::parse_sheet_data( $raw_data, $column_map );
        $validation = Sheets_Parser::validate_data( $parsed, $import_type );

        if ( empty( $validation['valid_rows'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'No valid data to import.', 'swiftlms' ),
                'errors'  => $validation['errors'],
            ) );
        }

        // Import.
        $importer = new Sheets_Importer( $validation['valid_rows'], $import_type, $options );
        $result = $importer->import();

        wp_send_json_success( array(
            'message' => sprintf(
                __( 'Import complete: %d created, %d updated, %d skipped, %d errors.', 'swiftlms' ),
                $result['stats']['created'],
                $result['stats']['updated'],
                $result['stats']['skipped'],
                $result['stats']['errors']
            ),
            'stats'   => $result['stats'],
            'log'     => $result['log'],
        ) );
    }

    /**
     * AJAX: Download template CSV.
     */
    public function ajax_download_template(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        $import_type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';

        if ( empty( $import_type ) ) {
            wp_die( 'Invalid import type' );
        }

        $csv = Sheets_Parser::generate_template( $import_type );
        $filename = 'swiftlms-' . $import_type . '-template.csv';

        header( 'Content-Type: text/csv' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        echo $csv;
        exit;
    }

    /**
     * AJAX: Save preset.
     */
    public function ajax_save_preset(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $id = isset( $_POST['preset_id'] ) ? sanitize_key( $_POST['preset_id'] ) : '';
        $name = isset( $_POST['preset_name'] ) ? sanitize_text_field( $_POST['preset_name'] ) : '';
        $desc = isset( $_POST['preset_desc'] ) ? sanitize_text_field( $_POST['preset_desc'] ) : '';
        $options = isset( $_POST['options'] ) ? json_decode( stripslashes( $_POST['options'] ), true ) : array();

        if ( empty( $id ) || empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Preset ID and name are required.', 'swiftlms' ) ) );
        }

        $validated_options = Parsing_Options::validate_options( $options );
        Parsing_Options::save_preset( $id, $name, $desc, $validated_options );

        wp_send_json_success( array( 'message' => __( 'Preset saved.', 'swiftlms' ) ) );
    }

    /**
     * AJAX: Delete preset.
     */
    public function ajax_delete_preset(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $id = isset( $_POST['preset_id'] ) ? sanitize_key( $_POST['preset_id'] ) : '';

        if ( empty( $id ) ) {
            wp_send_json_error( array( 'message' => __( 'Preset ID is required.', 'swiftlms' ) ) );
        }

        Parsing_Options::delete_preset( $id );

        wp_send_json_success( array( 'message' => __( 'Preset deleted.', 'swiftlms' ) ) );
    }

    /**
     * AJAX: Load preset options.
     */
    public function ajax_load_preset(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        $id = isset( $_POST['preset_id'] ) ? sanitize_key( $_POST['preset_id'] ) : '';

        if ( empty( $id ) ) {
            wp_send_json_error( array( 'message' => __( 'Preset ID is required.', 'swiftlms' ) ) );
        }

        $preset = Parsing_Options::get_preset( $id );

        if ( ! $preset ) {
            wp_send_json_error( array( 'message' => __( 'Preset not found.', 'swiftlms' ) ) );
        }

        wp_send_json_success( array(
            'preset'  => $preset,
            'options' => Parsing_Options::get_preset_options( $id ),
        ) );
    }

    /**
     * AJAX: Handle file upload.
     */
    public function ajax_file_upload(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'swiftlms' ) ) );
        }

        $file = $_FILES['file'];
        $file_type = isset( $_POST['file_type'] ) ? sanitize_text_field( $_POST['file_type'] ) : '';

        // Validate file.
        $allowed_types = array(
            'word'  => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword' ),
            'excel' => array(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'text/csv',
                'text/plain',
            ),
        );

        if ( ! isset( $allowed_types[ $file_type ] ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid file type.', 'swiftlms' ) ) );
        }

        // Check MIME type.
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        // Also check extension as fallback.
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $valid_exts = array(
            'word'  => array( 'docx', 'doc' ),
            'excel' => array( 'xlsx', 'xls', 'csv' ),
        );

        if ( ! in_array( $ext, $valid_exts[ $file_type ], true ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid file extension.', 'swiftlms' ) ) );
        }

        // Move to temp directory.
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/swiftlms-temp';
        if ( ! file_exists( $temp_dir ) ) {
            wp_mkdir_p( $temp_dir );
        }

        $temp_file = $temp_dir . '/' . wp_generate_uuid4() . '.' . $ext;
        if ( ! move_uploaded_file( $file['tmp_name'], $temp_file ) ) {
            wp_send_json_error( array( 'message' => __( 'Failed to save uploaded file.', 'swiftlms' ) ) );
        }

        // Store in transient for later use.
        $file_key = wp_generate_uuid4();
        set_transient( 'sfls_upload_' . $file_key, array(
            'path'      => $temp_file,
            'name'      => $file['name'],
            'type'      => $file_type,
            'extension' => $ext,
        ), HOUR_IN_SECONDS );

        wp_send_json_success( array(
            'file_key'  => $file_key,
            'file_name' => $file['name'],
            'file_type' => $file_type,
            'extension' => $ext,
        ) );
    }

    /**
     * AJAX: Preview uploaded file.
     */
    public function ajax_file_preview(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $file_key = isset( $_POST['file_key'] ) ? sanitize_text_field( $_POST['file_key'] ) : '';
        $file_data = get_transient( 'sfls_upload_' . $file_key );

        if ( ! $file_data || ! file_exists( $file_data['path'] ) ) {
            wp_send_json_error( array( 'message' => __( 'File not found. Please upload again.', 'swiftlms' ) ) );
        }

        if ( 'word' === $file_data['type'] ) {
            $this->preview_word_file( $file_data );
        } else {
            $this->preview_excel_file( $file_data, $_POST );
        }
    }

    /**
     * Preview Word file.
     *
     * @param array $file_data File data.
     */
    private function preview_word_file( array $file_data ): void {
        $split_by = isset( $_POST['split_by'] ) ? sanitize_text_field( $_POST['split_by'] ) : 'heading_1';

        $parser = new Word_Parser( $file_data['path'] );
        $parsed = $parser->parse( array(
            'split_by'       => $split_by,
            'import_images'  => false,
            'create_quizzes' => true,
        ) );

        if ( is_wp_error( $parsed ) ) {
            wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
        }

        // Build preview similar to Google Docs.
        $preview = array(
            'course'  => $parsed['course'],
            'totals'  => array(
                'lessons' => count( $parsed['lessons'] ),
                'quizzes' => count( $parsed['quizzes'] ),
            ),
            'lessons' => array_map( function( $lesson ) {
                return array(
                    'title'      => $lesson['title'],
                    'type'       => 'lesson',
                    'word_count' => $lesson['word_count'],
                    'has_images' => $lesson['has_images'],
                    'has_video'  => $lesson['has_video'],
                );
            }, $parsed['lessons'] ),
            'quizzes' => array_map( function( $quiz ) {
                return array(
                    'title'          => $quiz['title'],
                    'question_count' => count( $quiz['questions'] ),
                );
            }, $parsed['quizzes'] ),
        );

        wp_send_json_success( array(
            'title'     => $parsed['course']['title'] ?: $file_data['name'],
            'preview'   => $preview,
            'structure' => $parser->get_structure_preview(),
            'stats'     => array(
                'word_count'     => $parsed['word_count'],
                'heading_counts' => $parser->get_heading_counts(),
            ),
        ) );
    }

    /**
     * Preview Excel file.
     *
     * @param array $file_data File data.
     * @param array $post_data POST data.
     */
    private function preview_excel_file( array $file_data, array $post_data ): void {
        $import_type = isset( $post_data['import_type'] ) ? sanitize_text_field( $post_data['import_type'] ) : '';
        $sheet_index = isset( $post_data['sheet_index'] ) ? (int) $post_data['sheet_index'] : 0;

        if ( empty( $import_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Please select an import type.', 'swiftlms' ) ) );
        }

        // Read file based on extension.
        if ( 'csv' === $file_data['extension'] ) {
            $raw_data = Excel_Parser::read_csv( $file_data['path'] );
        } else {
            $parser = new Excel_Parser( $file_data['path'] );
            $result = $parser->open();

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }

            $raw_data = $parser->read_sheet( $sheet_index );
            $parser->close();
        }

        if ( is_wp_error( $raw_data ) ) {
            wp_send_json_error( array( 'message' => $raw_data->get_error_message() ) );
        }

        $values = $raw_data['values'] ?? array();
        if ( empty( $values ) ) {
            wp_send_json_error( array( 'message' => __( 'File is empty.', 'swiftlms' ) ) );
        }

        $headers = $values[0];
        $column_map = Excel_Parser::auto_detect_columns( $headers, $import_type );
        $parsed = Excel_Parser::parse_sheet_data( $raw_data, $column_map );
        $validation = Excel_Parser::validate_data( $parsed, $import_type );

        wp_send_json_success( array(
            'headers'     => $headers,
            'column_map'  => $column_map,
            'sample_rows' => array_slice( $parsed, 0, 5 ),
            'validation'  => $validation,
            'type_config' => Sheets_Parser::get_import_type_config( $import_type ),
        ) );
    }

    /**
     * AJAX: Import uploaded file.
     */
    public function ajax_file_import(): void {
        check_ajax_referer( 'sfls_gdocs_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'swiftlms' ) ) );
        }

        $file_key = isset( $_POST['file_key'] ) ? sanitize_text_field( $_POST['file_key'] ) : '';
        $file_data = get_transient( 'sfls_upload_' . $file_key );

        if ( ! $file_data || ! file_exists( $file_data['path'] ) ) {
            wp_send_json_error( array( 'message' => __( 'File not found. Please upload again.', 'swiftlms' ) ) );
        }

        if ( 'word' === $file_data['type'] ) {
            $this->import_word_file( $file_data, $file_key );
        } else {
            $this->import_excel_file( $file_data, $file_key );
        }
    }

    /**
     * Import Word file.
     *
     * @param array  $file_data File data.
     * @param string $file_key  File key.
     */
    private function import_word_file( array $file_data, string $file_key ): void {
        $options = array(
            'split_by'        => isset( $_POST['split_by'] ) ? sanitize_text_field( $_POST['split_by'] ) : 'heading_1',
            'import_images'   => ! empty( $_POST['import_images'] ),
            'create_quizzes'  => ! empty( $_POST['create_quizzes'] ),
            'preserve_styles' => true,
        );

        $parser = new Word_Parser( $file_data['path'] );
        $parsed = $parser->parse( $options );

        if ( is_wp_error( $parsed ) ) {
            wp_send_json_error( array( 'message' => $parsed->get_error_message() ) );
        }

        // Import using Course_Importer.
        $importer = new Course_Importer( $parsed, array(
            'course_status'  => isset( $_POST['course_status'] ) ? sanitize_text_field( $_POST['course_status'] ) : 'draft',
            'import_quizzes' => $options['create_quizzes'],
        ) );

        $result = $importer->import();

        // Cleanup temp file.
        @unlink( $file_data['path'] );
        delete_transient( 'sfls_upload_' . $file_key );

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
     * Import Excel file.
     *
     * @param array  $file_data File data.
     * @param string $file_key  File key.
     */
    private function import_excel_file( array $file_data, string $file_key ): void {
        $import_type = isset( $_POST['import_type'] ) ? sanitize_text_field( $_POST['import_type'] ) : '';
        $sheet_index = isset( $_POST['sheet_index'] ) ? (int) $_POST['sheet_index'] : 0;
        $column_map = isset( $_POST['column_map'] ) ? json_decode( stripslashes( $_POST['column_map'] ), true ) : array();
        $options = isset( $_POST['options'] ) ? json_decode( stripslashes( $_POST['options'] ), true ) : array();

        if ( empty( $import_type ) ) {
            wp_send_json_error( array( 'message' => __( 'Import type is required.', 'swiftlms' ) ) );
        }

        // Read file.
        if ( 'csv' === $file_data['extension'] ) {
            $raw_data = Excel_Parser::read_csv( $file_data['path'] );
        } else {
            $parser = new Excel_Parser( $file_data['path'] );
            $result = $parser->open();

            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            }

            $raw_data = $parser->read_sheet( $sheet_index );
            $parser->close();
        }

        if ( is_wp_error( $raw_data ) ) {
            wp_send_json_error( array( 'message' => $raw_data->get_error_message() ) );
        }

        // Parse and validate.
        $parsed = Excel_Parser::parse_sheet_data( $raw_data, $column_map );
        $validation = Excel_Parser::validate_data( $parsed, $import_type );

        if ( empty( $validation['valid_rows'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'No valid data to import.', 'swiftlms' ),
                'errors'  => $validation['errors'],
            ) );
        }

        // Import.
        $importer = new Sheets_Importer( $validation['valid_rows'], $import_type, $options );
        $result = $importer->import();

        // Cleanup temp file.
        @unlink( $file_data['path'] );
        delete_transient( 'sfls_upload_' . $file_key );

        wp_send_json_success( array(
            'message' => sprintf(
                __( 'Import complete: %d created, %d updated, %d skipped, %d errors.', 'swiftlms' ),
                $result['stats']['created'],
                $result['stats']['updated'],
                $result['stats']['skipped'],
                $result['stats']['errors']
            ),
            'stats' => $result['stats'],
            'log'   => $result['log'],
        ) );
    }
}
