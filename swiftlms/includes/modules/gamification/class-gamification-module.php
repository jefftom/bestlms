<?php
/**
 * Gamification Module
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Gamification;

use SwiftLMS\Core\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Gamification Module class.
 */
class Gamification_Module extends AbstractModule {

    /**
     * Module ID.
     *
     * @var string
     */
    protected string $id = 'gamification';

    /**
     * Module name.
     *
     * @var string
     */
    protected string $name = 'Gamification';

    /**
     * Module description.
     *
     * @var string
     */
    protected string $description = 'Points, badges, levels, and leaderboards to increase engagement.';

    /**
     * Default point values.
     *
     * @var array
     */
    private array $default_points = array(
        'lesson_complete'    => 10,
        'course_complete'    => 100,
        'quiz_pass'          => 25,
        'quiz_perfect'       => 50,
        'assignment_submit'  => 15,
        'assignment_graded'  => 20,
        'daily_login'        => 5,
        'streak_bonus'       => 10,
    );

    /**
     * Initialize module.
     */
    public function init(): void {
        // Initialize CPT.
        Badge::init();

        // Register hooks.
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_pages' ), 30 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Point triggers.
        add_action( 'swiftlms_lesson_completed', array( $this, 'on_lesson_complete' ), 10, 3 );
        add_action( 'swiftlms_course_completed', array( $this, 'on_course_complete' ), 10, 2 );
        add_action( 'swiftlms_quiz_completed', array( $this, 'on_quiz_complete' ), 10, 4 );
        add_action( 'swiftlms_assignment_submitted', array( $this, 'on_assignment_submit' ), 10, 3 );
        add_action( 'swiftlms_assignment_graded', array( $this, 'on_assignment_graded' ), 10, 3 );
        add_action( 'wp_login', array( $this, 'on_user_login' ), 10, 2 );

        // Badge triggers.
        add_action( 'swiftlms_points_awarded', array( $this, 'check_badge_eligibility' ), 10, 4 );
        add_action( 'swiftlms_user_level_up', array( $this, 'check_level_badges' ), 10, 3 );

        // Shortcodes.
        add_shortcode( 'swiftlms_leaderboard', array( $this, 'leaderboard_shortcode' ) );
        add_shortcode( 'swiftlms_user_points', array( $this, 'user_points_shortcode' ) );
        add_shortcode( 'swiftlms_user_badges', array( $this, 'user_badges_shortcode' ) );
        add_shortcode( 'swiftlms_user_level', array( $this, 'user_level_shortcode' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_sfls_get_badge_notification', array( $this, 'ajax_get_badge_notification' ) );
        add_action( 'wp_ajax_sfls_dismiss_badge_notification', array( $this, 'ajax_dismiss_badge_notification' ) );
    }

    /**
     * Activate module.
     */
    public function activate(): void {
        Points_Table::create_tables();
        User_Badges::create_table();
        flush_rewrite_rules();
    }

    /**
     * Get point value for action.
     *
     * @param string $action Action type.
     * @return int
     */
    private function get_point_value( string $action ): int {
        $settings = get_option( 'swiftlms_gamification_points', array() );
        return (int) ( $settings[ $action ] ?? $this->default_points[ $action ] ?? 0 );
    }

    /**
     * On lesson complete.
     *
     * @param int $user_id   User ID.
     * @param int $lesson_id Lesson ID.
     * @param int $course_id Course ID.
     */
    public function on_lesson_complete( int $user_id, int $lesson_id, int $course_id ): void {
        $points = $this->get_point_value( 'lesson_complete' );

        if ( $points > 0 ) {
            Points_Table::award_points(
                $user_id,
                $points,
                Points_Table::ACTION_LESSON_COMPLETE,
                $lesson_id,
                'lesson',
                sprintf( __( 'Completed lesson: %s', 'swiftlms' ), get_the_title( $lesson_id ) )
            );
        }

        $this->check_badge_eligibility( $user_id, $points, Points_Table::ACTION_LESSON_COMPLETE, $lesson_id );
    }

    /**
     * On course complete.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     */
    public function on_course_complete( int $user_id, int $course_id ): void {
        $points = $this->get_point_value( 'course_complete' );

        if ( $points > 0 ) {
            Points_Table::award_points(
                $user_id,
                $points,
                Points_Table::ACTION_COURSE_COMPLETE,
                $course_id,
                'course',
                sprintf( __( 'Completed course: %s', 'swiftlms' ), get_the_title( $course_id ) )
            );
        }

        // Check for specific course badge
        $this->check_course_badges( $user_id, $course_id );
        $this->check_badge_eligibility( $user_id, $points, Points_Table::ACTION_COURSE_COMPLETE, $course_id );
    }

    /**
     * On quiz complete.
     *
     * @param int   $user_id    User ID.
     * @param int   $quiz_id    Quiz ID.
     * @param bool  $passed     Whether passed.
     * @param float $percentage Score percentage.
     */
    public function on_quiz_complete( int $user_id, int $quiz_id, bool $passed, float $percentage ): void {
        if ( ! $passed ) {
            return;
        }

        $is_perfect = $percentage >= 100;
        $action     = $is_perfect ? 'quiz_perfect' : 'quiz_pass';
        $points     = $this->get_point_value( $action );

        if ( $points > 0 ) {
            Points_Table::award_points(
                $user_id,
                $points,
                $is_perfect ? Points_Table::ACTION_QUIZ_PERFECT : Points_Table::ACTION_QUIZ_PASS,
                $quiz_id,
                'quiz',
                sprintf(
                    $is_perfect ? __( 'Perfect score on quiz: %s', 'swiftlms' ) : __( 'Passed quiz: %s', 'swiftlms' ),
                    get_the_title( $quiz_id )
                )
            );
        }

        $this->check_badge_eligibility(
            $user_id,
            $points,
            $is_perfect ? Points_Table::ACTION_QUIZ_PERFECT : Points_Table::ACTION_QUIZ_PASS,
            $quiz_id
        );
    }

    /**
     * On assignment submit.
     *
     * @param int $submission_id Submission ID.
     * @param int $assignment_id Assignment ID.
     * @param int $user_id       User ID.
     */
    public function on_assignment_submit( int $submission_id, int $assignment_id, int $user_id ): void {
        $points = $this->get_point_value( 'assignment_submit' );

        if ( $points > 0 ) {
            Points_Table::award_points(
                $user_id,
                $points,
                Points_Table::ACTION_ASSIGNMENT_SUBMIT,
                $assignment_id,
                'assignment',
                sprintf( __( 'Submitted assignment: %s', 'swiftlms' ), get_the_title( $assignment_id ) )
            );
        }
    }

    /**
     * On assignment graded.
     *
     * @param int    $submission_id Submission ID.
     * @param float  $grade         Grade.
     * @param string $feedback      Feedback.
     */
    public function on_assignment_graded( int $submission_id, float $grade, string $feedback ): void {
        global $wpdb;

        $submission = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, assignment_id FROM {$wpdb->prefix}swiftlms_submissions WHERE id = %d",
                $submission_id
            )
        );

        if ( ! $submission ) {
            return;
        }

        $points = $this->get_point_value( 'assignment_graded' );

        if ( $points > 0 ) {
            Points_Table::award_points(
                (int) $submission->user_id,
                $points,
                Points_Table::ACTION_ASSIGNMENT_GRADED,
                (int) $submission->assignment_id,
                'assignment'
            );
        }

        $this->check_badge_eligibility(
            (int) $submission->user_id,
            $points,
            Points_Table::ACTION_ASSIGNMENT_GRADED,
            (int) $submission->assignment_id
        );
    }

