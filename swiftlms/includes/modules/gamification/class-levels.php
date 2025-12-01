<?php
/**
 * Levels System
 *
 * @package SwiftLMS
 */

namespace SwiftLMS\Modules\Gamification;

defined( 'ABSPATH' ) || exit;

/**
 * Levels class.
 */
class Levels {

    /**
     * Default levels configuration.
     *
     * @var array
     */
    private static array $default_levels = array(
        1  => array( 'name' => 'Newcomer',      'points' => 0,      'icon' => '🌱', 'color' => '#9CA3AF' ),
        2  => array( 'name' => 'Beginner',      'points' => 100,    'icon' => '🌿', 'color' => '#6EE7B7' ),
        3  => array( 'name' => 'Learner',       'points' => 300,    'icon' => '📚', 'color' => '#60A5FA' ),
        4  => array( 'name' => 'Student',       'points' => 600,    'icon' => '🎓', 'color' => '#818CF8' ),
        5  => array( 'name' => 'Apprentice',    'points' => 1000,   'icon' => '⭐', 'color' => '#FBBF24' ),
        6  => array( 'name' => 'Skilled',       'points' => 1500,   'icon' => '💫', 'color' => '#F59E0B' ),
        7  => array( 'name' => 'Proficient',    'points' => 2500,   'icon' => '🏆', 'color' => '#F97316' ),
        8  => array( 'name' => 'Expert',        'points' => 4000,   'icon' => '💎', 'color' => '#14B8A6' ),
        9  => array( 'name' => 'Master',        'points' => 6000,   'icon' => '👑', 'color' => '#EC4899' ),
        10 => array( 'name' => 'Legend',        'points' => 10000,  'icon' => '🔥', 'color' => '#EF4444' ),
    );

    /**
     * Get all levels.
     *
     * @return array
     */
    public static function get_levels(): array {
        $custom_levels = get_option( 'swiftlms_gamification_levels', array() );

        if ( ! empty( $custom_levels ) ) {
            return $custom_levels;
        }

        return self::$default_levels;
    }

    /**
     * Get level by ID.
     *
     * @param int $level_id Level ID.
     * @return array
     */
    public static function get_level( int $level_id ): array {
        $levels = self::get_levels();

        if ( isset( $levels[ $level_id ] ) ) {
            return array_merge( $levels[ $level_id ], array( 'id' => $level_id ) );
        }

        // Return first level as fallback
        return array_merge( $levels[1], array( 'id' => 1 ) );
    }

    /**
     * Get level for points.
     *
     * @param int $points Total points.
     * @return array
     */
    public static function get_level_for_points( int $points ): array {
        $levels       = self::get_levels();
        $current_level = 1;

        foreach ( $levels as $level_id => $level ) {
            if ( $points >= $level['points'] ) {
                $current_level = $level_id;
            } else {
                break;
            }
        }

        return array_merge( $levels[ $current_level ], array( 'id' => $current_level ) );
    }

    /**
     * Get next level.
     *
     * @param int $current_level_id Current level ID.
     * @return array|null
     */
    public static function get_next_level( int $current_level_id ): ?array {
        $levels   = self::get_levels();
        $next_id  = $current_level_id + 1;

        if ( isset( $levels[ $next_id ] ) ) {
            return array_merge( $levels[ $next_id ], array( 'id' => $next_id ) );
        }

        return null;
    }

    /**
     * Get progress to next level.
     *
     * @param int $points         Current points.
     * @param int $current_level_id Current level ID.
     * @return array
     */
    public static function get_level_progress( int $points, int $current_level_id ): array {
        $current_level = self::get_level( $current_level_id );
        $next_level    = self::get_next_level( $current_level_id );

        if ( ! $next_level ) {
            // Max level reached
            return array(
                'current_points'   => $points,
                'level_start'      => $current_level['points'],
                'level_end'        => $current_level['points'],
                'points_in_level'  => 0,
                'points_needed'    => 0,
                'percentage'       => 100,
                'is_max_level'     => true,
            );
        }

        $level_start     = $current_level['points'];
        $level_end       = $next_level['points'];
        $points_in_level = $points - $level_start;
        $points_needed   = $level_end - $level_start;
        $percentage      = $points_needed > 0 ? min( 100, ( $points_in_level / $points_needed ) * 100 ) : 100;

        return array(
            'current_points'   => $points,
            'level_start'      => $level_start,
            'level_end'        => $level_end,
            'points_in_level'  => $points_in_level,
            'points_needed'    => $points_needed,
            'points_remaining' => max( 0, $level_end - $points ),
            'percentage'       => round( $percentage, 1 ),
            'is_max_level'     => false,
        );
    }

    /**
     * Save custom levels.
     *
     * @param array $levels Levels configuration.
     * @return bool
     */
    public static function save_levels( array $levels ): bool {
        return update_option( 'swiftlms_gamification_levels', $levels );
    }

    /**
     * Reset to default levels.
     *
     * @return bool
     */
    public static function reset_levels(): bool {
        return delete_option( 'swiftlms_gamification_levels' );
    }

    /**
     * Get level badge HTML.
     *
     * @param int  $level_id Level ID.
     * @param bool $show_name Show level name.
     * @return string
     */
    public static function get_level_badge( int $level_id, bool $show_name = true ): string {
        $level = self::get_level( $level_id );

        $html  = '<span class="sfls-level-badge" style="--level-color: ' . esc_attr( $level['color'] ) . ';">';
        $html .= '<span class="sfls-level-icon">' . esc_html( $level['icon'] ) . '</span>';

        if ( $show_name ) {
            $html .= '<span class="sfls-level-name">' . esc_html( $level['name'] ) . '</span>';
        }

        $html .= '</span>';

        return $html;
    }
}
