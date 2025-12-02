<?php
/**
 * Word Document Parser
 *
 * Parses .docx files into course structure without external dependencies.
 * Uses native PHP ZipArchive to read the XML structure.
 *
 * @package SwiftLMS\Modules\GoogleDocsImport
 */

namespace SwiftLMS\Modules\GoogleDocsImport;

defined( 'ABSPATH' ) || exit;

/**
 * Word_Parser class.
 *
 * Parses Microsoft Word documents (.docx) into structured course content.
 */
class Word_Parser {

    /**
     * Word namespace URIs.
     */
    const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    const NS_WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    const NS_PIC = 'http://schemas.openxmlformats.org/drawingml/2006/picture';

    /**
     * ZipArchive instance.
     *
     * @var \ZipArchive
     */
    private $zip;

    /**
     * Document XML.
     *
     * @var \SimpleXMLElement
     */
    private $document;

    /**
     * Relationships.
     *
     * @var array
     */
    private $relationships = array();

    /**
     * Styles mapping.
     *
     * @var array
     */
    private $styles = array();

    /**
     * Numbering definitions.
     *
     * @var array
     */
    private $numbering = array();

    /**
     * Parsed content.
     *
     * @var array
     */
    private $content = array();

    /**
     * Word count.
     *
     * @var int
     */
    private $word_count = 0;

    /**
     * Images found.
     *
     * @var array
     */
    private $images = array();

    /**
     * File path.
     *
     * @var string
     */
    private $file_path;

    /**
     * Constructor.
     *
     * @param string $file_path Path to .docx file.
     */
    public function __construct( string $file_path ) {
        $this->file_path = $file_path;
    }

    /**
     * Parse the document.
     *
     * @param array $options Parsing options.
     * @return array|WP_Error Parsed content or error.
     */
    public function parse( array $options = array() ): array|\WP_Error {
        $defaults = array(
            'split_by'        => 'heading_1',
            'import_images'   => true,
            'create_quizzes'  => true,
            'preserve_styles' => true,
        );
        $options = wp_parse_args( $options, $defaults );

        // Open the docx file.
        $result = $this->open_document();
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Load relationships.
        $this->load_relationships();

        // Load styles.
        $this->load_styles();

        // Load numbering.
        $this->load_numbering();

        // Parse the main document.
        $this->parse_document( $options );

        // Close the zip.
        $this->zip->close();

        // Build course structure.
        return $this->build_course_structure( $options );
    }

    /**
     * Open the document.
     *
     * @return true|WP_Error
     */
    private function open_document(): bool|\WP_Error {
        if ( ! file_exists( $this->file_path ) ) {
            return new \WP_Error( 'file_not_found', __( 'File not found.', 'swiftlms' ) );
        }

        if ( ! class_exists( 'ZipArchive' ) ) {
            return new \WP_Error( 'zip_not_available', __( 'ZipArchive extension is required.', 'swiftlms' ) );
        }

        $this->zip = new \ZipArchive();
        $result = $this->zip->open( $this->file_path );

        if ( true !== $result ) {
            return new \WP_Error( 'invalid_docx', __( 'Could not open document. Make sure it is a valid .docx file.', 'swiftlms' ) );
        }

        // Read main document.
        $content = $this->zip->getFromName( 'word/document.xml' );
        if ( false === $content ) {
            $this->zip->close();
            return new \WP_Error( 'invalid_docx', __( 'Document structure is invalid.', 'swiftlms' ) );
        }

        $this->document = simplexml_load_string( $content );
        if ( false === $this->document ) {
            $this->zip->close();
            return new \WP_Error( 'parse_error', __( 'Could not parse document XML.', 'swiftlms' ) );
        }

        $this->document->registerXPathNamespace( 'w', self::NS_W );

        return true;
    }