    /**
     * On user login.
     *
     * @param string   $user_login Username.
     * @param \WP_User $user       User object.
     */
    public function on_user_login( string $user_login, \WP_User $user ): void {
        $user_id = $user->ID;

        // Check if already logged in today
        if ( Points_Table::has_action_today( $user_id, Points_Table::ACTION_DAILY_LOGIN ) ) {
            return;
        }

        // Update streak
        $streak = Points_Table::update_streak( $user_id );

        // Daily login points
        $daily_points = $this->get_point_value( 'daily_login' );
        if ( $daily_points > 0 ) {
            Points_Table::award_points(
                $user_id,
                $daily_points,
                Points_Table::ACTION_DAILY_LOGIN,
                0,
                '',
                __( 'Daily login bonus', 'swiftlms' )
            );
        }

        // Streak bonus (every 7 days)
        if ( $streak['current'] > 0 && $streak['current'] % 7 === 0 ) {
            $streak_points = $this->get_point_value( 'streak_bonus' ) * ( $streak['current'] / 7 );
            if ( $streak_points > 0 ) {
                Points_Table::award_points(
                    $user_id,
                    $streak_points,
                    Points_Table::ACTION_STREAK_BONUS,
                    0,
                    '',
                    sprintf( __( '%d day streak bonus!', 'swiftlms' ), $streak['current'] )
                );
            }
        }

        // Check streak badges
        $this->check_streak_badges( $user_id, $streak['current'] );
    }

