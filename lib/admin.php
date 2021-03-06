<?php
/**
 * Administrative UI views and callbacks
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Admin
 */
class Writing_On_GitHub_Admin {

    /**
     * plugin file name rel plugin dir.
     * @var string
     */
    protected $plugin_file;

    /**
     * Hook into GitHub API
     */
    public function __construct( $plugin_file ) {
        $this->plugin_file = $plugin_file;

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'current_screen', array( $this, 'trigger_cron' ) );
        add_filter( 'plugin_action_links', array($this, 'settings_links'), 10, 2 );
    }

    /**
     * settings link
     * @param  string[] $links
     * @param  string $file
     * @return string[]
     */
    public function settings_links( $links, $file ) {
        if ( $file != $this->plugin_file ) {
            return $links;
        }

        $settings_link = '<a href="options-general.php?page=' .
        Writing_On_GitHub::$text_domain . '">' . __( 'Settings', 'writing-on-github' ) . '</a>';

        array_push( $links, $settings_link );

        return $links;
    }

    /**
     * Callback to render the settings page view
     */
    public function settings_page() {
        include dirname( dirname( __FILE__ ) ) . '/views/options.php';
    }

    /**
     * Callback to register the plugin's options
     */
    public function register_settings() {
        add_settings_section(
            'general',
            'General Settings',
            array( $this, 'section_callback' ),
            Writing_On_GitHub::$text_domain
        );

        register_setting( Writing_On_GitHub::$text_domain, 'wogh_host' );
        add_settings_field( 'wogh_host', __( 'GitHub hostname', 'writing-on-github' ), array( $this, 'field_callback' ), Writing_On_GitHub::$text_domain, 'general', array(
                'default'   => 'https://api.github.com',
                'name'      => 'wogh_host',
                'help_text' => __( 'The GitHub host to use. This only needs to be changed to support a GitHub Enterprise installation.', 'writing-on-github' ),
            )
        );

        register_setting( Writing_On_GitHub::$text_domain, 'wogh_repository' );
        add_settings_field( 'wogh_repository', __( 'Repository', 'writing-on-github' ), array( $this, 'field_callback' ), Writing_On_GitHub::$text_domain, 'general', array(
                'default'   => '',
                'name'      => 'wogh_repository',
                'help_text' => __( 'The GitHub repository to commit to, with owner (<code>[OWNER]/[REPOSITORY]</code>), e.g., <code>github/hubot.github.com</code>. The repository should contain an initial commit, which is satisfied by including a README when you create the repository on GitHub.', 'writing-on-github' ),
            )
        );

        register_setting( Writing_On_GitHub::$text_domain, 'wogh_branch' );
        add_settings_field( 'wogh_branch', __( 'Branch', 'writing-on-github' ), array( $this, 'field_callback' ), Writing_On_GitHub::$text_domain, 'general', array(
                'default'   => 'master',
                'name'      => 'wogh_branch',
                'help_text' => __( 'The GitHub branch to commit to, default is master.', 'writing-on-github' ),
            )
        );

        register_setting( Writing_On_GitHub::$text_domain, 'wogh_oauth_token' );
        add_settings_field( 'wogh_oauth_token', __( 'Oauth Token', 'writing-on-github' ), array( $this, 'field_callback' ), Writing_On_GitHub::$text_domain, 'general', array(
                'default'   => '',
                'name'      => 'wogh_oauth_token',
                'help_text' => __( "A <a href='https://github.com/settings/tokens/new'>personal oauth token</a> with <code>public_repo</code> scope.", 'writing-on-github' ),
            )
        );

        register_setting( Writing_On_GitHub::$text_domain, 'wogh_secret' );
        add_settings_field( 'wogh_secret', __( 'Webhook Secret', 'writing-on-github' ), array( $this, 'field_callback' ), Writing_On_GitHub::$text_domain, 'general', array(
                'default'   => '',
                'name'      => 'wogh_secret',
                'help_text' => __( "The webhook's secret phrase. This should be password strength, as it is used to verify the webhook's payload.", 'writing-on-github' ),
            )
        );

        register_setting( Writing_On_GitHub::$text_domain, 'wogh_default_user' );
        add_settings_field( 'wogh_default_user', __( 'Default Import User', 'writing-on-github' ), array( &$this, 'user_field_callback' ), Writing_On_GitHub::$text_domain, 'general', array(
                'default'   => '',
                'name'      => 'wogh_default_user',
                'help_text' => __( 'The fallback user for import, in case Writing On GitHub cannot find the committer in the database.', 'writing-on-github' ),
            )
        );

        register_setting( Writing_On_GitHub::$text_domain, 'wogh_ignore_author' );
        add_settings_field( 'wogh_ignore_author', __( 'Ignore author', 'writing-on-github' ), array( &$this, 'checkbox_field_callback' ), Writing_On_GitHub::$text_domain, 'general', array(
                'default'   => '',
                'name'      => 'wogh_ignore_author',
                'help_text' => __( 'Do not export author and do not use author info from GitHub.', 'writing-on-github' ),
            )
        );

        register_setting( Writing_On_GitHub::$text_domain, 'wogh_dont_export_content' );
        add_settings_field( 'wogh_dont_export_content', __( 'Don\'t export content', 'writing-on-github' ), array( &$this, 'checkbox_field_callback' ), Writing_On_GitHub::$text_domain, 'general', array(
                'default'   => '',
                'name'      => 'wogh_dont_export_content',
                'help_text' => __( 'Do not export post content to github, only export meta.', 'writing-on-github' ),
            )
        );

        // register_setting( Writing_On_GitHub::$text_domain, 'wogh_ignore_metas' );
        // add_settings_field( 'wogh_ignore_metas', __( 'Ignore post metas', 'writing-on-github' ), array( &$this, 'textarea_field_callback' ), Writing_On_GitHub::$text_domain, 'general', array(
        //      'default'   => '',
        //      'name'      => 'wogh_ignore_metas',
        //      'help_text' => __( 'These meta keys will be ignored and cannot be imported and exported. One meta key per line.', 'writing-on-github' ),
        //  )
        // );
    }

    /**
     * Callback to render an individual options field
     *
     * @param array $args Field arguments.
     */
    public function field_callback( $args ) {
        include dirname( dirname( __FILE__ ) ) . '/views/setting-field.php';
    }

    /**
     * Callback to render the default import user field.
     *
     * @param array $args Field arguments.
     */
    public function user_field_callback( $args ) {
        include dirname( dirname( __FILE__ ) ) . '/views/user-setting-field.php';
    }

    /**
     * Callback to render the textarea field.
     *
     * @param array $args Field arguments.
     */
    public function textarea_field_callback( $args ) {
        include dirname( dirname( __FILE__ ) ) . '/views/textarea-setting-field.php';
    }

    /**
     * Callback to render the checkbox field.
     *
     * @param array $args Field arguments.
     */
    public function checkbox_field_callback( $args ) {
        include dirname( dirname( __FILE__ ) ) . '/views/checkbox-setting-field.php';
    }

    /**
     * Displays settings messages from background processes
     */
    public function section_callback() {
        if ( get_current_screen()->id !== 'settings_page_' . Writing_On_GitHub::$text_domain ) {
            return;
        }

        if ( 'yes' === get_option( '_wogh_export_started' ) ) { ?>
            <div class="updated">
                <p><?php esc_html_e( 'Export to GitHub started.', 'writing-on-github' ); ?></p>
            </div><?php
            delete_option( '_wogh_export_started' );
        }

        if ( $message = get_option( '_wogh_export_error' ) ) { ?>
            <div class="error">
                <p><?php esc_html_e( 'Export to GitHub failed with error:', 'writing-on-github' ); ?> <?php echo esc_html( $message );?></p>
            </div><?php
            delete_option( '_wogh_export_error' );
        }

        if ( 'yes' === get_option( '_wogh_export_complete' ) ) { ?>
            <div class="updated">
                <p><?php esc_html_e( 'Export to GitHub completed successfully.', 'writing-on-github' );?></p>
            </div><?php
            delete_option( '_wogh_export_complete' );
        }

        if ( 'yes' === get_option( '_wogh_import_started' ) ) { ?>
            <div class="updated">
            <p><?php esc_html_e( 'Import from GitHub started.', 'writing-on-github' ); ?></p>
            </div><?php
            delete_option( '_wogh_import_started' );
        }

        if ( $message = get_option( '_wogh_import_error' ) ) { ?>
            <div class="error">
            <p><?php esc_html_e( 'Import from GitHub failed with error:', 'writing-on-github' ); ?> <?php echo esc_html( $message );?></p>
            </div><?php
            delete_option( '_wogh_import_error' );
        }

        if ( 'yes' === get_option( '_wogh_import_complete' ) ) { ?>
            <div class="updated">
            <p><?php esc_html_e( 'Import from GitHub completed successfully.', 'writing-on-github' );?></p>
            </div><?php
            delete_option( '_wogh_import_complete' );
        }
    }

    /**
     * Add options menu to admin navbar
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Writing On GitHub', 'writing-on-github' ),
            __( 'Writing On GitHub', 'writing-on-github' ),
            'manage_options',
            Writing_On_GitHub::$text_domain,
            array( $this, 'settings_page' )
        );
    }

    /**
     * Admin callback to trigger import/export because WordPress admin routing lol
     */
    public function trigger_cron() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( get_current_screen()->id !== 'settings_page_' . Writing_On_GitHub::$text_domain ) {
            return;
        }

        if ( ! isset( $_GET['action'] ) ) {
            return;
        }

        if ( 'export' === $_GET['action'] ) {
            Writing_On_GitHub::$instance->start_export();
        }
        if ( 'force_export' === $_GET['action'] ) {
            Writing_On_GitHub::$instance->start_export(true);
        }
        if ( 'import' === $_GET['action'] ) {
            Writing_On_GitHub::$instance->start_import();
        }
        if ( 'force_import' === $_GET['action'] ) {
            Writing_On_GitHub::$instance->start_import(true);
        }

        wp_redirect( admin_url( 'options-general.php?page=writing-on-github' ) );
        die;
    }
}
