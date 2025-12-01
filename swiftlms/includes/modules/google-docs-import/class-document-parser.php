<?php
/**
 * Google Docs Document Parser
 *
 * @package SwiftLMS\Modules\GoogleDocsImport
 */

namespace SwiftLMS\Modules\GoogleDocsImport;

defined( 'ABSPATH' ) || exit;

/**
 * Document_Parser class.
 *
 * Parses Google Docs JSON structure into course content.
 */
class Document_Parser {

    /**
     * Document data.
     *
     * @var array
     */
    private $document;

    /**
     * Inline objects (images, etc.).
     *
     * @var array
     */
    private $inline_objects = array();

    /**
     * Lists data.
     *
     * @var array
     */
    private $lists = array();

    /**
     * Named styles.
     *
     * @var array
     */
    private $named_styles = array();

    /**
     * Imported images.
     *
     * @var array
     */
    private $imported_images = array();

    /**
     * Special markers for content structure.
     */
    const MARKER_QUIZ     = '[QUIZ]';
    const MARKER_QUESTION = '[Q]';
    const MARKER_ANSWER   = '[A]';
    const MARKER_VIDEO    = '[VIDEO]';
    const MARKER_NOTE     = '[NOTE]';
    const MARKER_TIP      = '[TIP]';
    const MARKER_WARNING  = '[WARNING]';

    /**
     * Constructor.
     *
     * @param array $document Google Docs document data.
     */
    public function __construct( array $document ) {
        $this->document = $document;
        $this->inline_objects = $document['inlineObjects'] ?? array();
        $this->lists = $document['lists'] ?? array();
        $this->named_styles = $document['namedStyles']['styles'] ?? array();
    }

    /**
     * Parse document into course structure.
     *
     * @param array $options Parser options.
     * @return array
     */
    public function parse( array $options = array() ): array {
        $defaults = array(
            'split_by'         => 'heading_1', // heading_1, heading_2, page_break, manual
            'import_images'    => true,
            'create_quizzes'   => true,
            'preserve_styles'  => true,
        );

        $options = wp_parse_args( $options, $defaults );

        $result = array(
            'title'       => $this->document['title'] ?? '',
            'description' => '',
            'lessons'     => array(),
            'quizzes'     => array(),
        );

        $body = $this->document['body']['content'] ?? array();

        // First pass: extract description (content before first heading).
        $description_parts = array();
        $found_heading = false;

        foreach ( $body as $element ) {
            if ( isset( $element['paragraph'] ) ) {
                $style = $element['paragraph']['paragraphStyle']['namedStyleType'] ?? 'NORMAL_TEXT';

                if ( in_array( $style, array( 'HEADING_1', 'HEADING_2', 'HEADING_3' ), true ) ) {
                    $found_heading = true;
                    break;
                }

                if ( ! $found_heading ) {
                    $text = $this->parse_paragraph( $element['paragraph'], $options );
                    if ( ! empty( trim( strip_tags( $text ) ) ) ) {
                        $description_parts[] = $text;
                    }
                }
            }
        }

        $result['description'] = implode( "\n", $description_parts );

        // Second pass: split into lessons.
        $split_style = $this->get_split_style( $options['split_by'] );
        $lessons = $this->split_content( $body, $split_style, $options );

        $result['lessons'] = $lessons;

        // Extract quizzes from content if enabled.
        if ( $options['create_quizzes'] ) {
            $result = $this->extract_quizzes( $result );
        }

        return $result;
    }

    /**
     * Get split style name.
     *
     * @param string $split_by Split option.
     * @return string
     */
    private function get_split_style( string $split_by ): string {
        $styles = array(
            'heading_1' => 'HEADING_1',
            'heading_2' => 'HEADING_2',
            'heading_3' => 'HEADING_3',
        );

        return $styles[ $split_by ] ?? 'HEADING_1';
    }

