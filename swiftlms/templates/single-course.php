<?php
/**
 * Single Course template.
 *
 * @package SwiftLMS\Templates
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$course_id    = get_the_ID();
$user_id      = get_current_user_id();
$is_enrolled  = $user_id ? swiftlms()->enrollment()->is_enrolled( $user_id, $course_id ) : false;
$enrollment   = $user_id ? swiftlms()->enrollment()->get_enrollment( $user_id, $course_id ) : null;
$course_meta  = swiftlms()->get_component( 'course' )->get_meta( $course_id );
$lessons      = swiftlms()->get_component( 'course' )->get_lessons( $course_id, 'publish' );
?>

<div class="swiftlms-course-single">
    <?php while ( have_posts() ) : the_post(); ?>

    <!-- Course Header -->
    <header class="swiftlms-course-header">
        <div class="swiftlms-container">
            <?php if ( has_post_thumbnail() ) : ?>
                <div class="swiftlms-course-thumbnail">
                    <?php the_post_thumbnail( 'large' ); ?>
                </div>
            <?php endif; ?>

            <div class="swiftlms-course-info">
                <h1 class="swiftlms-course-title"><?php the_title(); ?></h1>

                <?php if ( has_excerpt() ) : ?>
                    <div class="swiftlms-course-excerpt">
                        <?php the_excerpt(); ?>
                    </div>
                <?php endif; ?>

                <div class="swiftlms-course-meta">
                    <?php if ( $course_meta['duration'] ) : ?>
                        <span class="swiftlms-meta-item">
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo esc_html( $course_meta['duration'] ); ?>
                        </span>
                    <?php endif; ?>

                    <span class="swiftlms-meta-item">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php
                        printf(
                            /* translators: %d: Number of lessons */
                            esc_html( _n( '%d Lesson', '%d Lessons', $course_meta['lesson_count'], 'swiftlms' ) ),
                            esc_html( $course_meta['lesson_count'] )
                        );
                        ?>
                    </span>

                    <?php
                    $difficulty = get_the_terms( $course_id, 'sfls_difficulty' );
                    if ( $difficulty && ! is_wp_error( $difficulty ) ) :
                        ?>
                        <span class="swiftlms-meta-item">
                            <span class="dashicons dashicons-chart-bar"></span>
                            <?php echo esc_html( $difficulty[0]->name ); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Enrollment Status / Action -->
                <div class="swiftlms-course-action">
                    <?php if ( $is_enrolled ) : ?>
                        <?php
                        $resume = \SwiftLMS\Core\User::get_resume_data( $user_id, $course_id );
                        ?>
                        <div class="swiftlms-enrolled-status">
                            <div class="swiftlms-progress-bar">
                                <div class="swiftlms-progress-fill" style="width: <?php echo esc_attr( $enrollment->progress_percent ); ?>%;"></div>
                            </div>
                            <span class="swiftlms-progress-text">
                                <?php echo esc_html( round( $enrollment->progress_percent, 1 ) ); ?>% <?php esc_html_e( 'Complete', 'swiftlms' ); ?>
                            </span>
                        </div>

                        <?php if ( $resume['url'] ) : ?>
                            <a href="<?php echo esc_url( $resume['url'] ); ?>" class="swiftlms-button swiftlms-button-primary">
                                <?php
                                if ( $enrollment->progress_percent > 0 ) {
                                    esc_html_e( 'Continue Learning', 'swiftlms' );
                                } else {
                                    esc_html_e( 'Start Course', 'swiftlms' );
                                }
                                ?>
                            </a>
                        <?php endif; ?>

                    <?php elseif ( $course_meta['access_type'] === 'open' ) : ?>
                        <?php if ( ! empty( $lessons ) ) : ?>
                            <a href="<?php echo esc_url( get_permalink( $lessons[0]->ID ) ); ?>" class="swiftlms-button swiftlms-button-primary">
                                <?php esc_html_e( 'Start Learning', 'swiftlms' ); ?>
                            </a>
                        <?php endif; ?>

                    <?php elseif ( $course_meta['access_type'] === 'paid' ) : ?>
                        <span class="swiftlms-course-price">
                            <?php echo esc_html( '$' . number_format( $course_meta['price'], 2 ) ); ?>
                        </span>
                        <button class="swiftlms-button swiftlms-button-primary" disabled>
                            <?php esc_html_e( 'Purchase Coming Soon', 'swiftlms' ); ?>
                        </button>

                    <?php elseif ( is_user_logged_in() ) : ?>
                        <button class="swiftlms-button swiftlms-button-primary swiftlms-enroll-btn" data-course-id="<?php echo esc_attr( $course_id ); ?>">
                            <?php esc_html_e( 'Enroll Now', 'swiftlms' ); ?>
                        </button>

                    <?php else : ?>
                        <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="swiftlms-button swiftlms-button-primary">
                            <?php esc_html_e( 'Login to Enroll', 'swiftlms' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Course Content -->
    <div class="swiftlms-course-content">
        <div class="swiftlms-container">
            <div class="swiftlms-course-layout">
                <!-- Main Content -->
                <main class="swiftlms-course-main">
                    <!-- Description -->
                    <section class="swiftlms-course-section swiftlms-course-description">
                        <h2><?php esc_html_e( 'About This Course', 'swiftlms' ); ?></h2>
                        <div class="swiftlms-content">
                            <?php the_content(); ?>
                        </div>
                    </section>

                    <!-- Curriculum -->
                    <section class="swiftlms-course-section swiftlms-course-curriculum">
                        <h2><?php esc_html_e( 'Course Curriculum', 'swiftlms' ); ?></h2>

                        <?php if ( empty( $lessons ) ) : ?>
                            <p class="swiftlms-no-content"><?php esc_html_e( 'No lessons have been added yet.', 'swiftlms' ); ?></p>
                        <?php else : ?>
                            <ul class="swiftlms-lessons-list">
                                <?php foreach ( $lessons as $index => $lesson ) :
                                    $lesson_meta = swiftlms()->get_component( 'lesson' )->get_meta( $lesson->ID );
                                    $is_preview  = $lesson_meta['is_preview'];
                                    $is_available = $is_enrolled || $is_preview || $course_meta['access_type'] === 'open';

                                    $progress = null;
                                    if ( $user_id ) {
                                        $progress = swiftlms()->progress()->get_progress( $user_id, $lesson->ID );
                                    }

                                    $status_class = '';
                                    if ( $progress ) {
                                        $status_class = 'swiftlms-lesson-' . $progress['status'];
                                    }
                                    ?>
                                    <li class="swiftlms-lesson-item <?php echo esc_attr( $status_class ); ?>">
                                        <div class="swiftlms-lesson-number"><?php echo esc_html( $index + 1 ); ?></div>

                                        <div class="swiftlms-lesson-info">
                                            <?php if ( $is_available ) : ?>
                                                <a href="<?php echo esc_url( get_permalink( $lesson->ID ) ); ?>" class="swiftlms-lesson-title">
                                                    <?php echo esc_html( $lesson->post_title ); ?>
                                                </a>
                                            <?php else : ?>
                                                <span class="swiftlms-lesson-title swiftlms-lesson-locked">
                                                    <?php echo esc_html( $lesson->post_title ); ?>
                                                    <span class="dashicons dashicons-lock"></span>
                                                </span>
                                            <?php endif; ?>

                                            <div class="swiftlms-lesson-meta">
                                                <?php if ( $lesson_meta['duration'] ) : ?>
                                                    <span><?php echo esc_html( $lesson_meta['duration'] ); ?></span>
                                                <?php endif; ?>

                                                <?php if ( $is_preview && ! $is_enrolled ) : ?>
                                                    <span class="swiftlms-preview-badge"><?php esc_html_e( 'Preview', 'swiftlms' ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="swiftlms-lesson-status">
                                            <?php if ( $progress && $progress['status'] === 'completed' ) : ?>
                                                <span class="dashicons dashicons-yes-alt swiftlms-completed"></span>
                                            <?php elseif ( $progress && $progress['status'] === 'in_progress' ) : ?>
                                                <span class="swiftlms-in-progress"><?php echo esc_html( round( $progress['progress_percent'] ) ); ?>%</span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                </main>

                <!-- Sidebar -->
                <aside class="swiftlms-course-sidebar">
                    <!-- Instructor -->
                    <div class="swiftlms-sidebar-widget swiftlms-instructor">
                        <h3><?php esc_html_e( 'Instructor', 'swiftlms' ); ?></h3>
                        <?php
                        $author_id = get_the_author_meta( 'ID' );
                        ?>
                        <div class="swiftlms-instructor-info">
                            <?php echo get_avatar( $author_id, 80 ); ?>
                            <div>
                                <strong><?php the_author(); ?></strong>
                                <?php if ( get_the_author_meta( 'description' ) ) : ?>
                                    <p><?php echo esc_html( wp_trim_words( get_the_author_meta( 'description' ), 20 ) ); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Categories -->
                    <?php
                    $categories = get_the_terms( $course_id, 'sfls_course_category' );
                    if ( $categories && ! is_wp_error( $categories ) ) :
                        ?>
                        <div class="swiftlms-sidebar-widget">
                            <h3><?php esc_html_e( 'Categories', 'swiftlms' ); ?></h3>
                            <ul class="swiftlms-category-list">
                                <?php foreach ( $categories as $category ) : ?>
                                    <li>
                                        <a href="<?php echo esc_url( get_term_link( $category ) ); ?>">
                                            <?php echo esc_html( $category->name ); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- Share -->
                    <div class="swiftlms-sidebar-widget">
                        <h3><?php esc_html_e( 'Share', 'swiftlms' ); ?></h3>
                        <div class="swiftlms-share-links">
                            <a href="https://twitter.com/intent/tweet?url=<?php echo esc_url( get_permalink() ); ?>&text=<?php echo esc_attr( get_the_title() ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Share on Twitter', 'swiftlms' ); ?>">
                                <span class="dashicons dashicons-twitter"></span>
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_url( get_permalink() ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Share on Facebook', 'swiftlms' ); ?>">
                                <span class="dashicons dashicons-facebook"></span>
                            </a>
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo esc_url( get_permalink() ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Share on LinkedIn', 'swiftlms' ); ?>">
                                <span class="dashicons dashicons-linkedin"></span>
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