    /**
     * Load relationships.
     */
    private function load_relationships(): void {
        $content = $this->zip->getFromName( 'word/_rels/document.xml.rels' );
        if ( false === $content ) {
            return;
        }

        $xml = simplexml_load_string( $content );
        if ( false === $xml ) {
            return;
        }

        foreach ( $xml->Relationship as $rel ) {
            $id = (string) $rel['Id'];
            $this->relationships[ $id ] = array(
                'type'   => (string) $rel['Type'],
                'target' => (string) $rel['Target'],
            );
        }
    }

    /**
     * Load styles.
     */
    private function load_styles(): void {
        $content = $this->zip->getFromName( 'word/styles.xml' );
        if ( false === $content ) {
            return;
        }

        $xml = simplexml_load_string( $content );
        if ( false === $xml ) {
            return;
        }

        $xml->registerXPathNamespace( 'w', self::NS_W );

        foreach ( $xml->xpath( '//w:style' ) as $style ) {
            $id = (string) $style['w:styleId'];
            $type = (string) $style['w:type'];

            // Get outline level for headings.
            $outline = $style->xpath( './/w:outlineLvl/@w:val' );
            $outline_level = ! empty( $outline ) ? (int) $outline[0] : null;

            // Get based on style.
            $based_on = $style->xpath( './/w:basedOn/@w:val' );
            $based_on_id = ! empty( $based_on ) ? (string) $based_on[0] : null;

            $this->styles[ $id ] = array(
                'type'          => $type,
                'outline_level' => $outline_level,
                'based_on'      => $based_on_id,
            );
        }
    }

    /**
     * Load numbering definitions.
     */
    private function load_numbering(): void {
        $content = $this->zip->getFromName( 'word/numbering.xml' );
        if ( false === $content ) {
            return;
        }

        $xml = simplexml_load_string( $content );
        if ( false === $xml ) {
            return;
        }

        $xml->registerXPathNamespace( 'w', self::NS_W );

        // Load abstract numbering.
        foreach ( $xml->xpath( '//w:abstractNum' ) as $abstract ) {
            $id = (string) $abstract['w:abstractNumId'];
            $levels = array();

            foreach ( $abstract->xpath( './/w:lvl' ) as $level ) {
                $lvl_id = (string) $level['w:ilvl'];
                $fmt = $level->xpath( './/w:numFmt/@w:val' );
                $levels[ $lvl_id ] = array(
                    'format' => ! empty( $fmt ) ? (string) $fmt[0] : 'decimal',
                );
            }

            $this->numbering[ 'abstract_' . $id ] = $levels;
        }
    }

    /**
     * Parse the main document.
     *
     * @param array $options Parsing options.
     */
    private function parse_document( array $options ): void {
        $body = $this->document->xpath( '//w:body' );
        if ( empty( $body ) ) {
            return;
        }

        foreach ( $body[0]->children( self::NS_W ) as $element ) {
            $name = $element->getName();

            if ( 'p' === $name ) {
                $this->parse_paragraph( $element, $options );
            } elseif ( 'tbl' === $name ) {
                $this->parse_table( $element, $options );
            }
        }
    }