    /**
     * Split content into lessons.
     *
     * @param array  $body        Document body.
     * @param string $split_style Style to split on.
     * @param array  $options     Parser options.
     * @return array
     */
    private function split_content( array $body, string $split_style, array $options ): array {
        $lessons = array();
        $current_lesson = null;
        $current_content = array();
        $lesson_order = 0;

        foreach ( $body as $element ) {
            // Check for page break.
            if ( isset( $element['sectionBreak'] ) && 'page_break' === $options['split_by'] ) {
                if ( $current_lesson ) {
                    $current_lesson['content'] = implode( "\n", $current_content );
                    $lessons[] = $current_lesson;
                    $current_lesson = null;
                    $current_content = array();
                }
                continue;
            }

            // Check for paragraph.
            if ( isset( $element['paragraph'] ) ) {
                $paragraph = $element['paragraph'];
                $style = $paragraph['paragraphStyle']['namedStyleType'] ?? 'NORMAL_TEXT';

                // Check if this is a split point.
                if ( $style === $split_style ) {
                    // Save previous lesson.
                    if ( $current_lesson ) {
                        $current_lesson['content'] = implode( "\n", $current_content );
                        $lessons[] = $current_lesson;
                    }

                    // Start new lesson.
                    $lesson_order++;
                    $title = $this->get_plain_text( $paragraph );

                    $current_lesson = array(
                        'title'   => $title,
                        'content' => '',
                        'order'   => $lesson_order,
                        'type'    => 'text', // Will be updated if video found.
                    );
                    $current_content = array();
                    continue;
                }

                // Add to current lesson content.
                if ( $current_lesson ) {
                    $html = $this->parse_paragraph( $paragraph, $options );
                    if ( ! empty( $html ) ) {
                        $current_content[] = $html;

                        // Check for video marker.
                        if ( strpos( $html, self::MARKER_VIDEO ) !== false ) {
                            $current_lesson['type'] = 'video';
                        }
                    }
                }
            }

            // Check for table.
            if ( isset( $element['table'] ) && $current_lesson ) {
                $current_content[] = $this->parse_table( $element['table'], $options );
            }
        }

        // Don't forget the last lesson.
        if ( $current_lesson ) {
            $current_lesson['content'] = implode( "\n", $current_content );
            $lessons[] = $current_lesson;
        }

        return $lessons;
    }

    /**
     * Parse paragraph to HTML.
     *
     * @param array $paragraph Paragraph data.
     * @param array $options   Parser options.
     * @return string
     */
    private function parse_paragraph( array $paragraph, array $options ): string {
        $elements = $paragraph['elements'] ?? array();
        $style = $paragraph['paragraphStyle']['namedStyleType'] ?? 'NORMAL_TEXT';
        $bullet = $paragraph['bullet'] ?? null;

        $html_parts = array();

        foreach ( $elements as $element ) {
            if ( isset( $element['textRun'] ) ) {
                $html_parts[] = $this->parse_text_run( $element['textRun'], $options );
            }

            if ( isset( $element['inlineObjectElement'] ) ) {
                $object_id = $element['inlineObjectElement']['inlineObjectId'];
                $html_parts[] = $this->parse_inline_object( $object_id, $options );
            }
        }

        $content = implode( '', $html_parts );

        // Skip empty paragraphs.
        if ( empty( trim( strip_tags( $content ) ) ) ) {
            return '';
        }

        // Handle lists.
        if ( $bullet ) {
            $list_id = $bullet['listId'];
            $nesting = $bullet['nestingLevel'] ?? 0;
            $list_props = $this->lists[ $list_id ]['listProperties']['nestingLevels'][ $nesting ] ?? array();

            $is_ordered = isset( $list_props['glyphType'] ) || isset( $list_props['glyphFormat'] );
            $tag = $is_ordered ? 'li' : 'li';

            return "<{$tag}>{$content}</{$tag}>";
        }

        // Handle headings.
        $heading_map = array(
            'HEADING_1' => 'h1',
            'HEADING_2' => 'h2',
            'HEADING_3' => 'h3',
            'HEADING_4' => 'h4',
            'HEADING_5' => 'h5',
            'HEADING_6' => 'h6',
        );

        if ( isset( $heading_map[ $style ] ) ) {
            $tag = $heading_map[ $style ];
            return "<{$tag}>{$content}</{$tag}>";
        }

        // Handle special markers.
        $content = $this->process_special_markers( $content );

        return "<p>{$content}</p>";
    }

