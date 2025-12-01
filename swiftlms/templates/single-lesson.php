<?php
/**
 * Single Lesson template.
 *
 * @package SwiftLMS\Templates
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$lesson_id   = get_the_ID();
$user_id     = get_current_user_id();
$lesson_meta = swiftlms()->get_component( 'lesson' )->get_meta( $lesson_id );
$course_id   = $lesson_meta['course_id'];
$course      = get_post( $course_id );

// Check access.
$has_access = false;
if ( user_can( $user_id, 'manage_options' ) ) {
    $has_access = true;
} elseif ( $lesson_meta['is_preview'] ) {
    $has_access = true;
} else {
    $access_type = get_post_meta( $course_id, '_swiftlms_access_type', true );
    if ( $access_type === 'open' ) {
        $has_access = true;
    } elseif ( $user_id ) {
        $has_access = swiftlms()->enrollment()->is_enrolled( $user_id, $course_id );
    }
}

// Get all lessons in course for navigation.
$all_lessons   = swiftlms()->get_component( 'course' )->get_lessons( $course_id, 'publish' );
$current_index = 0;
foreach ( $all_lessons as $index => $l ) {
    if ( $l->ID === $lesson_id ) {
        $current_index = $index;
        break;
    }
}
$prev_lesson = $current_index > 0 ? $all_lessons[ $current_index - 1 ] : null;
$next_lesson = $current_index < count( $all_lessons ) - 1 ? $all_lessons[ $current_index + 1 ] : null;

// Get progress.
$progress = null;
if ( $user_id ) {
    $progress = swiftlms()->progress()->get_progress( $user_id, $lesson_id );
}

$is_completed = $progress && $progress['status'] === 'completed';
?>

<div class="swiftlms-lesson-single <?php echo $has_access ? '' : 'swiftlms-lesson-locked'; ?>">
    <?php while ( have_posts() ) : the_post(); ?>

    <!-- Focus Mode Header -->
    <header class="swiftlms-lesson-header">
        <div class="swiftlms-lesson-nav-left">
            <a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="swiftlms-back-to-course">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <span class="swiftlms-back-text"><?php echo esc_html( $course->post_title ); ?></span>
            </a>
        </div>

        <div class="swiftlms-lesson-nav-center">
            <span class="swiftlms-lesson-progress-text">
                <?php
                printf(
                    /* translators: 1: Current lesson number, 2: Total lessons */
                    esc_html__( 'Lesson %1$d of %2$d', 'swiftlms' ),
                    $current_index + 1,
                    count( $all_lessons )
                );
                ?>
            </span>
        </div>

        <div class="swiftlms-lesson-nav-right">
            <?php if ( $prev_lesson ) : ?>
                <a href="<?php echo esc_url( get_permalink( $prev_lesson->ID ) ); ?>" class="swiftlms-nav-btn swiftlms-prev-lesson" title="<?php esc_attr_e( 'Previous Lesson', 'swiftlms' ); ?>">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </a>
            <?php endif; ?>

            <?php if ( $next_lesson ) : ?>
                <a href="<?php echo esc_url( get_permalink( $next_lesson->ID ) ); ?>" class="swiftlms-nav-btn swiftlms-next-lesson" title="<?php esc_attr_e( 'Next Lesson', 'swiftlms' ); ?>">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ( ! $has_access ) : ?>
        <!-- Access Denied -->
        <div class="swiftlms-access-denied">
            <div class="swiftlms-access-denied-content">
                <span class="dashicons dashicons-lock"></span>
                <h2><?php esc_html_e( 'Lesson Locked', 'swiftlms' ); ?></h2>
                <p><?php esc_html_e( 'You need to enroll in this course to access this lesson.', 'swiftlms' ); ?></p>

                <?php if ( is_user_logged_in() ) : ?>
                    <a href="<?php echo esc_url( get_permalink( $course_id ) ); ?>" class="swiftlms-button swiftlms-button-primary">
                        <?php esc_html_e( 'View Course', 'swiftlms' ); ?>
                    </a>
                <?php else : ?>
                    <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="swiftlms-button swiftlms-button-primary">
                        <?php esc_html_e( 'Login to Continue', 'swiftlms' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

    <?php else : ?>
        <!-- Lesson Content -->
        <div class="swiftlms-lesson-layout">
            <!-- Main Content Area -->
            <main class="swiftlms-lesson-main">
                <!-- Video Player -->
                <?php if ( $lesson_meta['video_url'] ) : ?>
                    <div class="swiftlms-video-container" id="swiftlms-video-container">
                        <div class="swiftlms-video-wrapper">
                            <?php
                            $video_url = $lesson_meta['video_url'];

                            // Detect video type and embed appropriately.
                            if ( strpos( $video_url, 'youtube.com' ) !== false || strpos( $video_url, 'youtu.be' ) !== false ) :
                                // YouTube embed.
                                preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches );
                                $youtube_id = $matches[1] ?? '';
                                if ( $youtube_id ) :
                                    ?>
                                    <iframe
                                        id="swiftlms-video-player"
                                        src="https://www.youtube.com/embed/<?php echo esc_attr( $youtube_id ); ?>?enablejsapi=1&rel=0"
                                        frameborder="0"
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                        allowfullscreen
                                    ></iframe>
                                <?php endif; ?>

                            <?php elseif ( strpos( $video_url, 'vimeo.com' ) !== false ) :
                                // Vimeo embed.
                                preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $video_url, $matches );
                                $vimeo_id = $matches[1] ?? '';
                                if ( $vimeo_id ) :
                                    ?>
                                    <iframe
                                        id="swiftlms-video-player"
                                        src="https://player.vimeo.com/video/<?php echo esc_attr( $vimeo_id ); ?>?api=1"
                                        frameborder="0"
                                        allow="autoplay; fullscreen; picture-in-picture"
                                        allowfullscreen
                                    ></iframe>
                                <?php endif; ?>

                            <?php else :
                                // Self-hosted video.
                                ?>
                                <video
                                    id="swiftlms-video-player"
                                    src="<?php echo esc_url( $video_url ); ?>"
                                    controls
                                    playsinline
                                ></video>
                            <?php endif; ?>
                        </div>

                        <?php if ( get_option( 'swiftlms_enable_resume', true ) && $progress && $progress['video_position'] > 0 ) : ?>
                            <div class="swiftlms-resume-prompt" id="swiftlms-resume-prompt" style="display: none;">
                                <p><?php esc_html_e( 'Resume where you left off?', 'swiftlms' ); ?></p>
                                <button type="button" class="swiftlms-button swiftlms-button-small" id="swiftlms-resume-yes">
                                    <?php esc_html_e( 'Yes', 'swiftlms' ); ?>
                                </button>
                                <button type="button" class="swiftlms-button swiftlms-button-small swiftlms-button-secondary" id="swiftlms-resume-no">
                                    <?php esc_html_e( 'Start Over', 'swiftlms' ); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Lesson Title & Content -->
                <div class="swiftlms-lesson-content">
                    <h1 class="swiftlms-lesson-title"><?php the_title(); ?></h1>

                    <?php if ( $lesson_meta['duration'] ) : ?>
                        <div class="swiftlms-lesson-meta">
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo esc_html( $lesson_meta['duration'] ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="swiftlms-lesson-body">
                        <?php the_content(); ?>
                    </div>

                    <!-- Topics -->
                    <?php
                    $topics = swiftlms()->get_component( 'lesson' )->get_topics( $lesson_id, 'publish' );
                    if ( ! empty( $topics ) ) :
                        ?>
                        <div class="swiftlms-lesson-topics">
                            <h3><?php esc_html_e( 'Topics in this lesson', 'swiftlms' ); ?></h3>
                            <ul class="swiftlms-topics-list">
                                <?php foreach ( $topics as $topic ) : ?>
                                    <li>
                                        <a href="<?php echo esc_url( get_permalink( $topic->ID ) ); ?>">
                                            <?php echo esc_html( $topic->post_title ); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Completion Button -->
                    <?php if ( $user_id && $has_access ) : ?>
                        <div class="swiftlms-lesson-actions">
                            <?php if ( $is_completed ) : ?>
                                <span class="swiftlms-completed-badge">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php esc_html_e( 'Completed', 'swiftlms' ); ?>
                                </span>
                            <?php elseif ( $lesson_meta['completion_type'] === 'manual' ) : ?>
                                <button type="button" class="swiftlms-button swiftlms-button-primary swiftlms-mark-complete" id="swiftlms-mark-complete" data-lesson-id="<?php echo esc_attr( $lesson_id ); ?>">
                                    <?php esc_html_e( 'Mark Complete', 'swiftlms' ); ?>
                                </button>
                            <?php endif; ?>

                            <?php if ( $next_lesson ) : ?>
                                <a href="<?php echo esc_url( get_permalink( $next_lesson->ID ) ); ?>" class="swiftlms-button <?php echo $is_completed ? 'swiftlms-button-primary' : 'swiftlms-button-secondary'; ?>">
                                    <?php esc_html_e( 'Next Lesson', 'swiftlms' ); ?>
                                    <span class="dashicons dashicons-arrow-right-alt"></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>

            <!-- Sidebar - Course Navigation -->
            <aside class="swiftlms-lesson-sidebar">
                <div class="swiftlms-sidebar-header">
                    <h3><?php esc_html_e( 'Course Content', 'swiftlms' ); ?></h3>
                    <button type="button" class="swiftlms-sidebar-toggle" id="swiftlms-sidebar-toggle" aria-label="<?php esc_attr_e( 'Toggle sidebar', 'swiftlms' ); ?>">
                        <span class="dashicons dashicons-menu-alt3"></span>
                    </button>
                </div>

                <nav class="swiftlms-course-nav">
                    <ul class="swiftlms-nav-lessons">
                        <?php foreach ( $all_lessons as $index => $nav_lesson ) :
                            $nav_progress = $user_id ? swiftlms()->progress()->get_progress( $user_id, $nav_lesson->ID ) : null;
                            $nav_status   = $nav_progress ? $nav_progress['status'] : 'not_started';
                            $is_current   = $nav_lesson->ID === $lesson_id;
                            ?>
                            <li class="swiftlms-nav-lesson <?php echo $is_current ? 'swiftlms-current' : ''; ?> swiftlms-status-<?php echo esc_attr( $nav_status ); ?>">
                                <a href="<?php echo esc_url( get_permalink( $nav_lesson->ID ) ); ?>">
                                    <span class="swiftlms-nav-number"><?php echo esc_html( $index + 1 ); ?></span>
                                    <span class="swiftlms-nav-title"><?php echo esc_html( $nav_lesson->post_title ); ?></span>
                                    <?php if ( $nav_status === 'completed' ) : ?>
                                        <span class="dashicons dashicons-yes-alt swiftlms-nav-status"></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </aside>
        </div>
    <?php endif; ?>

    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
