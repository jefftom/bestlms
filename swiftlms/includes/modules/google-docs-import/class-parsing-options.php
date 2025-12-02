<?php
/**
 * Advanced Parsing Options
 *
 * @package SwiftLMS\Modules\GoogleDocsImport
 */

namespace SwiftLMS\Modules\GoogleDocsImport;

defined( 'ABSPATH' ) || exit;

/**
 * Parsing_Options class.
 *
 * Configurable parsing options for Google Docs import.
 */
class Parsing_Options {

    /**
     * Option key for saved presets.
     */
    const OPTION_PRESETS = 'swiftlms_import_presets';

    /**
     * Default parsing options.
     *
     * @var array
     */
    private static $defaults = array(
        // Content splitting.
        'split_method'       => 'heading',      // heading, page_break, marker, manual.
        'split_heading'      => 'HEADING_1',    // HEADING_1, HEADING_2, HEADING_3.
        'split_marker'       => '---LESSON---', // Custom marker for manual splits.

        // Section handling.
        'create_sections'    => false,
        'section_heading'    => 'HEADING_1',    // Which heading creates sections.
        'lesson_heading'     => 'HEADING_2',    // Which heading creates lessons.

        // Content processing.
        'preserve_styles'    => true,
        'convert_links'      => true,
        'import_images'      => true,
        'image_alignment'    => 'center',       // left, center, right, none.
        'convert_tables'     => true,
        'convert_lists'      => true,

        // Video handling.
        'detect_videos'      => true,
        'video_marker'       => '[VIDEO]',
        'auto_video_lesson'  => true,           // Set lesson type to video if contains video.

        // Callout boxes.
        'convert_callouts'   => true,
        'callout_markers'    => array(
            'note'    => '[NOTE]',
            'tip'     => '[TIP]',
            'warning' => '[WARNING]',
            'info'    => '[INFO]',
            'example' => '[EXAMPLE]',
        ),

        // Quiz parsing.
        'parse_quizzes'      => true,
        'quiz_marker'        => '[QUIZ]',
        'question_marker'    => '[Q]',
        'answer_marker'      => '[A]',
        'correct_marker'     => '[A*]',         // Asterisk marks correct.
        'quiz_per_lesson'    => true,           // Create quiz for each lesson with questions.

        // Assignment parsing.
        'parse_assignments'  => true,
        'assignment_marker'  => '[ASSIGNMENT]',
        'assignment_type'    => 'text_entry',

        // Advanced markers.
        'custom_markers'     => array(),        // User-defined markers.
        'code_block_marker'  => '[CODE]',
        'embed_marker'       => '[EMBED]',
        'download_marker'    => '[DOWNLOAD]',

        // Meta extraction.
        'extract_meta'       => true,
        'meta_markers'       => array(
            'duration'   => '[DURATION]',
            'difficulty' => '[DIFFICULTY]',
            'objectives' => '[OBJECTIVES]',
            'prereqs'    => '[PREREQUISITES]',
        ),

        // Text processing.
        'smart_quotes'       => true,
        'auto_paragraphs'    => true,
        'strip_empty'        => true,
        'trim_whitespace'    => true,

        // Error handling.
        'skip_empty_lessons' => true,
        'min_lesson_words'   => 10,
        'require_content'    => false,
    );

