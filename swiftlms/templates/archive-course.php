<?php
/**
 * Course Archive template.
 *
 * @package SwiftLMS\Templates
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="swiftlms-course-archive">
    <header class="swiftlms-archive-header">
        <div class="swiftlms-container">
            <?php if ( is_tax() ) : ?>
                <h1 class="swiftlms-archive-title"><?php single_term_title(); ?></h1>
                <?php
                $term_description = term_description();
                if ( $term_description ) :
                    ?>
                    <div class="swiftlms-archive-description">
                        <?php echo wp_kses_post( $term_description ); ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <h1 class="swiftlms-archive-title"><?php esc_html_e( 'Courses', 'swiftlms' ); ?></h1>
                <p class="swiftlms-archive-description"><?php esc_html_e( 'Browse our collection of courses and start learning today.', 'swiftlms' ); ?></p>
            <?php endif; ?>
        </div>
    </header>

    <div class="swiftlms-archive-content">
        <div class="swiftlms-container">
            <!-- Filters -->
            <div class="swiftlms-archive-filters">
                <form class="swiftlms-filter-form" method="get">
                    <div class="swiftlms-filter-group">
                        <label for="swiftlms-category-filter"><?php esc_html_e( 'Category', 'swiftlms' ); ?></label>
                        <?php
                        wp_dropdown_categories(
                            array(
                                'taxonomy'          => 'sfls_course_category',
                                'name'              => 'category',
                                'id'                => 'swiftlms-category-filter',
                                'show_option_all'   => __( 'All Categories', 'swiftlms' ),
                                'selected'          => get_query_var( 'sfls_course_category' ),
                                'value_field'       => 'slug',
                                'hide_empty'        => true,
                            )
                        );
                        ?>
                    </div>

                    <div class="swiftlms-filter-group">
                        <label for="swiftlms-difficulty-filter"><?php esc_html_e( 'Difficulty', 'swiftlms' ); ?></label>
                        <?php
                        wp_dropdown_categories(
                            array(
                                'taxonomy'          => 'sfls_difficulty',
                                'name'              => 'difficulty',
                                'id'                => 'swiftlms-difficulty-filter',
                                'show_option_all'   => __( 'All Levels', 'swiftlms' ),
                                'selected'          => get_query_var( 'sfls_difficulty' ),
                                'value_field'       => 'slug',
                                'hide_empty'        => true,
                            )
                        );
                        ?>
                    </div>

                    <div class="swiftlms-filter-group">
                        <label for="swiftlms-search"><?php esc_html_e( 'Search', 'swiftlms' ); ?></label>
                        <input type="search" id="swiftlms-search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search courses...', 'swiftlms' ); ?>">
                    </div>

                    <button type="submit" class="swiftlms-button"><?php esc_html_e( 'Filter', 'swiftlms' ); ?></button>
                </form>
            </div>

            <!-- Course Grid -->
            <?php if ( have_posts() ) : ?>
                <div class="swiftlms-course-grid">
                    <?php while ( have_posts() ) : the_post();
                        $course_id   = get_the_ID();
                        $course_meta = swiftlms()->get_component( 'course' )->get_meta( $course_id );
                        $user_id     = get_current_user_id();
                        $is_enrolled = $user_id ? swiftlms()->enrollment()->is_enrolled( $user_id, $course_id ) : false;
                        ?>

                        <article class="swiftlms-course-card" id="course-<?php the_ID(); ?>">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <a href="<?php the_permalink(); ?>" class="swiftlms-card-thumbnail">
                                    <?php the_post_thumbnail( 'medium_large' ); ?>
                                </a>
                            <?php else : ?>
                                <a href="<?php the_permalink(); ?>" class="swiftlms-card-thumbnail swiftlms-no-thumbnail">
                                    <span class="dashicons dashicons-welcome-learn-more"></span>
                                </a>
                            <?php endif; ?>

                            <div class="swiftlms-card-content">
                                <?php
                                $difficulty = get_the_terms( $course_id, 'sfls_difficulty' );
                                if ( $difficulty && ! is_wp_error( $difficulty ) ) :
                                    ?>
                                    <span class="swiftlms-card-badge"><?php echo esc_html( $difficulty[0]->name ); ?></span>
                                <?php endif; ?>

                                <h3 class="swiftlms-card-title">
                                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                </h3>

                                <?php if ( has_excerpt() ) : ?>
                                    <p class="swiftlms-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 15 ) ); ?></p>
                                <?php endif; ?>

                                <div class="swiftlms-card-meta">
                                    <?php if ( $course_meta['lesson_count'] > 0 ) : ?>
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
                                    <?php endif; ?>

                                    <?php if ( $course_meta['duration'] ) : ?>
                                        <span class="swiftlms-meta-item">
                                            <span class="dashicons dashicons-clock"></span>
                                            <?php echo esc_html( $course_meta['duration'] ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="swiftlms-card-footer">
                                    <?php if ( $course_meta['access_type'] === 'paid' && $course_meta['price'] > 0 ) : ?>
                                        <span class="swiftlms-card-price">$<?php echo esc_html( number_format( $course_meta['price'], 2 ) ); ?></span>
                                    <?php elseif ( $course_meta['access_type'] === 'open' || $course_meta['access_type'] === 'free' ) : ?>
                                        <span class="swiftlms-card-price swiftlms-free"><?php esc_html_e( 'Free', 'swiftlms' ); ?></span>
                                    <?php endif; ?>

                                    <?php if ( $is_enrolled ) : ?>
                                        <span class="swiftlms-enrolled-badge"><?php esc_html_e( 'Enrolled', 'swiftlms' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>

                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <nav class="swiftlms-pagination">
                    <?php
                    the_posts_pagination(
                        array(
                            'mid_size'  => 2,
                            'prev_text' => '<span class="dashicons dashicons-arrow-left-alt2"></span> ' . __( 'Previous', 'swiftlms' ),
                            'next_text' => __( 'Next', 'swiftlms' ) . ' <span class="dashicons dashicons-arrow-right-alt2"></span>',
                        )
                    );
                    ?>
                </nav>

            <?php else : ?>
                <div class="swiftlms-no-courses">
                    <p><?php esc_html_e( 'No courses found.', 'swiftlms' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php get_footer(); ?>