    /**
     * Parse text run to HTML.
     *
     * @param array $text_run Text run data.
     * @param array $options  Parser options.
     * @return string
     */
    private function parse_text_run( array $text_run, array $options ): string {
        $content = $text_run['content'] ?? '';
        $style = $text_run['textStyle'] ?? array();

        // Handle newlines.
        $content = str_replace( "\n", '', $content );

        if ( empty( $content ) ) {
            return '';
        }

        // Escape HTML.
        $content = esc_html( $content );

        if ( ! $options['preserve_styles'] ) {
            return $content;
        }

        // Apply text styles.
        if ( ! empty( $style['bold'] ) ) {
            $content = "<strong>{$content}</strong>";
        }

        if ( ! empty( $style['italic'] ) ) {
            $content = "<em>{$content}</em>";
        }

        if ( ! empty( $style['underline'] ) ) {
            $content = "<u>{$content}</u>";
        }

        if ( ! empty( $style['strikethrough'] ) ) {
            $content = "<del>{$content}</del>";
        }

        if ( ! empty( $style['baselineOffset'] ) ) {
            if ( 'SUPERSCRIPT' === $style['baselineOffset'] ) {
                $content = "<sup>{$content}</sup>";
            } elseif ( 'SUBSCRIPT' === $style['baselineOffset'] ) {
                $content = "<sub>{$content}</sub>";
            }
        }

        // Handle links.
        if ( ! empty( $style['link']['url'] ) ) {
            $url = esc_url( $style['link']['url'] );
            $content = "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\">{$content}</a>";
        }

        // Handle code/monospace.
        if ( ! empty( $style['weightedFontFamily']['fontFamily'] ) ) {
            $font = $style['weightedFontFamily']['fontFamily'];
            if ( in_array( $font, array( 'Consolas', 'Courier New', 'monospace' ), true ) ) {
                $content = "<code>{$content}</code>";
            }
        }

        return $content;
    }

    /**
     * Parse inline object (image).
     *
     * @param string $object_id Object ID.
     * @param array  $options   Parser options.
     * @return string
     */
    private function parse_inline_object( string $object_id, array $options ): string {
        if ( ! isset( $this->inline_objects[ $object_id ] ) ) {
            return '';
        }

        $object = $this->inline_objects[ $object_id ];
        $embedded = $object['inlineObjectProperties']['embeddedObject'] ?? array();

        if ( ! isset( $embedded['imageProperties'] ) ) {
            return '';
        }

        $image_props = $embedded['imageProperties'];
        $source_uri = $image_props['contentUri'] ?? $image_props['sourceUri'] ?? '';

        if ( empty( $source_uri ) ) {
            return '';
        }

        // Get dimensions.
        $size = $embedded['size'] ?? array();
        $width = isset( $size['width']['magnitude'] ) ? round( $size['width']['magnitude'] ) : '';
        $height = isset( $size['height']['magnitude'] ) ? round( $size['height']['magnitude'] ) : '';

        $title = $embedded['title'] ?? '';
        $alt = $embedded['description'] ?? $title;

        // Import image if enabled.
        if ( $options['import_images'] ) {
            $attachment_id = $this->import_image( $source_uri, $title );
            if ( $attachment_id ) {
                $source_uri = wp_get_attachment_url( $attachment_id );
            }
        }

        $attrs = array(
            'src'   => esc_url( $source_uri ),
            'alt'   => esc_attr( $alt ),
            'class' => 'sfls-imported-image',
        );

        if ( $width ) {
            $attrs['width'] = $width;
        }
        if ( $height ) {
            $attrs['height'] = $height;
        }

        $attr_string = '';
        foreach ( $attrs as $key => $value ) {
            $attr_string .= " {$key}=\"{$value}\"";
        }

        return "<img{$attr_string} />";
    }

