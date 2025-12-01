<?php
/**
 * Badge Custom Post Type
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Badge CPT class.
 */
class Badge {

    /**
     * Post type name.
     *
     * @var string
     */
    const POST_TYPE = 'sfls_badge';

    /**
     * Badge trigger types.
     */
    const TRIGGER_COURSES_COMPLETED  = 'courses_completed';
    const TRIGGER_LESSONS_COMPLETED  = 'lessons_completed';
    const TRIGGER_QUIZZES_PASSED     = 'quizzes_passed';
    const TRIGGER_POINTS_EARNED      = 'points_earned';
    const TRIGGER_STREAK_DAYS        = 'streak_days';
    const TRIGGER_ASSIGNMENTS_GRADED = 'assignments_graded';
    const TRIGGER_PERFECT_QUIZZES    = 'perfect_quizzes';
    const TRIGGER_SPECIFIC_COURSE    = 'specific_course';
    const TRIGGER_LEVEL_REACHED      = 'level_reached';
    const TRIGGER_MANUAL             = 'manual';

    /**
     * Initialize.
     */
    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_columns' ), 10, 2 );
    }

    /**
     * Register post type.
     */
    public static function register_post_type(): void {
        $labels = array(
            'name'               => __( 'Badges', 'swiftlms' ),
            'singular_name'      => __( 'Badge', 'swiftlms' ),
            'add_new'            => __( 'Add New', 'swiftlms' ),
            'add_new_item'       => __( 'Add New Badge', 'swiftlms' ),
            'edit_item'          => __( 'Edit Badge', 'swiftlms' ),
            'new_item'           => __( 'New Badge', 'swiftlms' ),
            'view_item'          => __( 'View Badge', 'swiftlms' ),
            'search_items'       => __( 'Search Badges', 'swiftlms' ),
            'not_found'          => __( 'No badges found', 'swiftlms' ),
            'not_found_in_trash' => __( 'No badges found in trash', 'swiftlms' ),
            'menu_name'          => __( 'Badges', 'swiftlms' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'swiftlms',
            'show_in_rest'        => true,
            'rest_base'           => 'badges',
            'query_var'           => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'supports'            => array( 'title', 'editor', 'thumbnail' ),
            'menu_icon'           => 'dashicons-awards',
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Add meta boxes.
     */
    public static function add_meta_boxes(): void {
        add_meta_box(
            'sfls_badge_settings',
            __( 'Badge Settings', 'swiftlms' ),
            array( __CLASS__, 'render_settings_meta_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'sfls_badge_appearance',
            __( 'Badge Appearance', 'swiftlms' ),
            array( __CLASS__, 'render_appearance_meta_box' ),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Get trigger types.
     *
     * @return array
     */
    public static function get_trigger_types(): array {
        return array(
            self::TRIGGER_COURSES_COMPLETED  => __( 'Courses Completed', 'swiftlms' ),
            self::TRIGGER_LESSONS_COMPLETED  => __( 'Lessons Completed', 'swiftlms' ),
            self::TRIGGER_QUIZZES_PASSED     => __( 'Quizzes Passed', 'swiftlms' ),
            self::TRIGGER_POINTS_EARNED      => __( 'Points Earned', 'swiftlms' ),
            self::TRIGGER_STREAK_DAYS        => __( 'Streak Days', 'swiftlms' ),
            self::TRIGGER_ASSIGNMENTS_GRADED => __( 'Assignments Graded', 'swiftlms' ),
            self::TRIGGER_PERFECT_QUIZZES    => __( 'Perfect Quiz Scores', 'swiftlms' ),
            self::TRIGGER_SPECIFIC_COURSE    => __( 'Specific Course Completed', 'swiftlms' ),
            self::TRIGGER_LEVEL_REACHED      => __( 'Level Reached', 'swiftlms' ),
            self::TRIGGER_MANUAL             => __( 'Manual Award', 'swiftlms' ),
        );
    }

    /**
     * Render settings meta box.
     *
     * @param \WP_Post $post Post object.
     */
    public static function render_settings_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'sfls_badge_settings', 'sfls_badge_nonce' );

        $trigger_type  = get_post_meta( $post->ID, '_sfls_badge_trigger', true ) ?: self::TRIGGER_MANUAL;
        $trigger_value = get_post_meta( $post->ID, '_sfls_badge_trigger_value', true ) ?: 1;
        $course_id     = get_post_meta( $post->ID, '_sfls_badge_course_id', true );
        $points_reward = get_post_meta( $post->ID, '_sfls_badge_points_reward', true ) ?: 50;
        $is_hidden     = get_post_meta( $post->ID, '_sfls_badge_hidden', true );
        $rarity        = get_post_meta( $post->ID, '_sfls_badge_rarity', true ) ?: 'common';

        $courses = get_posts(
            array(
                'post_type'      => 'sfls_course',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        $rarities = array(
            'common'    => __( 'Common', 'swiftlms' ),
            'uncommon'  => __( 'Uncommon', 'swiftlms' ),
            'rare'      => __( 'Rare', 'swiftlms' ),
            'epic'      => __( 'Epic', 'swiftlms' ),
            'legendary' => __( 'Legendary', 'swiftlms' ),
        );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="sfls_badge_trigger"><?php esc_html_e( 'Award Trigger', 'swiftlms' ); ?></label></th>
                <td>
                    <select name="sfls_badge_trigger" id="sfls_badge_trigger" class="regular-text">
                        <?php foreach ( self::get_trigger_types() as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $trigger_type, $key ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="sfls-trigger-value-row" style="<?php echo in_array( $trigger_type, array( self::TRIGGER_MANUAL, self::TRIGGER_SPECIFIC_COURSE ), true ) ? 'display:none;' : ''; ?>">
                <th><label for="sfls_badge_trigger_value"><?php esc_html_e( 'Required Count/Value', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" name="sfls_badge_trigger_value" id="sfls_badge_trigger_value"
                           value="<?php echo esc_attr( $trigger_value ); ?>" min="1" class="small-text">
                    <p class="description" id="trigger-value-desc"><?php esc_html_e( 'Number required to earn this badge.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr class="sfls-course-row" style="<?php echo $trigger_type !== self::TRIGGER_SPECIFIC_COURSE ? 'display:none;' : ''; ?>">
                <th><label for="sfls_badge_course_id"><?php esc_html_e( 'Course', 'swiftlms' ); ?></label></th>
                <td>
                    <select name="sfls_badge_course_id" id="sfls_badge_course_id" class="regular-text">
                        <option value=""><?php esc_html_e( '— Select Course —', 'swiftlms' ); ?></option>
                        <?php foreach ( $courses as $course ) : ?>
                            <option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_id, $course->ID ); ?>>
                                <?php echo esc_html( $course->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_badge_points_reward"><?php esc_html_e( 'Points Reward', 'swiftlms' ); ?></label></th>
                <td>
                    <input type="number" name="sfls_badge_points_reward" id="sfls_badge_points_reward"
                           value="<?php echo esc_attr( $points_reward ); ?>" min="0" class="small-text">
                    <p class="description"><?php esc_html_e( 'Points awarded when earning this badge.', 'swiftlms' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_badge_rarity"><?php esc_html_e( 'Rarity', 'swiftlms' ); ?></label></th>
                <td>
                    <select name="sfls_badge_rarity" id="sfls_badge_rarity">
                        <?php foreach ( $rarities as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $rarity, $key ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="sfls_badge_hidden"><?php esc_html_e( 'Hidden Badge', 'swiftlms' ); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="sfls_badge_hidden" id="sfls_badge_hidden"
                               value="1" <?php checked( $is_hidden, '1' ); ?>>
                        <?php esc_html_e( 'Hide until earned (secret achievement)', 'swiftlms' ); ?>
                    </label>
                </td>
            </tr>
        </table>

        <script>
        jQuery(function($) {
            $('#sfls_badge_trigger').on('change', function() {
                var trigger = $(this).val();
                var showValue = !['manual', 'specific_course'].includes(trigger);
                var showCourse = trigger === 'specific_course';

                $('.sfls-trigger-value-row').toggle(showValue);
                $('.sfls-course-row').toggle(showCourse);

                // Update description
                var descriptions = {
                    'courses_completed': '<?php echo esc_js( __( 'Number of courses to complete.', 'swiftlms' ) ); ?>',
                    'lessons_completed': '<?php echo esc_js( __( 'Number of lessons to complete.', 'swiftlms' ) ); ?>',
                    'quizzes_passed': '<?php echo esc_js( __( 'Number of quizzes to pass.', 'swiftlms' ) ); ?>',
                    'points_earned': '<?php echo esc_js( __( 'Total points required.', 'swiftlms' ) ); ?>',
                    'streak_days': '<?php echo esc_js( __( 'Days of consecutive activity.', 'swiftlms' ) ); ?>',
                    'level_reached': '<?php echo esc_js( __( 'Level number to reach.', 'swiftlms' ) ); ?>'
                };
                $('#trigger-value-desc').text(descriptions[trigger] || '<?php echo esc_js( __( 'Number required to earn this badge.', 'swiftlms' ) ); ?>');
            });
        });
        </script>
        <?php
    }

    /**
     * Render appearance meta box.
     *
     * @param \WP_Post $post Post object.
     */
    public static function render_appearance_meta_box( \WP_Post $post ): void {
        $icon_type   = get_post_meta( $post->ID, '_sfls_badge_icon_type', true ) ?: 'emoji';
        $icon_emoji  = get_post_meta( $post->ID, '_sfls_badge_icon_emoji', true ) ?: '🏆';
        $badge_color = get_post_meta( $post->ID, '_sfls_badge_color', true ) ?: '#4f46e5';
        ?>
        <p>
            <label for="sfls_badge_icon_type"><strong><?php esc_html_e( 'Icon Type', 'swiftlms' ); ?></strong></label><br>
            <select name="sfls_badge_icon_type" id="sfls_badge_icon_type" class="widefat">
                <option value="emoji" <?php selected( $icon_type, 'emoji' ); ?>><?php esc_html_e( 'Emoji', 'swiftlms' ); ?></option>
                <option value="image" <?php selected( $icon_type, 'image' ); ?>><?php esc_html_e( 'Featured Image', 'swiftlms' ); ?></option>
            </select>
        </p>
        <p class="sfls-emoji-field" style="<?php echo $icon_type !== 'emoji' ? 'display:none;' : ''; ?>">
            <label for="sfls_badge_icon_emoji"><strong><?php esc_html_e( 'Emoji Icon', 'swiftlms' ); ?></strong></label><br>
            <input type="text" name="sfls_badge_icon_emoji" id="sfls_badge_icon_emoji"
                   value="<?php echo esc_attr( $icon_emoji ); ?>" class="widefat" style="font-size: 24px; text-align: center;">
        </p>
        <p>
            <label for="sfls_badge_color"><strong><?php esc_html_e( 'Badge Color', 'swiftlms' ); ?></strong></label><br>
            <input type="color" name="sfls_badge_color" id="sfls_badge_color"
                   value="<?php echo esc_attr( $badge_color ); ?>">
        </p>
        <div class="sfls-badge-preview" style="text-align: center; margin-top: 20px; padding: 20px; background: #f5f5f5; border-radius: 8px;">
            <div class="sfls-preview-badge" style="display: inline-flex; flex-direction: column; align-items: center; gap: 8px;">
                <span class="sfls-preview-icon" style="font-size: 48px;"><?php echo esc_html( $icon_emoji ); ?></span>
                <span class="sfls-preview-name" style="font-weight: 600; color: <?php echo esc_attr( $badge_color ); ?>;">
                    <?php echo esc_html( $post->post_title ?: __( 'Badge Name', 'swiftlms' ) ); ?>
                </span>
            </div>
        </div>

        <script>
        jQuery(function($) {
            $('#sfls_badge_icon_type').on('change', function() {
                $('.sfls-emoji-field').toggle($(this).val() === 'emoji');
            });

            $('#sfls_badge_icon_emoji').on('input', function() {
                $('.sfls-preview-icon').text($(this).val());
            });

            $('#sfls_badge_color').on('input', function() {
                $('.sfls-preview-name').css('color', $(this).val());
            });
        });
        </script>
        <?php
    }

    /**
     * Save meta data.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public static function save_meta( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST['sfls_badge_nonce'] ) || ! wp_verify_nonce( $_POST['sfls_badge_nonce'], 'sfls_badge_settings' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = array(
            'sfls_badge_trigger'       => 'sanitize_key',
            'sfls_badge_trigger_value' => 'absint',
            'sfls_badge_course_id'     => 'absint',
            'sfls_badge_points_reward' => 'absint',
            'sfls_badge_rarity'        => 'sanitize_key',
            'sfls_badge_hidden'        => 'absint',
            'sfls_badge_icon_type'     => 'sanitize_key',
            'sfls_badge_icon_emoji'    => 'sanitize_text_field',
            'sfls_badge_color'         => 'sanitize_hex_color',
        );

        foreach ( $fields as $field => $sanitize ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = call_user_func( $sanitize, $_POST[ $field ] );
                update_post_meta( $post_id, '_' . $field, $value );
            } else {
                delete_post_meta( $post_id, '_' . $field );
            }
        }
    }

    /**
     * Add admin columns.
     *
     * @param array $columns Columns.
     * @return array
     */
    public static function add_columns( array $columns ): array {
        $new_columns = array();

        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;

            if ( 'title' === $key ) {
                $new_columns['sfls_icon']    = __( 'Icon', 'swiftlms' );
                $new_columns['sfls_trigger'] = __( 'Trigger', 'swiftlms' );
                $new_columns['sfls_rarity']  = __( 'Rarity', 'swiftlms' );
                $new_columns['sfls_awarded'] = __( 'Awarded', 'swiftlms' );
            }
        }

        return $new_columns;
    }

    /**
     * Render admin columns.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public static function render_columns( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'sfls_icon':
                $icon_type  = get_post_meta( $post_id, '_sfls_badge_icon_type', true );
                $icon_emoji = get_post_meta( $post_id, '_sfls_badge_icon_emoji', true ) ?: '🏆';

                if ( 'image' === $icon_type && has_post_thumbnail( $post_id ) ) {
                    echo get_the_post_thumbnail( $post_id, array( 40, 40 ) );
                } else {
                    echo '<span style="font-size: 24px;">' . esc_html( $icon_emoji ) . '</span>';
                }
                break;

            case 'sfls_trigger':
                $trigger = get_post_meta( $post_id, '_sfls_badge_trigger', true );
                $value   = get_post_meta( $post_id, '_sfls_badge_trigger_value', true );
                $types   = self::get_trigger_types();

                echo esc_html( $types[ $trigger ] ?? $trigger );
                if ( $value && ! in_array( $trigger, array( self::TRIGGER_MANUAL, self::TRIGGER_SPECIFIC_COURSE ), true ) ) {
                    echo ' <strong>(' . esc_html( $value ) . ')</strong>';
                }
                break;

            case 'sfls_rarity':
                $rarity  = get_post_meta( $post_id, '_sfls_badge_rarity', true ) ?: 'common';
                $colors  = array(
                    'common'    => '#9CA3AF',
                    'uncommon'  => '#10B981',
                    'rare'      => '#3B82F6',
                    'epic'      => '#8B5CF6',
                    'legendary' => '#F59E0B',
                );
                echo '<span style="color: ' . esc_attr( $colors[ $rarity ] ?? '#9CA3AF' ) . '; font-weight: 600;">';
                echo esc_html( ucfirst( $rarity ) );
                echo '</span>';
                break;

            case 'sfls_awarded':
                $count = User_Badges::get_badge_award_count( $post_id );
                echo esc_html( $count );
                break;
        }
    }

    /**
     * Get badge data.
     *
     * @param int $badge_id Badge ID.
     * @return array|null
     */
    public static function get_badge( int $badge_id ): ?array {
        $post = get_post( $badge_id );

        if ( ! $post || self::POST_TYPE !== $post->post_type ) {
            return null;
        }

        $icon_type  = get_post_meta( $badge_id, '_sfls_badge_icon_type', true ) ?: 'emoji';
        $icon_emoji = get_post_meta( $badge_id, '_sfls_badge_icon_emoji', true ) ?: '🏆';

        return array(
            'id'            => $badge_id,
            'title'         => $post->post_title,
            'description'   => $post->post_content,
            'trigger'       => get_post_meta( $badge_id, '_sfls_badge_trigger', true ) ?: 'manual',
            'trigger_value' => (int) ( get_post_meta( $badge_id, '_sfls_badge_trigger_value', true ) ?: 1 ),
            'course_id'     => (int) get_post_meta( $badge_id, '_sfls_badge_course_id', true ),
            'points_reward' => (int) ( get_post_meta( $badge_id, '_sfls_badge_points_reward', true ) ?: 50 ),
            'rarity'        => get_post_meta( $badge_id, '_sfls_badge_rarity', true ) ?: 'common',
            'is_hidden'     => (bool) get_post_meta( $badge_id, '_sfls_badge_hidden', true ),
            'icon_type'     => $icon_type,
            'icon'          => 'image' === $icon_type ? get_the_post_thumbnail_url( $badge_id, 'thumbnail' ) : $icon_emoji,
            'color'         => get_post_meta( $badge_id, '_sfls_badge_color', true ) ?: '#4f46e5',
        );
    }

    /**
     * Get all active badges.
     *
     * @param bool $include_hidden Include hidden badges.
     * @return array
     */
    public static function get_all_badges( bool $include_hidden = false ): array {
        $args = array(
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        );

        if ( ! $include_hidden ) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key'     => '_sfls_badge_hidden',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'   => '_sfls_badge_hidden',
                    'value' => '0',
                ),
            );
        }

        $badges = get_posts( $args );

        return array_map( function ( $post ) {
            return self::get_badge( $post->ID );
        }, $badges );
    }
}
