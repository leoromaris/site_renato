<?php

namespace FormVibes;

use FormVibes\Classes\Export;
use FormVibes\Classes\Utils;
use FormVibes\Integrations\Cf7;
use FormVibes\Integrations\Elementor;
use FormVibes\Integrations\Caldera;
use FormVibes\Classes\ApiEndpoint;
use FormVibes\Classes\Settings;
use FormVibes\Integrations\BeaverBuilder;
use function GuzzleHttp\Promise\all;


class Plugin{

    private static $_instance = null;
	private $panelNumber;
	private $data;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        add_action('admin_enqueue_scripts', [$this,'admin_scripts'], 10, 1);
        
        add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );
        add_action('plugins_loaded', [ 'FormVibes\Classes\DbTables','fv_plugin_activated']);

	    add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);

        if(!function_exists('is_plugin_active')){
            include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

        if(is_plugin_active( 'caldera-forms/caldera-core.php' )){
            $caldera = new Caldera();
        }
        if(is_plugin_active( 'contact-form-7/wp-contact-form-7.php' )){
            $cf7 = new Cf7();
        }
        if(is_plugin_active( 'elementor-pro/elementor-pro.php' )){
            $ef = new Elementor();
            
        }
        if(is_plugin_active( 'bb-plugin/fl-builder.php' )){
            $bb = new BeaverBuilder();
        }
        
        Settings::instance();
        //new \FormVibes\Classes\Submissions('elementor');

        add_action('admin_menu', [$this,'my_menu_pages'] );

        add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

        //add_action('fv_reports', [$this,'do_this_hourly']); // future plan

        add_filter( 'admin_footer_text', [ $this, 'admin_footer_text' ] );


        add_action('init',[$this,'fv_export_csv']);
    }

    function fv_export_csv(){
        if(isset($_POST['btnExport'])){
            $params = (array) json_decode(stripslashes($_REQUEST['params']));
            new Export($params);
        }
    }

    function init_rest_api(){
        
        $controllers = [
			new \FormVibes\Api\AdminRest,
		];

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
    }

    public function search_box( $text, $input_id ){} // Remove search box
    protected function pagination( $which ){}        // Remove pagination
    protected function display_tablenav( $which ){}  // Remove navigation

    public function go_pro_link($links){
        $links['go_pro'] = sprintf( '<a href="%1$s" target="_blank" class="fv-pro-link">%2$s</a>','https://wpvibes.com' , __( 'Go Pro', 'wpv-fv' ) );

        return $links;
    }

    public function admin_footer_text( $footer_text ) {
        $screen = get_current_screen();
        // Todo:: Show on plugin screens 
        if ( false ) {
            $footer_text = sprintf(
            /* translators: 1: Form Vibes, 2: Link to plugin review */
                __( 'Enjoyed %1$s? Please leave us a %2$s rating. We really appreciate your support!', 'wpv-fv' ),
                '<strong>' . __( 'Form Vibes', 'wpv-fv' ) . '</strong>',
                '<a href="https://wordpress.org/support/plugin/form-vibes/reviews/#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
            );
        }

        return $footer_text;
    }

    function wpv_fv() {
        global $wpv_fv;
        if ( ! isset( $wpv_fv ) ) {
            // Include Freemius SDK.
            require_once WPV_FV_PATH . '/freemius/start.php';
            $wpv_fv = fs_dynamic_init( array(
                'id'                  => '4666',
                'slug'                => 'form-vibes',
                'type'                => 'plugin',
                'public_key'          => 'pk_321780b7f1d1ee45009cf6da38431',
                'is_premium'          => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'menu'                => array(
                    'slug'           => 'fv-leads',
                    'first-path'     => 'admin.php?page=fv-db-settings',
                    'account'        => false,
                    'contact'        => false,
                    'support'        => false,
                ),
            ) );
        }
        return $wpv_fv;
    }

    function do_this_hourly() {
        $this->write_log("=============Cron Job Executed Time ===================". current_time('Y-m-d H:i:s',0));

    }

    function write_log ( $log )  {
        if ( is_array( $log ) || is_object( $log ) ) {
            error_log( print_r( $log, true ) );
        } else {
            error_log( $log );
        }
    }
    function fv_plugin_activation( $plugin, $network_activation ) {
        $url = admin_url().'admin.php?page=fv-db-settings';
        if($plugin == 'form-vibes/form-vibes.php'){
            header('Location: ' . $url);
            die();
        }
    }

    private function register_autoloader() {

        spl_autoload_register( [ __CLASS__, 'autoload' ] );

    }

    public function autoload( $class ) {

        if ( 0 !== strpos( $class, __NAMESPACE__ ) ) {
            return;
        }

        if ( ! class_exists( $class ) ) {

            $filename = strtolower(
                preg_replace(
                    [ '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
                    [ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
                    $class
                )
            );

            $filename = WPV_FV_PATH .'/inc/'. $filename . '.php';
            if ( is_readable( $filename ) ) {
                include( $filename );
            }
        }
    }

    function admin_scripts() {
	    $screen = get_current_screen();

	    // Todo:: Load on plugin screens only
        if ( 1 ) {

            //wp_enqueue_style('fv-select-css', WPV_FV_URL. 'assets/css/select2.min.css',[],WPV_FV_VERSION);
            wp_enqueue_style('fv-style-css', WPV_FV_URL. 'assets/css/style.css',[], WPV_FV_VERSION);
            wp_enqueue_script( 'fv-js', WPV_FV_URL . 'assets/script/index.js', [ 'jquery-ui-datepicker' ], WPV_FV_VERSION, true );

            $user          = wp_get_current_user();
            $user_role     = $user->roles;
            $user_role     = $user_role[0];
            $gdpr_settings = get_option( 'fv_gdpr_settings' );

            if ( isset( $_REQUEST['post'] ) ) {
                $postID   = $_REQUEST['post'];
                $postType = get_post_type( $postID );
                $postMeta = '';
                if ( $postType === 'fv_utility' ) {
                    $postMeta      = get_post_meta( $postID, 'fv_sc_data', true );
                    $postMetaStyle = get_post_meta( $postID, 'fv_sc_style_data', true );
                    $postKey       = get_post_meta( $postID, 'fv_data_key', true );
                    $d_type        = get_post_meta( $postID, 'fv_data_type', true );
                }
            } else {
                $postID        = '';
                $postMeta      = '';
                $d_type        = '';
                $postKey       = '';
                $postMetaStyle = '';
            }

	        $gdpr_settings = Utils::get_gdpr_settings();

            wp_localize_script( 'fv-js', 'fvGlobalVar', array(
                'site_url'        => site_url(),
                'ajax_url'        => admin_url( 'admin-ajax.php' ),
                'admin_url'       => admin_url(),
                'rest_url'        => get_rest_url(),
                'fv_version'      => WPV_FV_VERSION,
                'user'            => $user_role,
                'nonce'           => wp_create_nonce( 'wp_rest' ),
                'ajax_nonce'      => wp_create_nonce( 'fv_ajax_nonce' ),
                'forms'           => $this->prepareFormsData(),
                'fv_options'      => $this->get_fv_options(),
                'gdpr_settings'   => $gdpr_settings,
                'db_settings'     => get_option( 'fv-db-settings' ),
                'fv_dashboard_widget_settings' => get_option('fv_dashboard_widget_settings'),
                'saved_columns' => get_option( 'fv-keys')
            ) );

            add_action( 'admin_print_scripts', [ $this, 'fv_disable_admin_notices' ] );

            wp_enqueue_style( 'wp-components' );
        }

	    if ( $screen->id === 'dashboard') {
		    wp_enqueue_script( 'dashboard-js', WPV_FV_URL . 'assets/js/dashboard.js', [ 'wp-components' ], WPV_FV_VERSION, true );
	    }
        if ( $screen->id === 'form-vibes_page_fv-render-controls') {
            wp_enqueue_script( 'renderControls-js', WPV_FV_URL . 'assets/js/renderControls.js', [ 'wp-components' ], WPV_FV_VERSION, true );
            wp_enqueue_style( 'fv-renderControls-css', WPV_FV_URL . 'assets/js/renderControls.css', '', WPV_FV_VERSION );
            wp_enqueue_style('fv-select-css', WPV_FV_URL. 'assets/css/select2.min.css',[],WPV_FV_VERSION);
        }
        if ( $screen->id === 'fv_utility' || $screen->id === 'edit-fv_utility' || $screen->id == 'toplevel_page_fv-leads' || $screen->id == 'form-vibes_page_db-manager' || $screen->id == 'fv_utility' ) {
            wp_enqueue_script( 'submissions-js', WPV_FV_URL . 'assets/js/submissions.js', [ 'wp-components' ], WPV_FV_VERSION, true );
            wp_enqueue_style( 'fv-submission-css', WPV_FV_URL . 'assets/js/submissions.css', '', WPV_FV_VERSION );
        }
        if ( $screen->id === 'form-vibes_page_fv-db-settings') {
            wp_enqueue_script( 'setting-js', WPV_FV_URL . 'assets/js/settings.js', [ 'wp-components' ], WPV_FV_VERSION, true );
            wp_enqueue_style( 'setting-css', WPV_FV_URL . 'assets/js/settings.css', '', WPV_FV_VERSION );
        }
        if ( $screen->id === 'form-vibes_page_fv-analytics' ) {
            wp_enqueue_script( 'analytics-js', WPV_FV_URL . 'assets/js/analytics.js', [ 'wp-components' ], WPV_FV_VERSION, true );
            wp_enqueue_style( 'analytics-css', WPV_FV_URL . 'assets/js/analytics.css', '', WPV_FV_VERSION );
        }
        if ( $screen->id === 'form-vibes_page_fv-logs' ) {
            wp_enqueue_script( 'analytics-js', WPV_FV_URL . 'assets/js/eventLogs.js', [ 'wp-components' ], WPV_FV_VERSION, true );
            wp_enqueue_style( 'analytics-css', WPV_FV_URL . 'assets/js/eventLogs.css', '', WPV_FV_VERSION );
        }
        if ( $screen->id === 'dashboard' ) {
            wp_enqueue_script( 'dashboard-js', WPV_FV_URL . 'assets/js/dashboard.js', [ 'wp-components' ], WPV_FV_VERSION, true );
            wp_enqueue_script( 'script-js', WPV_FV_URL . 'assets/script/index.js', '',  WPV_FV_VERSION, true );
            wp_enqueue_style( 'dashboard-css', WPV_FV_URL . 'assets/js/dashboard.css', '', WPV_FV_VERSION );
        }
    }

    function add_dashboard_widgets() {
        $is_dashboard_active = get_option('fv-db-settings');

        if ( $is_dashboard_active == false || $is_dashboard_active == '' ) {
            $is_dashboard_active = [];
        }

        if ( !array_key_exists('widget', $is_dashboard_active) ) {
            $is_dashboard_active['widget'] = true;
        };

        if ( $is_dashboard_active['widget'] != 0 || $is_dashboard_active == '' ) {
            add_meta_box('form_vibes_widget-0','Form Vibes Analytics',[$this, 'dashboard_widget'],null,'normal','high',0);
        }
    }

    function dashboard_widget($vars, $i) {
        echo '<div name="dashboard-widget" id="fv-dashboard-widgets-'.$i['args'].'">
				</div>';
    }

    function prepareFormsData () {
        // TODO :: Refactor and Migrate Logic
        global $wpdb;
        $forms = [];
        $data['forms_plugin'] = apply_filters('fv_forms', $forms);

        // TODO: ask skip Plugin ?.
        //$skipPlugin = $request->get_param('skipPlugin');

        /*if($skipPlugin === true){
            unset($data['forms_plugin']['caldera']);
            unset($data['forms_plugin']['ninja']);
        }*/

        $gdpr_settings = Utils::get_gdpr_settings();

        $debugMode = $gdpr_settings['debug_mode'];
        

        $form_query = "select distinct form_id,form_plugin from {$wpdb->prefix}fv_enteries e";
        $form_res = $wpdb->get_results($form_query,OBJECT_K);

        $inserted_forms = get_option('fv_forms');


	    $pluginForms = [];

        foreach ( $data['forms_plugin'] as $key => $value ) {
            $res = [];

            if($key === 'caldera' || $key === 'ninja'){
                $class = '\FormVibes\Integrations\\'.ucfirst($key);

                $res = $class::get_forms($key);
            }
            else{
                foreach ( $form_res as $form_key => $form_value ) {

	                if(array_key_exists($key, $inserted_forms) && array_key_exists($form_key,$inserted_forms[$key])){
	                    $name = $inserted_forms[$key][$form_key]['name'];
                    }
	                else{
		                $name = $form_key;
                    }
                    if($form_res[$form_key]->form_plugin === $key){
                        $res[$form_key] = [
                            'id' => $form_key,
                            'name' => $name
                        ];
                    }
                }
            }

            if($res !== null ){
                $pluginForms[$key] = $res;
            }
        }

        $all_forms = [];
        foreach ($data['forms_plugin'] as $key => $value){
            if($pluginForms[$key]){
                $all_forms[$value] =  $pluginForms[$key];
            }
        }

        $all_forms = [];

        foreach ( $data['forms_plugin'] as $key => $value ) {
                array_push($all_forms, array(
                'label' => $value,
                'options' => [],
            ));
        }

        for ($i = 0; $i < count($all_forms); ++$i ) {
            foreach ( $data['forms_plugin'] as $key => $value ) {
                foreach ( $pluginForms[$key] as $key1 => $value1 ) {
                    $options = [];
                    if($debugMode === true || $debugMode === 'yes') {
                        array_push($options, array(
                            'label' => $value1['name'] . '('.$value1['id'].')',
                            'value' => $value1['id'],
                            'pluginName' => $value,
                            'formName' => $value1['name']
                        ));
                    } else {
                        array_push($options, array(
                            'label' => $value1['name'],
                            'value' => $value1['id'],
                            'pluginName' => $value,
                            'formName' => $value1['name']
                        ));
                    }

                    if ( $all_forms[$i]['label'] === $value ) {
                        array_push($all_forms[$i]['options'], $options[0]);
                    }
                }
            }
        }

        for ( $i = 0; $i < count($all_forms); ++$i ) {
            if ( count($all_forms[$i]['options']) == 0 ) {
                //echo $i;
                unset($all_forms[$i]);
            }
        }

        $all_forms = array_values($all_forms);

        $data['allForms'] = $all_forms;
//        echo "<pre>";
//        print_r($all_forms);
//        die();

        return $data;


        $allforms = [];

        if($debugMode === true || $debugMode === 'yes'){
	        foreach ($pluginForms as $key => $value){
		        $child = [];
		        foreach ($pluginForms[$key] as $childKey => $childValue){
			        $child[]= [
				        'id' => $childKey,
				        'text' => $childValue['name'].' ('.$childKey.')'
			        ];
		        }
		        if(count($child) > 0) {
			        $allforms[] = [
				        'text'     => $data['forms_plugin'][ $key ],
				        'children' => $child
			        ];
		        }
	        }
        }
        else{
	        foreach ($pluginForms as $key => $value){
		        $child = [];
		        foreach ($pluginForms[$key] as $childKey => $childValue){
			        $child[]= [
				        'id' => $childKey,
				        'text' => $childValue['name']
			        ];
		        }
		        if(count($child) > 0){
			        $allforms[] = [
				        'text' => $data['forms_plugin'][$key],
				        'children' => $child
			        ];
                }
	        }
        }

        $data['allForms'] = $allforms;

        return $data;
    }

    function get_fv_options () {
        // TODO:: all options here expect setting popup options.
    }

    public function plugin_row_meta( $plugin_meta, $plugin_file ) {
        if ( WPV_FV__PLUGIN_BASE === $plugin_file ) {
            $row_meta = [
                'docs' => '<a href="https://wpvibes.link/go/fv-all-docs-pp/" aria-label="' . esc_attr( __( 'View Documentation', 'wpv-fv' ) ) . '" target="_blank">' . __( 'Read Docs', 'wpv-fv' ) . '</a>',
                'support' => '<a href="https://wpvibes.link/go/fv-support-wp/" aria-label="' . esc_attr( __( 'Support', 'wpv-fv' ) ) . '" target="_blank">' . __( 'Need Support', 'wpv-fv' ) . '</a>',
            ];

            $plugin_meta = array_merge( $plugin_meta, $row_meta );
        }

        return $plugin_meta;
    }

    function my_menu_pages(){

        add_menu_page('Form Vibes Leads', 'Form Vibes', 'publish_posts', 'fv-leads', [$this,'display_react_table'], 'dashicons-analytics', 30 );
        add_submenu_page('fv-leads','Form Vibes Submissions', 'Submissions', 'publish_posts', 'fv-leads', [$this,'display_react_table'],1 );
        add_submenu_page('fv-leads','Form Vibes Analytics', 'Analytics', 'publish_posts', 'fv-analytics', [$this,'fv_analytics'],2 );
        add_submenu_page('fv-leads','Form Vibes Dashboard Settings', 'Settings', 'manage_options', 'fv-db-settings', [$this,'fv_db_settings'],5);
        add_submenu_page('fv-leads','Form Vibes Logs', 'Event Logs', 'publish_posts', 'fv-logs', [$this,'fv_logs'],6);
    }

    function fv_disable_admin_notices() {
        global $wp_filter;
        $screen = get_current_screen();
        $fv_screens = [
            'toplevel_page_fv-leads',
            'form-vibes_page_fv-analytics',
            'form-vibes_page_fv-db-settings',
            'form-vibes_page_fv-logs',
        ];

        if( in_array($screen->id, $fv_screens) ) {
            if ( is_user_admin() ) {
                if ( isset( $wp_filter['user_admin_notices'] ) ) {
                    unset( $wp_filter['user_admin_notices'] );
                }
            } elseif ( isset( $wp_filter['admin_notices'] ) ) {
                unset( $wp_filter['admin_notices'] );
            }
            if ( isset( $wp_filter['all_admin_notices'] ) ) {
                unset( $wp_filter['all_admin_notices'] );
            }
        }

        // Form Vibes Pro Notice
        /*if(WPV_FV_PLAN === 'FREE'){
            add_action( 'admin_notices', [ $this,'fv_pro_notice'] );
        }*/

        if ( isset( $_GET['remind_later'] ) ) {
            add_action( 'admin_notices', [ $this,'fv_remind_later'] );
        }
        else if ( isset( $_GET['review_done'] ) ) {
            add_action( 'admin_notices', [ $this,'fv_review_done'] );
        }
        else{
            add_action( 'admin_notices', [ $this,'fv_review'] );
        }


    }
    function fv_pro_notice(){
        ?>
        <div class="notice notice-success is-dismissible">
            Get More features in Pro Version.
            <a href="#">Go Pro</a>
        </div>
        <?php
    }
    function fv_review(){
        $show_review = get_transient('fv_remind_later');
        $review_added = get_transient('fv_review_done');

        $review_status = get_option( 'fv-review');
        if($review_status !== 'done'){
            if($show_review == '' && $review_added == ''){
                global $wpdb;

                $rowcount = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fv_enteries");

                if($rowcount>9){
                    ?>
                    <div class="notice notice-success is-dismissible">
                        <p><?php _e( 'Thank you for using <b>Form Vibes</b>! <br/> How was your Experience with us ?', 'wpv-fv' ); ?></p>

                        <p>
                            <a class="fv-notice-link" target="_blank" href="https://wordpress.org/support/plugin/form-vibes/reviews/#new-post" class="button button-primary"><span class="dashicons dashicons-heart"></span><?php _e( 'Ok, you deserve it!', 'wpv-fv' ); ?></a>
                            <a class="fv-notice-link" href="<?php echo add_query_arg( 'remind_later', 'later'); ?>"><span class="dashicons dashicons-schedule"></span><?php _e( 'May Be Later', 'wpv-fv' ); ?></a>
                            <a class="fv-notice-link" href="<?php echo add_query_arg( 'review_done', 'done'); ?>"><span class="dashicons dashicons-smiley"></span><?php _e( 'Already Done', 'wpv-fv' ); ?></a>
                        </p>
                    </div>
                    <?php
                }
            }
        }
    }
    function fv_remind_later(){
        set_transient( 'fv_remind_later', 'show again', WEEK_IN_SECONDS );
    }

    function fv_review_done(){
        //set_transient( 'fv_review_done', 'Already Reviewed !', 3 * MONTH_IN_SECONDS );

        update_option( 'fv-review', 'done',false);
    }

    function display_react_table()
    {
        ?>
        <div id="fv-submissions">

        </div>
        <?php
    }

    function fv_logs(){
        ?>
        <div id="fv-logs" class="fv-logs"></div>
        <?php
    }

    function fv_analytics(){
        ?>
        <div id="fv-analytics" class="fv-analytics"></div>
        <?php
    }
    function fv_db_settings(){
        ?>
        <div id="fv-db-settings" class="fv-db-settings"></div>
        <?php
    }
    function fv_render_controls(){
        ?>
        <div id="fv-render-controls" class="fv-render-controls-wrapper"></div>
        <?php
    }
}
Plugin::instance();