    /**
     * Import image to media library.
     *
     * @param string $url   Image URL.
     * @param string $title Image title.
     * @return int|false Attachment ID or false.
     */
    private function import_image( string $url, string $title = '' ) {
        // Check if already imported.
        if ( isset( $this->imported_images[ $url ] ) ) {
            return $this->imported_images[ $url ];
        }

        // Download image.
        $result = Google_API_Client::download_image( $url );

        if ( is_wp_error( $result ) ) {
            return false;
        }

        $file = $result['file'];
        $type = $result['type'];

        // Prepare file for upload.
        $filename = $title ? sanitize_file_name( $title ) : 'imported-image-' . uniqid();
        $ext = pathinfo( $file, PATHINFO_EXTENSION );
        $filename .= '.' . $ext;

        // Upload to media library.
        $upload = wp_upload_bits( $filename, null, file_get_contents( $file ) );

        // Clean up temp file.
        unlink( $file );

        if ( $upload['error'] ) {
            return false;
        }

        // Create attachment.
        $attachment = array(
            'post_mime_type' => $type,
            'post_title'     => $title ?: $filename,
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );

        if ( is_wp_error( $attachment_id ) ) {
            return false;
        }

        // Generate metadata.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        wp_update_attachment_metadata( $attachment_id, $metadata );

        // Cache for reuse.
        $this->imported_images[ $url ] = $attachment_id;

        return $attachment_id;
    }