    /**
     * Built-in presets.
     *
     * @var array
     */
    private static $builtin_presets = array(
        'simple' => array(
            'name'        => 'Simple Course',
            'description' => 'Basic import: H1 = Lessons, minimal processing',
            'options'     => array(
                'split_method'      => 'heading',
                'split_heading'     => 'HEADING_1',
                'create_sections'   => false,
                'parse_quizzes'     => false,
                'parse_assignments' => false,
                'convert_callouts'  => false,
            ),
        ),
        'structured' => array(
            'name'        => 'Structured Course',
            'description' => 'H1 = Sections, H2 = Lessons, with quizzes',
            'options'     => array(
                'split_method'     => 'heading',
                'create_sections'  => true,
                'section_heading'  => 'HEADING_1',
                'lesson_heading'   => 'HEADING_2',
                'parse_quizzes'    => true,
                'quiz_per_lesson'  => true,
            ),
        ),
        'video_course' => array(
            'name'        => 'Video Course',
            'description' => 'Optimized for video-based content with timestamps',
            'options'     => array(
                'detect_videos'     => true,
                'auto_video_lesson' => true,
                'video_marker'      => '[VIDEO]',
                'custom_markers'    => array(
                    'timestamp' => '[TIME]',
                    'resource'  => '[RESOURCE]',
                ),
            ),
        ),
        'assessment' => array(
            'name'        => 'Assessment Heavy',
            'description' => 'Course with lots of quizzes and assignments',
            'options'     => array(
                'parse_quizzes'      => true,
                'parse_assignments'  => true,
                'quiz_per_lesson'    => true,
                'assignment_marker'  => '[ASSIGNMENT]',
            ),
        ),
        'ebook' => array(
            'name'        => 'eBook Style',
            'description' => 'Long-form content, page breaks = chapters',
            'options'     => array(
                'split_method'     => 'page_break',
                'create_sections'  => true,
                'section_heading'  => 'HEADING_1',
                'preserve_styles'  => true,
                'parse_quizzes'    => false,
            ),
        ),
        'workshop' => array(
            'name'        => 'Workshop/Tutorial',
            'description' => 'Hands-on content with code blocks and examples',
            'options'     => array(
                'code_block_marker' => '[CODE]',
                'convert_callouts'  => true,
                'callout_markers'   => array(
                    'note'     => '[NOTE]',
                    'tip'      => '[TIP]',
                    'warning'  => '[WARNING]',
                    'exercise' => '[EXERCISE]',
                    'solution' => '[SOLUTION]',
                ),
            ),
        ),
    );

    /**
     * Get default options.
     *
     * @return array
     */
    public static function get_defaults(): array {
        return self::$defaults;
    }

    /**
     * Get merged options with defaults.
     *
     * @param array $options User options.
     * @return array
     */
    public static function merge_with_defaults( array $options ): array {
        return self::recursive_merge( self::$defaults, $options );
    }

