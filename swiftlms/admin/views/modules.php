<?php
/**
 * Modules view.
 *
 * @package SwiftLMS\Admin\Views
 * @var array $modules           Registered modules.
 * @var array $available_modules Available modules.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap swiftlms-admin-page swiftlms-modules">
    <h1><?php esc_html_e( 'SwiftLMS Modules', 'swiftlms' ); ?></h1>

    <p class="description">
        <?php esc_html_e( 'Extend SwiftLMS functionality with optional modules. Each module adds specific features to your learning platform.', 'swiftlms' ); ?>
    </p>

    <!-- Active Modules -->
    <div class="swiftlms-modules-section">
        <h2><?php esc_html_e( 'Active Modules', 'swiftlms' ); ?></h2>

        <?php if ( empty( $modules ) ) : ?>
            <p class="swiftlms-no-modules"><?php esc_html_e( 'No modules are currently active.', 'swiftlms' ); ?></p>
        <?php else : ?>
            <div class="swiftlms-modules-grid">
                <?php foreach ( $modules as $slug => $module ) : ?>
                    <div class="swiftlms-module-card swiftlms-module-active">
                        <div class="swiftlms-module-header">
                            <h3><?php echo esc_html( $module->get_name() ); ?></h3>
                            <span class="swiftlms-module-version">v<?php echo esc_html( $module->get_version() ); ?></span>
                        </div>
                        <p class="swiftlms-module-description">
                            <?php echo esc_html( $module->get_description() ); ?>
                        </p>
                        <div class="swiftlms-module-footer">
                            <span class="swiftlms-module-status swiftlms-status-active">
                                <?php esc_html_e( 'Active', 'swiftlms' ); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Available Modules -->
    <div class="swiftlms-modules-section">
        <h2><?php esc_html_e( 'Available Modules', 'swiftlms' ); ?></h2>

        <div class="swiftlms-modules-grid">
            <?php foreach ( $available_modules as $slug => $module_info ) : ?>
                <div class="swiftlms-module-card <?php echo $module_info['installed'] ? 'swiftlms-module-installed' : ''; ?>">
                    <div class="swiftlms-module-header">
                        <h3><?php echo esc_html( $module_info['name'] ); ?></h3>
                    </div>
                    <p class="swiftlms-module-description">
                        <?php echo esc_html( $module_info['description'] ); ?>
                    </p>
                    <div class="swiftlms-module-footer">
                        <?php if ( $module_info['installed'] ) : ?>
                            <span class="swiftlms-module-status swiftlms-status-installed">
                                <?php esc_html_e( 'Installed', 'swiftlms' ); ?>
                            </span>
                        <?php else : ?>
                            <span class="swiftlms-module-status swiftlms-status-available">
                                <?php esc_html_e( 'Not Installed', 'swiftlms' ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Module Development Info -->
    <div class="swiftlms-modules-section swiftlms-dev-info">
        <h2><?php esc_html_e( 'Develop Custom Modules', 'swiftlms' ); ?></h2>
        <p>
            <?php esc_html_e( 'Create your own SwiftLMS modules by extending the AbstractModule class. Modules can add new post types, database tables, REST API endpoints, and more.', 'swiftlms' ); ?>
        </p>
        <pre><code>&lt;?php
use SwiftLMS\Abstracts\AbstractModule;

class MyCustomModule extends AbstractModule {
    protected string $slug = 'my-module';
    protected string $name = 'My Custom Module';
    protected string $version = '1.0.0';

    protected function load(): void {
        // Initialize your module
    }
}</code></pre>
    </div>
</div>