    /**
     * Parse table to HTML.
     *
     * @param array $table   Table data.
     * @param array $options Parser options.
     * @return string
     */
    private function parse_table( array $table, array $options ): string {
        $rows = $table['tableRows'] ?? array();
        $html = '<table class="sfls-imported-table">';

        foreach ( $rows as $row_index => $row ) {
            $tag = $row_index === 0 ? 'th' : 'td';
            $html .= '<tr>';

            foreach ( $row['tableCells'] ?? array() as $cell ) {
                $content = '';
                foreach ( $cell['content'] ?? array() as $element ) {
                    if ( isset( $element['paragraph'] ) ) {
                        $content .= $this->get_plain_text( $element['paragraph'] );
                    }
                }
                $html .= "<{$tag}>" . esc_html( $content ) . "</{$tag}>";
            }

            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    /**
     * Get plain text from paragraph.
     *
     * @param array $paragraph Paragraph data.
     * @return string
     */
    private function get_plain_text( array $paragraph ): string {
        $text = '';

        foreach ( $paragraph['elements'] ?? array() as $element ) {
            if ( isset( $element['textRun']['content'] ) ) {
                $text .= $element['textRun']['content'];
            }
        }

        return trim( $text );
    }

    /**
     * Process special markers in content.
     *
     * @param string $content Content.
     * @return string
     */
    private function process_special_markers( string $content ): string {
        // Convert [NOTE] to styled box.
        if ( strpos( $content, self::MARKER_NOTE ) !== false ) {
            $content = str_replace( self::MARKER_NOTE, '', $content );
            return '<div class="sfls-callout sfls-callout-note"><strong>Note:</strong> ' . trim( $content ) . '</div>';
        }

        // Convert [TIP] to styled box.
        if ( strpos( $content, self::MARKER_TIP ) !== false ) {
            $content = str_replace( self::MARKER_TIP, '', $content );
            return '<div class="sfls-callout sfls-callout-tip"><strong>Tip:</strong> ' . trim( $content ) . '</div>';
        }

        // Convert [WARNING] to styled box.
        if ( strpos( $content, self::MARKER_WARNING ) !== false ) {
            $content = str_replace( self::MARKER_WARNING, '', $content );
            return '<div class="sfls-callout sfls-callout-warning"><strong>Warning:</strong> ' . trim( $content ) . '</div>';
        }

        // Convert [VIDEO] URL to embed.
        if ( preg_match( '/\[VIDEO\]\s*(https?:\/\/[^\s<]+)/', $content, $matches ) ) {
            $video_url = $matches[1];
            $embed = wp_oembed_get( $video_url );
            if ( $embed ) {
                return '<div class="sfls-video-embed">' . $embed . '</div>';
            }
        }

        return $content;
    }

    /**
     * Extract quizzes from parsed content.
     *
     * @param array $result Parsed result.
     * @return array
     */
    private function extract_quizzes( array $result ): array {
        foreach ( $result['lessons'] as &$lesson ) {
            // Check for quiz marker.
            if ( strpos( $lesson['content'], self::MARKER_QUIZ ) !== false ) {
                $quiz = $this->parse_quiz_content( $lesson['content'] );
                if ( $quiz ) {
                    $result['quizzes'][] = array(
                        'title'     => $lesson['title'] . ' Quiz',
                        'lesson'    => $lesson['order'],
                        'questions' => $quiz,
                    );

                    // Remove quiz content from lesson.
                    $lesson['content'] = preg_replace(
                        '/\[QUIZ\].*$/s',
                        '',
                        $lesson['content']
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Parse quiz content from markers.
     *
     * @param string $content Content with quiz markers.
     * @return array|null
     */
    private function parse_quiz_content( string $content ): ?array {
        $questions = array();

        // Find quiz section.
        $quiz_start = strpos( $content, self::MARKER_QUIZ );
        if ( $quiz_start === false ) {
            return null;
        }

        $quiz_content = substr( $content, $quiz_start );

        // Parse questions.
        preg_match_all( '/\[Q\]\s*(.+?)(?=\[Q\]|\[QUIZ\]|$)/s', $quiz_content, $q_matches );

        foreach ( $q_matches[1] as $q_block ) {
            $question = array(
                'text'    => '',
                'type'    => 'multiple_choice',
                'answers' => array(),
                'correct' => array(),
            );

            // Split into lines.
            $lines = preg_split( '/\r?\n/', trim( $q_block ) );
            $question['text'] = trim( array_shift( $lines ) );

            // Parse answers.
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( empty( $line ) ) {
                    continue;
                }

                // Check for answer marker.
                if ( preg_match( '/^\[A\*?\]\s*(.+)$/', $line, $a_match ) ) {
                    $is_correct = strpos( $line, '[A*]' ) !== false;
                    $answer_text = trim( $a_match[1] );

                    $question['answers'][] = $answer_text;
                    if ( $is_correct ) {
                        $question['correct'][] = count( $question['answers'] ) - 1;
                    }
                }
            }

            // Determine question type.
            if ( count( $question['correct'] ) > 1 ) {
                $question['type'] = 'multiple_select';
            } elseif ( count( $question['answers'] ) === 2 &&
                       in_array( strtolower( $question['answers'][0] ), array( 'true', 'yes' ), true ) ) {
                $question['type'] = 'true_false';
            }

            if ( ! empty( $question['text'] ) && ! empty( $question['answers'] ) ) {
                $questions[] = $question;
            }
        }

        return ! empty( $questions ) ? $questions : null;
    }

    /**
     * Get document title.
     *
     * @return string
     */
    public function get_title(): string {
        return $this->document['title'] ?? '';
    }

    /**
     * Get document word count (approximate).
     *
     * @return int
     */
    public function get_word_count(): int {
        $text = '';

        foreach ( $this->document['body']['content'] ?? array() as $element ) {
            if ( isset( $element['paragraph'] ) ) {
                $text .= $this->get_plain_text( $element['paragraph'] ) . ' ';
            }
        }

        return str_word_count( $text );
    }

    /**
     * Get heading count for preview.
     *
     * @return array
     */
    public function get_heading_counts(): array {
        $counts = array(
            'HEADING_1' => 0,
            'HEADING_2' => 0,
            'HEADING_3' => 0,
        );

        foreach ( $this->document['body']['content'] ?? array() as $element ) {
            if ( isset( $element['paragraph'] ) ) {
                $style = $element['paragraph']['paragraphStyle']['namedStyleType'] ?? '';
                if ( isset( $counts[ $style ] ) ) {
                    $counts[ $style ]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Get preview of document structure.
     *
     * @return array
     */
    public function get_structure_preview(): array {
        $structure = array();

        foreach ( $this->document['body']['content'] ?? array() as $element ) {
            if ( isset( $element['paragraph'] ) ) {
                $paragraph = $element['paragraph'];
                $style = $paragraph['paragraphStyle']['namedStyleType'] ?? 'NORMAL_TEXT';

                if ( in_array( $style, array( 'HEADING_1', 'HEADING_2', 'HEADING_3' ), true ) ) {
                    $structure[] = array(
                        'level' => $style,
                        'title' => $this->get_plain_text( $paragraph ),
                    );
                }
            }
        }

        return $structure;
    }
}