    /**
     * Parse a paragraph.
     *
     * @param \SimpleXMLElement $paragraph Paragraph element.
     * @param array             $options   Parsing options.
     */
    private function parse_paragraph( \SimpleXMLElement $paragraph, array $options ): void {
        $paragraph->registerXPathNamespace( 'w', self::NS_W );

        // Get paragraph style.
        $style_id = $paragraph->xpath( './/w:pStyle/@w:val' );
        $style_id = ! empty( $style_id ) ? (string) $style_id[0] : null;

        // Get text content.
        $text = $this->get_paragraph_text( $paragraph, $options );

        // Determine element type.
        $type = 'paragraph';
        $level = null;

        if ( $style_id && isset( $this->styles[ $style_id ] ) ) {
            $style = $this->styles[ $style_id ];

            // Check for heading.
            if ( null !== $style['outline_level'] ) {
                $type = 'heading';
                $level = $style['outline_level'] + 1; // 0-based to 1-based.
            } elseif ( strpos( strtolower( $style_id ), 'heading' ) !== false ) {
                $type = 'heading';
                // Extract level from style name like "Heading1".
                preg_match( '/(\d+)/', $style_id, $matches );
                $level = ! empty( $matches[1] ) ? (int) $matches[1] : 1;
            }
        }

        // Check for list.
        $num_id = $paragraph->xpath( './/w:numPr/w:numId/@w:val' );
        $list_level = $paragraph->xpath( './/w:numPr/w:ilvl/@w:val' );
        $is_list = ! empty( $num_id );

        // Check for images.
        $images = $this->extract_images( $paragraph );

        // Skip empty paragraphs (unless they have images).
        if ( empty( trim( $text ) ) && empty( $images ) ) {
            return;
        }

        // Count words.
        $this->word_count += str_word_count( $text );

        // Add to content.
        $item = array(
            'type'    => $type,
            'text'    => $text,
            'raw'     => $text,
            'level'   => $level,
            'is_list' => $is_list,
            'images'  => $images,
        );

        if ( $is_list ) {
            $item['list_level'] = ! empty( $list_level ) ? (int) $list_level[0] : 0;
        }

        $this->content[] = $item;
    }

    /**
     * Get paragraph text with formatting.
     *
     * @param \SimpleXMLElement $paragraph Paragraph element.
     * @param array             $options   Parsing options.
     * @return string
     */
    private function get_paragraph_text( \SimpleXMLElement $paragraph, array $options ): string {
        $paragraph->registerXPathNamespace( 'w', self::NS_W );

        $text = '';
        $runs = $paragraph->xpath( './/w:r' );

        foreach ( $runs as $run ) {
            $run->registerXPathNamespace( 'w', self::NS_W );

            // Get text.
            $t = $run->xpath( './/w:t' );
            if ( empty( $t ) ) {
                continue;
            }

            $run_text = (string) $t[0];

            // Apply formatting if preserving styles.
            if ( $options['preserve_styles'] ) {
                // Check for bold.
                $bold = $run->xpath( './/w:b' );
                if ( ! empty( $bold ) ) {
                    $run_text = '<strong>' . $run_text . '</strong>';
                }

                // Check for italic.
                $italic = $run->xpath( './/w:i' );
                if ( ! empty( $italic ) ) {
                    $run_text = '<em>' . $run_text . '</em>';
                }

                // Check for underline.
                $underline = $run->xpath( './/w:u' );
                if ( ! empty( $underline ) ) {
                    $run_text = '<u>' . $run_text . '</u>';
                }

                // Check for strikethrough.
                $strike = $run->xpath( './/w:strike' );
                if ( ! empty( $strike ) ) {
                    $run_text = '<del>' . $run_text . '</del>';
                }

                // Check for hyperlink.
                $hyperlink = $run->xpath( 'ancestor::w:hyperlink/@r:id' );
                if ( ! empty( $hyperlink ) ) {
                    $rel_id = (string) $hyperlink[0];
                    if ( isset( $this->relationships[ $rel_id ] ) ) {
                        $url = $this->relationships[ $rel_id ]['target'];
                        $run_text = '<a href="' . esc_url( $url ) . '">' . $run_text . '</a>';
                    }
                }
            }

            $text .= $run_text;
        }

        return $text;
    }

    /**
     * Extract images from paragraph.
     *
     * @param \SimpleXMLElement $paragraph Paragraph element.
     * @return array
     */
    private function extract_images( \SimpleXMLElement $paragraph ): array {
        $images = array();
        $paragraph->registerXPathNamespace( 'w', self::NS_W );
        $paragraph->registerXPathNamespace( 'a', self::NS_A );
        $paragraph->registerXPathNamespace( 'r', self::NS_R );
        $paragraph->registerXPathNamespace( 'pic', self::NS_PIC );

        // Find embedded images.
        $blips = $paragraph->xpath( './/a:blip/@r:embed' );

        foreach ( $blips as $blip ) {
            $rel_id = (string) $blip;
            if ( isset( $this->relationships[ $rel_id ] ) ) {
                $target = $this->relationships[ $rel_id ]['target'];
                $image_path = 'word/' . $target;

                // Get image data.
                $image_data = $this->zip->getFromName( $image_path );
                if ( false !== $image_data ) {
                    $images[] = array(
                        'rel_id'   => $rel_id,
                        'path'     => $image_path,
                        'filename' => basename( $target ),
                        'data'     => base64_encode( $image_data ),
                        'mime'     => $this->get_mime_type( $target ),
                    );

                    $this->images[] = $image_path;
                }
            }
        }

        return $images;
    }