    /**
     * Check badge eligibility.
     *
     * @param int    $user_id      User ID.
     * @param int    $points       Points awarded.
     * @param string $action_type  Action type.
     * @param int    $reference_id Reference ID.
     */
    public function check_badge_eligibility( int $user_id, int $points, string $action_type, int $reference_id ): void {
        $badges      = Badge::get_all_badges( true );
        $user_points = Points_Table::get_user_points( $user_id );

        foreach ( $badges as $badge ) {
            if ( User_Badges::has_badge( $user_id, $badge['id'] ) ) {
                continue;
            }

            $qualified = false;

            switch ( $badge['trigger'] ) {
                case Badge::TRIGGER_COURSES_COMPLETED:
                    $count     = $this->get_user_completion_count( $user_id, 'course' );
                    $qualified = $count >= $badge['trigger_value'];
                    break;

                case Badge::TRIGGER_LESSONS_COMPLETED:
                    $count     = $this->get_user_completion_count( $user_id, 'lesson' );
                    $qualified = $count >= $badge['trigger_value'];
                    break;

                case Badge::TRIGGER_QUIZZES_PASSED:
                    $count     = $this->get_user_quiz_count( $user_id, false );
                    $qualified = $count >= $badge['trigger_value'];
                    break;

                case Badge::TRIGGER_PERFECT_QUIZZES:
                    $count     = $this->get_user_quiz_count( $user_id, true );
                    $qualified = $count >= $badge['trigger_value'];
                    break;

                case Badge::TRIGGER_POINTS_EARNED:
                    $qualified = (int) $user_points->total_points >= $badge['trigger_value'];
                    break;

                case Badge::TRIGGER_ASSIGNMENTS_GRADED:
                    $count     = $this->get_user_assignment_count( $user_id );
                    $qualified = $count >= $badge['trigger_value'];
                    break;
            }

            if ( $qualified ) {
                User_Badges::award_badge( $user_id, $badge['id'] );
            }
        }
    }

    /**
     * Check course-specific badges.
     *
     * @param int $user_id   User ID.
     * @param int $course_id Course ID.
     */
    private function check_course_badges( int $user_id, int $course_id ): void {
        $badges = Badge::get_all_badges( true );

        foreach ( $badges as $badge ) {
            if ( $badge['trigger'] !== Badge::TRIGGER_SPECIFIC_COURSE ) {
                continue;
            }

            if ( $badge['course_id'] === $course_id && ! User_Badges::has_badge( $user_id, $badge['id'] ) ) {
                User_Badges::award_badge( $user_id, $badge['id'] );
            }
        }
    }

    /**
     * Check streak badges.
     *
     * @param int $user_id User ID.
     * @param int $streak  Current streak.
     */
    private function check_streak_badges( int $user_id, int $streak ): void {
        $badges = Badge::get_all_badges( true );

        foreach ( $badges as $badge ) {
            if ( $badge['trigger'] !== Badge::TRIGGER_STREAK_DAYS ) {
                continue;
            }

            if ( $streak >= $badge['trigger_value'] && ! User_Badges::has_badge( $user_id, $badge['id'] ) ) {
                User_Badges::award_badge( $user_id, $badge['id'] );
            }
        }
    }

    /**
     * Check level badges.
     *
     * @param int   $user_id   User ID.
     * @param array $new_level New level data.
     * @param int   $old_level Old level ID.
     */
    public function check_level_badges( int $user_id, array $new_level, int $old_level ): void {
        $badges = Badge::get_all_badges( true );

        foreach ( $badges as $badge ) {
            if ( $badge['trigger'] !== Badge::TRIGGER_LEVEL_REACHED ) {
                continue;
            }

            if ( $new_level['id'] >= $badge['trigger_value'] && ! User_Badges::has_badge( $user_id, $badge['id'] ) ) {
                User_Badges::award_badge( $user_id, $badge['id'] );
            }
        }
    }