    /**
     * Recursive array merge.
     *
     * @param array $defaults Default values.
     * @param array $options  User values.
     * @return array
     */
    private static function recursive_merge( array $defaults, array $options ): array {
        foreach ( $options as $key => $value ) {
            if ( is_array( $value ) && isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) ) {
                $defaults[ $key ] = self::recursive_merge( $defaults[ $key ], $value );
            } else {
                $defaults[ $key ] = $value;
            }
        }
        return $defaults;
    }

    /**
     * Get built-in presets.
     *
     * @return array
     */
    public static function get_builtin_presets(): array {
        return self::$builtin_presets;
    }

    /**
     * Get user-saved presets.
     *
     * @return array
     */
    public static function get_user_presets(): array {
        return get_option( self::OPTION_PRESETS, array() );
    }

    /**
     * Get all presets (built-in + user).
     *
     * @return array
     */
    public static function get_all_presets(): array {
        $builtin = array();
        foreach ( self::$builtin_presets as $id => $preset ) {
            $builtin[ $id ] = array_merge( $preset, array( 'builtin' => true ) );
        }

        $user = array();
        foreach ( self::get_user_presets() as $id => $preset ) {
            $user[ $id ] = array_merge( $preset, array( 'builtin' => false ) );
        }

        return array_merge( $builtin, $user );
    }

    /**
     * Get preset by ID.
     *
     * @param string $preset_id Preset ID.
     * @return array|null
     */
    public static function get_preset( string $preset_id ): ?array {
        $all = self::get_all_presets();
        return $all[ $preset_id ] ?? null;
    }

    /**
     * Get preset options.
     *
     * @param string $preset_id Preset ID.
     * @return array
     */
    public static function get_preset_options( string $preset_id ): array {
        $preset = self::get_preset( $preset_id );

        if ( ! $preset ) {
            return self::$defaults;
        }

        return self::merge_with_defaults( $preset['options'] ?? array() );
    }

    /**
     * Save user preset.
     *
     * @param string $id      Preset ID.
     * @param string $name    Preset name.
     * @param string $desc    Description.
     * @param array  $options Options.
     * @return bool
     */
    public static function save_preset( string $id, string $name, string $desc, array $options ): bool {
        $presets = self::get_user_presets();

        $presets[ sanitize_key( $id ) ] = array(
            'name'        => sanitize_text_field( $name ),
            'description' => sanitize_text_field( $desc ),
            'options'     => $options,
            'created'     => current_time( 'mysql' ),
        );

        return update_option( self::OPTION_PRESETS, $presets );
    }

    /**
     * Delete user preset.
     *
     * @param string $id Preset ID.
     * @return bool
     */
    public static function delete_preset( string $id ): bool {
        $presets = self::get_user_presets();

        if ( ! isset( $presets[ $id ] ) ) {
            return false;
        }

        unset( $presets[ $id ] );
        return update_option( self::OPTION_PRESETS, $presets );
    }

    /**
     * Validate options.
     *
     * @param array $options Options to validate.
     * @return array Validated options.
     */
    public static function validate_options( array $options ): array {
        $validated = array();

        // Split method.
        $valid_methods = array( 'heading', 'page_break', 'marker', 'manual' );
        $validated['split_method'] = in_array( $options['split_method'] ?? '', $valid_methods, true )
            ? $options['split_method']
            : 'heading';

        // Headings.
        $valid_headings = array( 'HEADING_1', 'HEADING_2', 'HEADING_3', 'HEADING_4' );
        foreach ( array( 'split_heading', 'section_heading', 'lesson_heading' ) as $key ) {
            $validated[ $key ] = in_array( $options[ $key ] ?? '', $valid_headings, true )
                ? $options[ $key ]
                : self::$defaults[ $key ];
        }

        // Booleans.
        $bool_keys = array(
            'create_sections', 'preserve_styles', 'convert_links', 'import_images',
            'convert_tables', 'convert_lists', 'detect_videos', 'auto_video_lesson',
            'convert_callouts', 'parse_quizzes', 'quiz_per_lesson', 'parse_assignments',
            'extract_meta', 'smart_quotes', 'auto_paragraphs', 'strip_empty',
            'trim_whitespace', 'skip_empty_lessons', 'require_content',
        );

        foreach ( $bool_keys as $key ) {
            $validated[ $key ] = ! empty( $options[ $key ] );
        }

        // Strings.
        $string_keys = array(
            'split_marker', 'video_marker', 'quiz_marker', 'question_marker',
            'answer_marker', 'correct_marker', 'assignment_marker', 'code_block_marker',
            'embed_marker', 'download_marker', 'image_alignment', 'assignment_type',
        );

        foreach ( $string_keys as $key ) {
            $validated[ $key ] = sanitize_text_field( $options[ $key ] ?? self::$defaults[ $key ] );
        }

        // Numbers.
        $validated['min_lesson_words'] = absint( $options['min_lesson_words'] ?? 10 );

        // Arrays (callout markers, custom markers, meta markers).
        foreach ( array( 'callout_markers', 'custom_markers', 'meta_markers' ) as $key ) {
            if ( isset( $options[ $key ] ) && is_array( $options[ $key ] ) ) {
                $validated[ $key ] = array_map( 'sanitize_text_field', $options[ $key ] );
            } else {
                $validated[ $key ] = self::$defaults[ $key ];
            }
        }

        return $validated;
    }

    /**
     * Get options form fields configuration.
     *
     * @return array
     */
    public static function get_form_fields(): array {
        return array(
            'content_splitting' => array(
                'title'  => __( 'Content Splitting', 'swiftlms' ),
                'fields' => array(
                    'split_method' => array(
                        'type'    => 'select',
                        'label'   => __( 'Split Lessons By', 'swiftlms' ),
                        'options' => array(
                            'heading'    => __( 'Headings', 'swiftlms' ),
                            'page_break' => __( 'Page Breaks', 'swiftlms' ),
                            'marker'     => __( 'Custom Marker', 'swiftlms' ),
                            'manual'     => __( 'Manual (No Auto-Split)', 'swiftlms' ),
                        ),
                    ),
                    'split_heading' => array(
                        'type'       => 'select',
                        'label'      => __( 'Split at Heading Level', 'swiftlms' ),
                        'options'    => array(
                            'HEADING_1' => __( 'Heading 1 (H1)', 'swiftlms' ),
                            'HEADING_2' => __( 'Heading 2 (H2)', 'swiftlms' ),
                            'HEADING_3' => __( 'Heading 3 (H3)', 'swiftlms' ),
                        ),
                        'depends_on' => array( 'split_method' => 'heading' ),
                    ),
                    'split_marker' => array(
                        'type'       => 'text',
                        'label'      => __( 'Custom Split Marker', 'swiftlms' ),
                        'depends_on' => array( 'split_method' => 'marker' ),
                    ),
                ),
            ),
            'sections' => array(
                'title'  => __( 'Sections/Modules', 'swiftlms' ),
                'fields' => array(
                    'create_sections' => array(
                        'type'  => 'checkbox',
                        'label' => __( 'Create course sections', 'swiftlms' ),
                    ),
                    'section_heading' => array(
                        'type'       => 'select',
                        'label'      => __( 'Section Heading Level', 'swiftlms' ),
                        'options'    => array(
                            'HEADING_1' => 'H1',
                            'HEADING_2' => 'H2',
                        ),
                        'depends_on' => array( 'create_sections' => true ),
                    ),
                    'lesson_heading' => array(
                        'type'       => 'select',
                        'label'      => __( 'Lesson Heading Level', 'swiftlms' ),
                        'options'    => array(
                            'HEADING_2' => 'H2',
                            'HEADING_3' => 'H3',
                        ),
                        'depends_on' => array( 'create_sections' => true ),
                    ),
                ),
            ),
            'content' => array(
                'title'  => __( 'Content Processing', 'swiftlms' ),
                'fields' => array(
                    'preserve_styles' => array(
                        'type'  => 'checkbox',
                        'label' => __( 'Preserve text formatting (bold, italic, etc.)', 'swiftlms' ),
                    ),
                    'import_images' => array(
                        'type'  => 'checkbox',
                        'label' => __( 'Import images to Media Library', 'swiftlms' ),
                    ),
                    'convert_tables' => array(
                        'type'  => 'checkbox',
                        'label' => __( 'Convert tables to HTML', 'swiftlms' ),
                    ),
                    'convert_callouts' => array(
                        'type'  => 'checkbox',
                        'label' => __( 'Convert callout markers to styled boxes', 'swiftlms' ),
                    ),
                ),
            ),
            'video' => array(
                'title'  => __( 'Video Content', 'swiftlms' ),
                'fields' => array(
                    'detect_videos' => array(
                        'type'  => 'checkbox',
                        'label' => __( 'Detect and embed videos', 'swiftlms' ),
                    ),
                    'video_marker' => array(
                        'type'  => 'text',
                        'label' => __( 'Video Marker', 'swiftlms' ),
                    ),
                    'auto_video_lesson' => array(
                        'type'  => 'checkbox',
                        'label' => __( 'Auto-set lesson type to "video" when video detected', 'swiftlms' ),
                    ),
                ),
            ),
            'quizzes' => array(
                'title'  => __( 'Quiz Parsing', 'swiftlms' ),
                'fields' => array(
                    'parse_quizzes' => array(
                        'type'  => 'checkbox',
                        'label' => __( 'Parse quiz markers into quizzes', 'swiftlms' ),
                    ),
                    'quiz_marker' => array(
                        'type'       => 'text',
                        'label'      => __( 'Quiz Start Marker', 'swiftlms' ),
                        'depends_on' => array( 'parse_quizzes' => true ),
                    ),
                    'question_marker' => array(
                        'type'       => 'text',
                        'label'      => __( 'Question Marker', 'swiftlms' ),
                        'depends_on' => array( 'parse_quizzes' => true ),
                    ),
                    'answer_marker' => array(
                        'type'       => 'text',
                        'label'      => __( 'Answer Marker', 'swiftlms' ),
                        'depends_on' => array( 'parse_quizzes' => true ),
                    ),
                    'correct_marker' => array(
                        'type'       => 'text',
                        'label'      => __( 'Correct Answer Marker', 'swiftlms' ),
                        'depends_on' => array( 'parse_quizzes' => true ),
                    ),
                ),
            ),
            'assignments' => array(
                'title'  => __( 'Assignment Parsing', 'swiftlms' ),
                'fields' => array(
                    'parse_assignments' => array(
                        'type'  => 'checkbox',
                        'label' => __( 'Parse assignment markers', 'swiftlms' ),
                    ),
                    'assignment_marker' => array(
                        'type'       => 'text',
                        'label'      => __( 'Assignment Marker', 'swiftlms' ),
                        'depends_on' => array( 'parse_assignments' => true ),
                    ),
                ),
            ),
        );
    }
}