    /**
     * Get MIME type from filename.
     *
     * @param string $filename Filename.
     * @return string
     */
    private function get_mime_type( string $filename ): string {
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

        $mime_types = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'emf'  => 'image/x-emf',
            'wmf'  => 'image/x-wmf',
        );

        return $mime_types[ $ext ] ?? 'application/octet-stream';
    }

    /**
     * Parse a table.
     *
     * @param \SimpleXMLElement $table   Table element.
     * @param array             $options Parsing options.
     */
    private function parse_table( \SimpleXMLElement $table, array $options ): void {
        $table->registerXPathNamespace( 'w', self::NS_W );

        $rows = array();
        $table_rows = $table->xpath( './/w:tr' );

        foreach ( $table_rows as $row ) {
            $cells = array();
            $row->registerXPathNamespace( 'w', self::NS_W );
            $table_cells = $row->xpath( './/w:tc' );

            foreach ( $table_cells as $cell ) {
                $cell_text = '';
                $cell->registerXPathNamespace( 'w', self::NS_W );
                $paragraphs = $cell->xpath( './/w:p' );

                foreach ( $paragraphs as $p ) {
                    $cell_text .= $this->get_paragraph_text( $p, $options ) . "\n";
                }

                $cells[] = trim( $cell_text );
            }

            $rows[] = $cells;
        }

        if ( ! empty( $rows ) ) {
            $this->content[] = array(
                'type' => 'table',
                'rows' => $rows,
            );
        }
    }

    /**
     * Build course structure from parsed content.
     *
     * @param array $options Parsing options.
     * @return array
     */
    private function build_course_structure( array $options ): array {
        $split_level = $this->get_split_level( $options['split_by'] );

        $course = array(
            'title'       => '',
            'description' => '',
        );

        $lessons = array();
        $current_lesson = null;
        $description_parts = array();
        $found_first_heading = false;

        foreach ( $this->content as $item ) {
            // First heading of split level becomes first lesson.
            if ( 'heading' === $item['type'] && $item['level'] === $split_level ) {
                // Save previous lesson.
                if ( null !== $current_lesson ) {
                    $current_lesson['content'] = $this->finalize_content( $current_lesson['content'] );
                    $lessons[] = $current_lesson;
                }

                // Start new lesson.
                $current_lesson = array(
                    'title'      => strip_tags( $item['text'] ),
                    'content'    => array(),
                    'has_images' => false,
                    'has_video'  => false,
                    'word_count' => 0,
                );

                $found_first_heading = true;
                continue;
            }

            // Content before first split heading is course description.
            if ( ! $found_first_heading ) {
                if ( 'heading' === $item['type'] && $item['level'] === 1 && empty( $course['title'] ) ) {
                    $course['title'] = strip_tags( $item['text'] );
                } else {
                    $description_parts[] = $item;
                }
                continue;
            }

            // Add to current lesson.
            if ( null !== $current_lesson ) {
                $current_lesson['content'][] = $item;
                $current_lesson['word_count'] += str_word_count( strip_tags( $item['text'] ?? '' ) );

                if ( ! empty( $item['images'] ) ) {
                    $current_lesson['has_images'] = true;
                }

                // Check for video markers.
                if ( isset( $item['text'] ) && preg_match( '/\[VIDEO\]/i', $item['text'] ) ) {
                    $current_lesson['has_video'] = true;
                }
            }
        }

        // Add last lesson.
        if ( null !== $current_lesson ) {
            $current_lesson['content'] = $this->finalize_content( $current_lesson['content'] );
            $lessons[] = $current_lesson;
        }

        // Build description.
        $course['description'] = $this->build_description( $description_parts );

        // Use first heading as title if not set.
        if ( empty( $course['title'] ) && ! empty( $lessons ) ) {
            $course['title'] = $lessons[0]['title'];
        }

        // Extract quizzes if enabled.
        $quizzes = array();
        if ( $options['create_quizzes'] ) {
            foreach ( $lessons as &$lesson ) {
                $quiz_data = $this->extract_quizzes( $lesson['content'] );
                if ( ! empty( $quiz_data['quizzes'] ) ) {
                    $quizzes = array_merge( $quizzes, $quiz_data['quizzes'] );
                    $lesson['content'] = $quiz_data['content'];
                }
            }
        }

        return array(
            'course'     => $course,
            'lessons'    => $lessons,
            'quizzes'    => $quizzes,
            'word_count' => $this->word_count,
            'images'     => $this->images,
        );
    }

    /**
     * Get split level from option.
     *
     * @param string $split_by Split option.
     * @return int
     */
    private function get_split_level( string $split_by ): int {
        $levels = array(
            'heading_1' => 1,
            'heading_2' => 2,
            'heading_3' => 3,
        );

        return $levels[ $split_by ] ?? 1;
    }

    /**
     * Finalize content into HTML.
     *
     * @param array $content Content items.
     * @return string
     */
    private function finalize_content( array $content ): string {
        $html = '';
        $in_list = false;
        $list_type = 'ul';

        foreach ( $content as $item ) {
            if ( 'paragraph' === $item['type'] || 'heading' === $item['type'] ) {
                // Close list if needed.
                if ( $in_list && ! $item['is_list'] ) {
                    $html .= '</' . $list_type . '>';
                    $in_list = false;
                }

                // Handle lists.
                if ( $item['is_list'] ) {
                    if ( ! $in_list ) {
                        $list_type = 'ul'; // Could detect ol based on numbering.
                        $html .= '<' . $list_type . '>';
                        $in_list = true;
                    }
                    $html .= '<li>' . $item['text'] . '</li>';
                } elseif ( 'heading' === $item['type'] ) {
                    $level = min( 6, max( 1, $item['level'] ) );
                    $html .= '<h' . $level . '>' . $item['text'] . '</h' . $level . '>';
                } else {
                    $html .= '<p>' . $item['text'] . '</p>';
                }

                // Add images.
                if ( ! empty( $item['images'] ) ) {
                    foreach ( $item['images'] as $image ) {
                        $html .= '<!-- IMAGE: ' . esc_attr( $image['filename'] ) . ' -->';
                    }
                }
            } elseif ( 'table' === $item['type'] ) {
                // Close list if needed.
                if ( $in_list ) {
                    $html .= '</' . $list_type . '>';
                    $in_list = false;
                }

                $html .= $this->render_table( $item['rows'] );
            }
        }

        // Close any open list.
        if ( $in_list ) {
            $html .= '</' . $list_type . '>';
        }

        return $html;
    }

    /**
     * Render table HTML.
     *
     * @param array $rows Table rows.
     * @return string
     */
    private function render_table( array $rows ): string {
        if ( empty( $rows ) ) {
            return '';
        }

        $html = '<table class="sfls-content-table"><thead><tr>';

        // First row as header.
        foreach ( $rows[0] as $cell ) {
            $html .= '<th>' . esc_html( $cell ) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        // Remaining rows.
        for ( $i = 1; $i < count( $rows ); $i++ ) {
            $html .= '<tr>';
            foreach ( $rows[ $i ] as $cell ) {
                $html .= '<td>' . esc_html( $cell ) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Build description from content items.
     *
     * @param array $items Content items.
     * @return string
     */
    private function build_description( array $items ): string {
        $parts = array();

        foreach ( $items as $item ) {
            if ( isset( $item['text'] ) && ! empty( trim( $item['text'] ) ) ) {
                $parts[] = strip_tags( $item['text'] );
            }
        }

        return implode( ' ', array_slice( $parts, 0, 3 ) );
    }

    /**
     * Extract quizzes from content.
     *
     * @param string $content HTML content.
     * @return array
     */
    private function extract_quizzes( string $content ): array {
        $quizzes = array();
        $clean_content = $content;

        // Find [QUIZ] markers.
        if ( preg_match_all( '/\[QUIZ\](.*?)(?=\[QUIZ\]|$)/is', $content, $matches ) ) {
            foreach ( $matches[1] as $quiz_content ) {
                $quiz = array(
                    'title'     => 'Quiz',
                    'questions' => array(),
                );

                // Parse questions.
                if ( preg_match_all( '/\[Q\](.*?)(?=\[Q\]|\[\/QUIZ\]|$)/is', $quiz_content, $q_matches ) ) {
                    foreach ( $q_matches[1] as $q_content ) {
                        $question = array(
                            'question' => '',
                            'answers'  => array(),
                        );

                        // Get question text.
                        $lines = explode( "\n", trim( strip_tags( $q_content ) ) );
                        $question['question'] = trim( $lines[0] );

                        // Get answers.
                        if ( preg_match_all( '/\[A(\*?)\](.*?)(?=\[A|\[Q\]|$)/is', $q_content, $a_matches, PREG_SET_ORDER ) ) {
                            foreach ( $a_matches as $answer ) {
                                $question['answers'][] = array(
                                    'text'    => trim( strip_tags( $answer[2] ) ),
                                    'correct' => ! empty( $answer[1] ),
                                );
                            }
                        }

                        if ( ! empty( $question['question'] ) ) {
                            $quiz['questions'][] = $question;
                        }
                    }
                }

                if ( ! empty( $quiz['questions'] ) ) {
                    $quizzes[] = $quiz;
                }
            }

            // Remove quiz content.
            $clean_content = preg_replace( '/\[QUIZ\].*?(?=\[QUIZ\]|<\/p>|$)/is', '', $content );
        }

        return array(
            'quizzes' => $quizzes,
            'content' => $clean_content,
        );
    }

    /**
     * Get document title from core properties.
     *
     * @return string
     */
    public function get_title(): string {
        $content = $this->zip->getFromName( 'docProps/core.xml' );
        if ( false === $content ) {
            return '';
        }

        $xml = simplexml_load_string( $content );
        if ( false === $xml ) {
            return '';
        }

        $namespaces = $xml->getNamespaces( true );
        if ( isset( $namespaces['dc'] ) ) {
            $dc = $xml->children( $namespaces['dc'] );
            if ( isset( $dc->title ) ) {
                return (string) $dc->title;
            }
        }

        return '';
    }

    /**
     * Get word count.
     *
     * @return int
     */
    public function get_word_count(): int {
        return $this->word_count;
    }

    /**
     * Get structure preview for UI.
     *
     * @return array
     */
    public function get_structure_preview(): array {
        $structure = array();

        foreach ( $this->content as $item ) {
            if ( 'heading' === $item['type'] ) {
                $structure[] = array(
                    'level' => 'HEADING_' . $item['level'],
                    'title' => strip_tags( $item['text'] ),
                );
            }
        }

        return $structure;
    }

    /**
     * Get heading counts.
     *
     * @return array
     */
    public function get_heading_counts(): array {
        $counts = array(
            'h1' => 0,
            'h2' => 0,
            'h3' => 0,
            'h4' => 0,
            'h5' => 0,
            'h6' => 0,
        );

        foreach ( $this->content as $item ) {
            if ( 'heading' === $item['type'] && $item['level'] >= 1 && $item['level'] <= 6 ) {
                $counts[ 'h' . $item['level'] ]++;
            }
        }

        return $counts;
    }
}