    /**
     * Get user completion count.
     *
     * @param int    $user_id User ID.
     * @param string $type    Type: course or lesson.
     * @return int
     */
    private function get_user_completion_count( int $user_id, string $type ): int {
        global $wpdb;

        $content_type = 'course' === $type ? 'course' : 'lesson';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT content_id) FROM {$wpdb->prefix}swiftlms_progress
                WHERE user_id = %d AND content_type = %s AND completed = 1",
                $user_id,
                $content_type
            )
        );
    }

    /**
     * Get user quiz count.
     *
     * @param int  $user_id      User ID.
     * @param bool $perfect_only Only perfect scores.
     * @return int
     */
    private function get_user_quiz_count( int $user_id, bool $perfect_only = false ): int {
        global $wpdb;

        $where = $wpdb->prepare( "user_id = %d AND passed = 1", $user_id );

        if ( $perfect_only ) {
            $where .= " AND percentage >= 100";
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT quiz_id) FROM {$wpdb->prefix}swiftlms_quiz_attempts WHERE {$where}"
        );
    }

    /**
     * Get user graded assignment count.
     *
     * @param int $user_id User ID.
     * @return int
     */
    private function get_user_assignment_count( int $user_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}swiftlms_submissions WHERE user_id = %d AND status = 'graded'",
                $user_id
            )
        );
    }

    /**
     * Register REST routes.
     */
    public function register_rest_routes(): void {
        register_rest_route(
            'swiftlms/v1',
            '/gamification/leaderboard',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_leaderboard_api' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'period' => array(
                        'default'           => 'all_time',
                        'sanitize_callback' => 'sanitize_key',
                    ),
                    'limit'  => array(
                        'default'           => 10,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/gamification/user/(?P<id>\d+)',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_user_gamification_api' ),
                'permission_callback' => array( $this, 'check_user_permission' ),
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/gamification/me',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_current_user_gamification' ),
                'permission_callback' => 'is_user_logged_in',
            )
        );

        register_rest_route(
            'swiftlms/v1',
            '/gamification/badges',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_badges_api' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Check user permission.
     *
     * @param \WP_REST_Request $request Request.
     * @return bool
     */
    public function check_user_permission( \WP_REST_Request $request ): bool {
        $user_id = (int) $request->get_param( 'id' );
        return $user_id === get_current_user_id() || current_user_can( 'edit_users' );
    }

    /**
     * Get leaderboard API.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function get_leaderboard_api( \WP_REST_Request $request ): \WP_REST_Response {
        $period = $request->get_param( 'period' );
        $limit  = min( 50, $request->get_param( 'limit' ) );

        $leaderboard = Points_Table::get_leaderboard( $period, $limit );

        $data = array();
        $rank = 1;

        foreach ( $leaderboard as $entry ) {
            $level     = Levels::get_level( (int) ( $entry->level_id ?? 1 ) );
            $points    = 'all_time' === $period ? (int) $entry->total_points : (int) $entry->period_points;
            $avatar    = get_avatar_url( $entry->user_id, array( 'size' => 64 ) );

            $data[] = array(
                'rank'         => $rank++,
                'user_id'      => (int) $entry->user_id,
                'display_name' => $entry->display_name,
                'avatar'       => $avatar,
                'points'       => $points,
                'level'        => $level,
            );
        }

        return rest_ensure_response( $data );
    }

    /**
     * Get user gamification data API.
     *
     * @param \WP_REST_Request $request Request.
     * @return \WP_REST_Response
     */
    public function get_user_gamification_api( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id = (int) $request->get_param( 'id' );
        return rest_ensure_response( $this->get_user_gamification_data( $user_id ) );
    }

    /**
     * Get current user gamification.
     *
     * @return \WP_REST_Response
     */
    public function get_current_user_gamification(): \WP_REST_Response {
        return rest_ensure_response( $this->get_user_gamification_data( get_current_user_id() ) );
    }

    /**
     * Get user gamification data.
     *
     * @param int $user_id User ID.
     * @return array
     */
    private function get_user_gamification_data( int $user_id ): array {
        $points_data   = Points_Table::get_user_points( $user_id );
        $level         = Levels::get_level( (int) $points_data->level_id );
        $next_level    = Levels::get_next_level( (int) $points_data->level_id );
        $progress      = Levels::get_level_progress( (int) $points_data->total_points, (int) $points_data->level_id );
        $badges        = User_Badges::get_user_badges( $user_id );
        $rank          = Points_Table::get_user_rank( $user_id );
        $transactions  = Points_Table::get_user_transactions( $user_id, array( 'per_page' => 10 ) );

        return array(
            'points'        => array(
                'total'     => (int) $points_data->total_points,
                'available' => (int) $points_data->available_points,
            ),
            'level'         => $level,
            'next_level'    => $next_level,
            'progress'      => $progress,
            'streak'        => array(
                'current' => (int) $points_data->current_streak,
                'longest' => (int) $points_data->longest_streak,
            ),
            'badges'        => $badges,
            'badge_count'   => count( $badges ),
            'rank'          => $rank,
            'transactions'  => array_map( function ( $t ) {
                return array(
                    'id'          => (int) $t->id,
                    'points'      => (int) $t->points,
                    'action'      => $t->action_type,
                    'description' => $t->description,
                    'created_at'  => $t->created_at,
                );
            }, $transactions ),
        );
    }

    /**
     * Get badges API.
     *
     * @return \WP_REST_Response
     */
    public function get_badges_api(): \WP_REST_Response {
        $badges = Badge::get_all_badges( false );

        // Add earned count for each badge
        foreach ( $badges as &$badge ) {
            $badge['earned_count'] = User_Badges::get_badge_award_count( $badge['id'] );
        }

        return rest_ensure_response( $badges );
    }

    /**
     * Leaderboard shortcode.
     *
     * @param array $atts Attributes.
     * @return string
     */
    public function leaderboard_shortcode( array $atts = array() ): string {
        $atts = shortcode_atts(
            array(
                'period' => 'all_time',
                'limit'  => 10,
                'title'  => __( 'Leaderboard', 'swiftlms' ),
            ),
            $atts
        );

        $leaderboard = Points_Table::get_leaderboard( $atts['period'], (int) $atts['limit'] );

        if ( empty( $leaderboard ) ) {
            return '<div class="sfls-leaderboard-empty">' . esc_html__( 'No rankings yet.', 'swiftlms' ) . '</div>';
        }

        ob_start();
        ?>
        <div class="sfls-leaderboard">
            <?php if ( $atts['title'] ) : ?>
                <h3 class="sfls-leaderboard-title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <?php endif; ?>

            <div class="sfls-leaderboard-tabs">
                <button class="sfls-tab-btn <?php echo 'all_time' === $atts['period'] ? 'active' : ''; ?>" data-period="all_time">
                    <?php esc_html_e( 'All Time', 'swiftlms' ); ?>
                </button>
                <button class="sfls-tab-btn <?php echo 'monthly' === $atts['period'] ? 'active' : ''; ?>" data-period="monthly">
                    <?php esc_html_e( 'This Month', 'swiftlms' ); ?>
                </button>
                <button class="sfls-tab-btn <?php echo 'weekly' === $atts['period'] ? 'active' : ''; ?>" data-period="weekly">
                    <?php esc_html_e( 'This Week', 'swiftlms' ); ?>
                </button>
            </div>

            <div class="sfls-leaderboard-list">
                <?php
                $rank = 1;
                foreach ( $leaderboard as $entry ) :
                    $level  = Levels::get_level( (int) ( $entry->level_id ?? 1 ) );
                    $points = 'all_time' === $atts['period'] ? (int) $entry->total_points : (int) $entry->period_points;
                    $is_current = is_user_logged_in() && get_current_user_id() === (int) $entry->user_id;
                    ?>
                    <div class="sfls-leaderboard-item <?php echo $rank <= 3 ? 'sfls-top-' . $rank : ''; ?> <?php echo $is_current ? 'sfls-current-user' : ''; ?>">
                        <span class="sfls-rank"><?php echo esc_html( $rank ); ?></span>
                        <img src="<?php echo esc_url( get_avatar_url( $entry->user_id, array( 'size' => 48 ) ) ); ?>"
                             alt="" class="sfls-avatar">
                        <div class="sfls-user-info">
                            <span class="sfls-user-name"><?php echo esc_html( $entry->display_name ); ?></span>
                            <?php echo Levels::get_level_badge( (int) ( $entry->level_id ?? 1 ), false ); ?>
                        </div>
                        <span class="sfls-points"><?php echo esc_html( number_format( $points ) ); ?> pts</span>
                    </div>
                    <?php
                    $rank++;
                endforeach;
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * User points shortcode.
     *
     * @param array $atts Attributes.
     * @return string
     */
    public function user_points_shortcode( array $atts = array() ): string {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $user_id     = get_current_user_id();
        $points_data = Points_Table::get_user_points( $user_id );

        ob_start();
        ?>
        <span class="sfls-user-points-badge">
            <span class="sfls-points-icon">⭐</span>
            <span class="sfls-points-value"><?php echo esc_html( number_format( $points_data->total_points ) ); ?></span>
        </span>
        <?php
        return ob_get_clean();
    }

    /**
     * User badges shortcode.
     *
     * @param array $atts Attributes.
     * @return string
     */
    public function user_badges_shortcode( array $atts = array() ): string {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'limit' => 0,
            ),
            $atts
        );

        $user_id = get_current_user_id();
        $badges  = User_Badges::get_user_badges( $user_id );

        if ( $atts['limit'] > 0 ) {
            $badges = array_slice( $badges, 0, $atts['limit'] );
        }

        if ( empty( $badges ) ) {
            return '<div class="sfls-no-badges">' . esc_html__( 'No badges earned yet.', 'swiftlms' ) . '</div>';
        }

        ob_start();
        ?>
        <div class="sfls-badges-grid">
            <?php foreach ( $badges as $badge ) : ?>
                <div class="sfls-badge-item sfls-rarity-<?php echo esc_attr( $badge['rarity'] ); ?>"
                     title="<?php echo esc_attr( $badge['title'] ); ?>">
                    <span class="sfls-badge-icon" style="background-color: <?php echo esc_attr( $badge['color'] ); ?>20;">
                        <?php if ( $badge['icon_type'] === 'image' && $badge['icon'] ) : ?>
                            <img src="<?php echo esc_url( $badge['icon'] ); ?>" alt="">
                        <?php else : ?>
                            <?php echo esc_html( $badge['icon'] ); ?>
                        <?php endif; ?>
                    </span>
                    <span class="sfls-badge-name"><?php echo esc_html( $badge['title'] ); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * User level shortcode.
     *
     * @param array $atts Attributes.
     * @return string
     */
    public function user_level_shortcode( array $atts = array() ): string {
        if ( ! is_user_logged_in() ) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'show_progress' => 'yes',
            ),
            $atts
        );

        $user_id     = get_current_user_id();
        $points_data = Points_Table::get_user_points( $user_id );
        $level       = Levels::get_level( (int) $points_data->level_id );
        $progress    = Levels::get_level_progress( (int) $points_data->total_points, (int) $points_data->level_id );

        ob_start();
        ?>
        <div class="sfls-user-level-widget">
            <div class="sfls-level-display">
                <?php echo Levels::get_level_badge( (int) $points_data->level_id ); ?>
            </div>
            <?php if ( 'yes' === $atts['show_progress'] && ! $progress['is_max_level'] ) : ?>
                <div class="sfls-level-progress">
                    <div class="sfls-progress-bar">
                        <div class="sfls-progress-fill" style="width: <?php echo esc_attr( $progress['percentage'] ); ?>%;"></div>
                    </div>
                    <span class="sfls-progress-text">
                        <?php printf( esc_html__( '%d pts to next level', 'swiftlms' ), $progress['points_remaining'] ); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX get badge notification.
     */
    public function ajax_get_badge_notification(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error();
        }

        $badges = User_Badges::get_unnotified_badges( get_current_user_id() );

        if ( empty( $badges ) ) {
            wp_send_json_success( array( 'badges' => array() ) );
        }

        wp_send_json_success( array( 'badges' => $badges ) );
    }

    /**
     * AJAX dismiss badge notification.
     */
    public function ajax_dismiss_badge_notification(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error();
        }

        User_Badges::mark_badges_notified( get_current_user_id() );
        wp_send_json_success();
    }

    /**
     * Add admin pages.
     */
    public function add_admin_pages(): void {
        add_submenu_page(
            'swiftlms',
            __( 'Gamification Settings', 'swiftlms' ),
            __( 'Gamification', 'swiftlms' ),
            'manage_options',
            'sfls-gamification',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings(): void {
        register_setting( 'swiftlms_gamification', 'swiftlms_gamification_points' );
        register_setting( 'swiftlms_gamification', 'swiftlms_gamification_enabled' );
    }

    /**
     * Render settings page.
     */
    public function render_settings_page(): void {
        $points = get_option( 'swiftlms_gamification_points', $this->default_points );
        $points = wp_parse_args( $points, $this->default_points );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Gamification Settings', 'swiftlms' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'swiftlms_gamification' ); ?>

                <h2><?php esc_html_e( 'Point Values', 'swiftlms' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Lesson Complete', 'swiftlms' ); ?></th>
                        <td>
                            <input type="number" name="swiftlms_gamification_points[lesson_complete]"
                                   value="<?php echo esc_attr( $points['lesson_complete'] ); ?>" min="0" class="small-text">
                            <?php esc_html_e( 'points', 'swiftlms' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Course Complete', 'swiftlms' ); ?></th>
                        <td>
                            <input type="number" name="swiftlms_gamification_points[course_complete]"
                                   value="<?php echo esc_attr( $points['course_complete'] ); ?>" min="0" class="small-text">
                            <?php esc_html_e( 'points', 'swiftlms' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Quiz Pass', 'swiftlms' ); ?></th>
                        <td>
                            <input type="number" name="swiftlms_gamification_points[quiz_pass]"
                                   value="<?php echo esc_attr( $points['quiz_pass'] ); ?>" min="0" class="small-text">
                            <?php esc_html_e( 'points', 'swiftlms' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Perfect Quiz Score', 'swiftlms' ); ?></th>
                        <td>
                            <input type="number" name="swiftlms_gamification_points[quiz_perfect]"
                                   value="<?php echo esc_attr( $points['quiz_perfect'] ); ?>" min="0" class="small-text">
                            <?php esc_html_e( 'points', 'swiftlms' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Assignment Submit', 'swiftlms' ); ?></th>
                        <td>
                            <input type="number" name="swiftlms_gamification_points[assignment_submit]"
                                   value="<?php echo esc_attr( $points['assignment_submit'] ); ?>" min="0" class="small-text">
                            <?php esc_html_e( 'points', 'swiftlms' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Assignment Graded', 'swiftlms' ); ?></th>
                        <td>
                            <input type="number" name="swiftlms_gamification_points[assignment_graded]"
                                   value="<?php echo esc_attr( $points['assignment_graded'] ); ?>" min="0" class="small-text">
                            <?php esc_html_e( 'points', 'swiftlms' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Daily Login', 'swiftlms' ); ?></th>
                        <td>
                            <input type="number" name="swiftlms_gamification_points[daily_login]"
                                   value="<?php echo esc_attr( $points['daily_login'] ); ?>" min="0" class="small-text">
                            <?php esc_html_e( 'points', 'swiftlms' ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Streak Bonus (per week)', 'swiftlms' ); ?></th>
                        <td>
                            <input type="number" name="swiftlms_gamification_points[streak_bonus]"
                                   value="<?php echo esc_attr( $points['streak_bonus'] ); ?>" min="0" class="small-text">
                            <?php esc_html_e( 'points', 'swiftlms' ); ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend_assets(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }

        wp_enqueue_style(
            'sfls-gamification',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/gamification/assets/css/gamification.css',
            array(),
            SWIFTLMS_VERSION
        );

        wp_enqueue_script(
            'sfls-gamification',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/gamification/assets/js/gamification.js',
            array( 'jquery' ),
            SWIFTLMS_VERSION,
            true
        );

        wp_localize_script(
            'sfls-gamification',
            'swiftlms_gamification',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'sfls_ajax' ),
                'rest_url' => rest_url( 'swiftlms/v1/gamification/' ),
                'i18n'     => array(
                    'badge_earned'     => __( 'Badge Earned!', 'swiftlms' ),
                    'points_earned'    => __( '+%d points', 'swiftlms' ),
                    'level_up'         => __( 'Level Up!', 'swiftlms' ),
                    'congratulations'  => __( 'Congratulations!', 'swiftlms' ),
                ),
            )
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( 'swiftlms_page_sfls-gamification' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'sfls-gamification-admin',
            SWIFTLMS_PLUGIN_URL . 'includes/modules/gamification/assets/css/admin.css',
            array(),
            SWIFTLMS_VERSION
        );
    }
}
