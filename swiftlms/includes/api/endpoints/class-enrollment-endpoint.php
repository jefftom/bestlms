<?php
/**
 * Enrollment REST API endpoint.
 *
 * @package SwiftLMS\Api\Endpoints
 */

namespace SwiftLMS\Api\Endpoints;

use SwiftLMS\Api\RestApi;
use SwiftLMS\Core\Enrollment;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles enrollment API endpoints.
 */
class EnrollmentEndpoint {

    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Enroll in a course.
        register_rest_route(
            RestApi::NAMESPACE,
            '/courses/(?P<course_id>\d+)/enroll',
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'enroll' ),
                    'permission_callback' => array( RestApi::class, 'check_authentication' ),
                    'args'                => array(
                        'course_id' => array(
                            'description'       => __( 'Course ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_course_id' ),
                        ),
                    ),
                ),
            )
        );

        // Unenroll from a course.
        register_rest_route(
            RestApi::NAMESPACE,
            '/courses/(?P<course_id>\d+)/unenroll',
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'unenroll' ),
                    'permission_callback' => array( RestApi::class, 'check_authentication' ),
                    'args'                => array(
                        'course_id' => array(
                            'description'       => __( 'Course ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_course_id' ),
                        ),
                        'reason'    => array(
                            'description'       => __( 'Reason for unenrollment.', 'swiftlms' ),
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        // Get enrollment status.
        register_rest_route(
            RestApi::NAMESPACE,
            '/courses/(?P<course_id>\d+)/enrollment',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_enrollment' ),
                    'permission_callback' => array( RestApi::class, 'check_authentication' ),
                    'args'                => array(
                        'course_id' => array(
                            'description'       => __( 'Course ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_course_id' ),
                        ),
                    ),
                ),
            )
        );

        // Admin: Enroll a user.
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/enrollments',
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'admin_enroll' ),
                    'permission_callback' => array( RestApi::class, 'check_admin_permission' ),
                    'args'                => array(
                        'user_id'    => array(
                            'description'       => __( 'User ID to enroll.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_user_id' ),
                        ),
                        'course_id'  => array(
                            'description'       => __( 'Course ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_course_id' ),
                        ),
                        'expires_at' => array(
                            'description'       => __( 'Expiration date (Y-m-d H:i:s).', 'swiftlms' ),
                            'type'              => 'string',
                            'format'            => 'date-time',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        // Admin: Get course enrollments.
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/courses/(?P<course_id>\d+)/enrollments',
            array(
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_course_enrollments' ),
                    'permission_callback' => array( RestApi::class, 'check_admin_permission' ),
                    'args'                => array(
                        'course_id' => array(
                            'description'       => __( 'Course ID.', 'swiftlms' ),
                            'type'              => 'integer',
                            'required'          => true,
                            'validate_callback' => array( RestApi::class, 'validate_course_id' ),
                        ),
                        'status'    => array(
                            'description' => __( 'Filter by status.', 'swiftlms' ),
                            'type'        => 'string',
                            'enum'        => array( 'active', 'completed', 'expired', 'cancelled' ),
                        ),
                        'page'      => array(
                            'description' => __( 'Page number.', 'swiftlms' ),
                            'type'        => 'integer',
                            'default'     => 1,
                        ),
                        'per_page'  => array(
                            'description' => __( 'Items per page.', 'swiftlms' ),
                            'type'        => 'integer',
                            'default'     => 20,
                        ),
                    ),
                ),
            )
        );

        // Admin: Delete enrollment.
        register_rest_route(
            RestApi::NAMESPACE,
            '/admin/enrollments/(?P<user_id>\d+)/(?P<course_id>\d+)',
            array(
                array(
                    'methods'             => \WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_enrollment' ),
                    'permission_callback' => array( RestApi::class, 'check_admin_permission' ),
                    'args'                => array(
                        'user_id'   => array(
                            'description' => __( 'User ID.', 'swiftlms' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                        'course_id' => array(
                            'description' => __( 'Course ID.', 'swiftlms' ),
                            'type'        => 'integer',
                            'required'    => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Enroll current user in a course.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function enroll( \WP_REST_Request $request ) {
        $user_id   = get_current_user_id();
        $course_id = $request->get_param( 'course_id' );

        // Check if already enrolled.
        if ( swiftlms()->enrollment()->is_enrolled( $user_id, $course_id, false ) ) {
            return new \WP_Error(
                'already_enrolled',
                __( 'You are already enrolled in this course.', 'swiftlms' ),
                array( 'status' => 400 )
            );
        }

        // Check access type.
        $access_type = get_post_meta( $course_id, '_swiftlms_access_type', true );

        if ( $access_type === 'paid' ) {
            return new \WP_Error(
                'payment_required',
                __( 'This course requires payment.', 'swiftlms' ),
                array( 'status' => 402 )
            );
        }

        if ( $access_type === 'closed' ) {
            return new \WP_Error(
                'closed_enrollment',
                __( 'Enrollment for this course is closed.', 'swiftlms' ),
                array( 'status' => 403 )
            );
        }

        // Check if course is full.
        if ( swiftlms()->enrollment()->is_course_full( $course_id ) ) {
            return new \WP_Error(
                'course_full',
                __( 'This course has reached its maximum capacity.', 'swiftlms' ),
                array( 'status' => 400 )
            );
        }

        // Enroll the user.
        $enrollment_id = swiftlms()->enrollment()->enroll(
            $user_id,
            $course_id,
            array(
                'enrollment_source' => Enrollment::SOURCE_SELF,
            )
        );

        if ( ! $enrollment_id ) {
            return new \WP_Error(
                'enrollment_failed',
                __( 'Failed to enroll in course.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        $enrollment = swiftlms()->enrollment()->get_enrollment( $user_id, $course_id );

        return new \WP_REST_Response(
            array(
                'success'    => true,
                'message'    => __( 'Successfully enrolled in course.', 'swiftlms' ),
                'enrollment' => array(
                    'id'          => $enrollment_id,
                    'status'      => $enrollment->status,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'expires_at'  => $enrollment->expires_at,
                ),
            ),
            201
        );
    }

    /**
     * Unenroll current user from a course.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function unenroll( \WP_REST_Request $request ) {
        $user_id   = get_current_user_id();
        $course_id = $request->get_param( 'course_id' );
        $reason    = $request->get_param( 'reason' ) ?? '';

        if ( ! swiftlms()->enrollment()->is_enrolled( $user_id, $course_id, false ) ) {
            return new \WP_Error(
                'not_enrolled',
                __( 'You are not enrolled in this course.', 'swiftlms' ),
                array( 'status' => 400 )
            );
        }

        $result = swiftlms()->enrollment()->unenroll( $user_id, $course_id, $reason );

        if ( ! $result ) {
            return new \WP_Error(
                'unenroll_failed',
                __( 'Failed to unenroll from course.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        return new \WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'Successfully unenrolled from course.', 'swiftlms' ),
            )
        );
    }

    /**
     * Get enrollment status for current user.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function get_enrollment( \WP_REST_Request $request ): \WP_REST_Response {
        $user_id   = get_current_user_id();
        $course_id = $request->get_param( 'course_id' );

        $enrollment = swiftlms()->enrollment()->get_enrollment( $user_id, $course_id );

        if ( ! $enrollment ) {
            return new \WP_REST_Response(
                array(
                    'is_enrolled' => false,
                    'enrollment'  => null,
                )
            );
        }

        return new \WP_REST_Response(
            array(
                'is_enrolled' => swiftlms()->enrollment()->is_enrolled( $user_id, $course_id ),
                'enrollment'  => array(
                    'id'               => (int) $enrollment->id,
                    'status'           => $enrollment->status,
                    'enrolled_at'      => $enrollment->enrolled_at,
                    'expires_at'       => $enrollment->expires_at,
                    'completed_at'     => $enrollment->completed_at,
                    'progress_percent' => (float) $enrollment->progress_percent,
                    'last_activity_at' => $enrollment->last_activity_at,
                ),
            )
        );
    }

    /**
     * Admin: Enroll a user in a course.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function admin_enroll( \WP_REST_Request $request ) {
        $user_id    = $request->get_param( 'user_id' );
        $course_id  = $request->get_param( 'course_id' );
        $expires_at = $request->get_param( 'expires_at' );

        $enrollment_id = swiftlms()->enrollment()->enroll(
            $user_id,
            $course_id,
            array(
                'enrollment_source' => Enrollment::SOURCE_ADMIN,
                'expires_at'        => $expires_at,
            )
        );

        if ( ! $enrollment_id ) {
            // Check why it failed.
            if ( swiftlms()->enrollment()->is_enrolled( $user_id, $course_id, false ) ) {
                return new \WP_Error(
                    'already_enrolled',
                    __( 'User is already enrolled in this course.', 'swiftlms' ),
                    array( 'status' => 400 )
                );
            }

            return new \WP_Error(
                'enrollment_failed',
                __( 'Failed to enroll user.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        $enrollment = swiftlms()->enrollment()->get_enrollment( $user_id, $course_id );
        $user       = get_userdata( $user_id );

        return new \WP_REST_Response(
            array(
                'success'    => true,
                'enrollment' => array(
                    'id'          => $enrollment_id,
                    'user_id'     => $user_id,
                    'user_email'  => $user->user_email,
                    'user_name'   => $user->display_name,
                    'course_id'   => $course_id,
                    'status'      => $enrollment->status,
                    'enrolled_at' => $enrollment->enrolled_at,
                    'expires_at'  => $enrollment->expires_at,
                ),
            ),
            201
        );
    }

    /**
     * Get all enrollments for a course.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function get_course_enrollments( \WP_REST_Request $request ): \WP_REST_Response {
        $course_id = $request->get_param( 'course_id' );
        $status    = $request->get_param( 'status' );
        $page      = $request->get_param( 'page' );
        $per_page  = $request->get_param( 'per_page' );
        $offset    = ( $page - 1 ) * $per_page;

        $enrollments = swiftlms()->enrollment()->get_course_enrollments(
            $course_id,
            $status,
            $per_page,
            $offset
        );

        $total = swiftlms()->enrollment()->get_enrollment_count( $course_id, $status );

        $data = array();

        foreach ( $enrollments as $enrollment ) {
            $user = get_userdata( $enrollment->user_id );

            $data[] = array(
                'id'               => (int) $enrollment->id,
                'user_id'          => (int) $enrollment->user_id,
                'user_email'       => $user ? $user->user_email : '',
                'user_name'        => $user ? $user->display_name : '',
                'status'           => $enrollment->status,
                'enrolled_at'      => $enrollment->enrolled_at,
                'expires_at'       => $enrollment->expires_at,
                'completed_at'     => $enrollment->completed_at,
                'progress_percent' => (float) $enrollment->progress_percent,
                'last_activity_at' => $enrollment->last_activity_at,
            );
        }

        $response = new \WP_REST_Response( $data );
        $response->header( 'X-WP-Total', $total );
        $response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

        return $response;
    }

    /**
     * Delete an enrollment.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function delete_enrollment( \WP_REST_Request $request ) {
        $user_id   = $request->get_param( 'user_id' );
        $course_id = $request->get_param( 'course_id' );

        $enrollment = swiftlms()->enrollment()->get_enrollment( $user_id, $course_id );

        if ( ! $enrollment ) {
            return new \WP_Error(
                'not_found',
                __( 'Enrollment not found.', 'swiftlms' ),
                array( 'status' => 404 )
            );
        }

        // Delete progress data too.
        swiftlms()->progress()->reset_course_progress( $user_id, $course_id );

        // Delete enrollment.
        $result = \SwiftLMS\Database\EnrollmentTable::delete( $user_id, $course_id );

        if ( ! $result ) {
            return new \WP_Error(
                'delete_failed',
                __( 'Failed to delete enrollment.', 'swiftlms' ),
                array( 'status' => 500 )
            );
        }

        return new \WP_REST_Response(
            array(
                'success' => true,
                'message' => __( 'Enrollment deleted.', 'swiftlms' ),
            )
        );
    }
}
