<?php

// Extend with a public constructor so that can be mocked in tests
class MockJetpack extends Jetpack {
	public function __construct() {
	}
}

class WP_Test_Jetpack extends WP_UnitTestCase {

	static $activated_modules = array();
	static $deactivated_modules = array();

	/**
	 * @author blobaugh
	 * @covers Jetpack::init
	 * @since 2.3.3
	 */
	public function test_init() {
		$this->assertInstanceOf( 'Jetpack', Jetpack::init() );
	}

	/**
	 * @author enkrates
	 * @covers Jetpack::sort_modules
	 * @since 3.2
	 */
	public function test_sort_modules_with_equal_sort_values() {

		$first_file  = array( 'sort' => 5 );
		$second_file = array( 'sort' => 5 );

		$sort_value = Jetpack::sort_modules( $first_file, $second_file );

		$this->assertEquals( 0, $sort_value );
	}

	/**
	 * @author enkrates
	 * @covers Jetpack::sort_modules
	 * @since 3.2
	 */
	public function test_sort_modules_with_different_sort_values() {

		$first_file  = array( 'sort' => 10 );
		$second_file = array( 'sort' => 5 );

		$sort_value = Jetpack::sort_modules( $first_file, $second_file );
		$reversed_sort_value = Jetpack::sort_modules( $second_file, $first_file );

		$this->assertEquals( 1, $sort_value );
		$this->assertEquals( -1, $reversed_sort_value );
	}

	/**
	 * @author georgestephanis
	 * @covers Jetpack::absolutize_css_urls
	 */
	public function test_absolutize_css_urls_properly_handles_use_cases() {

		$css = <<<CSS
.test-it {
	background: url(same-dir.png);
	background: url('same-dir.png');
	background: url("same-dir.png");
	background: url( same-dir.png );
	background: url( 'same-dir.png' );
	background: url( "same-dir.png" );
	background: url(		same-dir.png		);
	background: url(		'same-dir.png'	);
	background: url(		"same-dir.png"	);
	background: url(./same-dir.png);
	background: url(down/down-dir.png);
	background: url(../up-dir.png);
	background: url(../../up-2-dirs.png);
	background: url(/at-root.png);
	background: url(//other-domain.com/root.png);
	background: url(https://other-domain.com/root.png);
	background: url(data:image/gif;base64,eh129ehiuehjdhsa==);
}
CSS;

		$expected = <<<EXPECTED
.test-it {
	background: url("http://example.com/dir1/dir2/same-dir.png");
	background: url("http://example.com/dir1/dir2/same-dir.png");
	background: url("http://example.com/dir1/dir2/same-dir.png");
	background: url("http://example.com/dir1/dir2/same-dir.png");
	background: url("http://example.com/dir1/dir2/same-dir.png");
	background: url("http://example.com/dir1/dir2/same-dir.png");
	background: url("http://example.com/dir1/dir2/same-dir.png");
	background: url("http://example.com/dir1/dir2/same-dir.png");
	background: url("http://example.com/dir1/dir2/same-dir.png");
	background: url("http://example.com/dir1/dir2/./same-dir.png");
	background: url("http://example.com/dir1/dir2/down/down-dir.png");
	background: url("http://example.com/dir1/dir2/../up-dir.png");
	background: url("http://example.com/dir1/dir2/../../up-2-dirs.png");
	background: url("http://example.com/at-root.png");
	background: url(//other-domain.com/root.png);
	background: url(https://other-domain.com/root.png);
	background: url(data:image/gif;base64,eh129ehiuehjdhsa==);
}
EXPECTED;

		$result = Jetpack::absolutize_css_urls( $css, 'http://example.com/dir1/dir2/style.css' );
		$this->assertEquals( $expected, $result );

	}

	/**
	 * @author tonykova
	 * @covers Jetpack::check_identity_crisis
	 * @since 3.2.0
	 */
	public function test_check_identity_crisis_will_report_crisis_if_an_http_site_and_siteurl_mismatch() {
		// Store master user data
		Jetpack_Options::update_option( 'master_user', 'test' );
		Jetpack_Options::update_option( 'user_tokens', array( 'test' => 'herp.derp.test' ) );
		add_filter( 'jetpack_development_mode', '__return_false', 1, 1 );

		// Mock get_cloud_site_options
		$jp	= $this->getMockBuilder( 'MockJetpack' )
			->setMethods( array( 'get_cloud_site_options' ) )
			->getMock();

		$jp->init();
		Jetpack::$instance = $jp;

		$jp->expects( $this->any() )
			->method( 'get_cloud_site_options' )
			->will( $this->returnValue( array( 'siteurl' => 'https://test.site.com' ) ) );

		// Save the mismatching option for comparison
		// Using @ to prevent throwing an error on a bug in WP core when attempting to change .htaccess
		@update_option( 'siteurl', 'http://test.site.com' );

		// Attach hook for checking the errors

		add_filter( 'jetpack_has_identity_crisis', array( $this, 'pre_test_check_identity_crisis_will_report_crisis_if_an_http_site_and_siteurl_mismatch' ) );

		$this->assertTrue( false !== MockJetpack::check_identity_crisis( true ) );
		remove_filter( 'jetpack_has_identity_crisis', array( $this, 'pre_test_check_identity_crisis_will_report_crisis_if_an_http_site_and_siteurl_mismatch' ) );
	}

	public function pre_test_check_identity_crisis_will_report_crisis_if_an_http_site_and_siteurl_mismatch( $errors ){
		$this->assertCount( 1, $errors );
	}

	/**
	 * @author  kraftbj
	 * @covers Jetpack::is_staging_site
	 * @since  3.9.0
	 */
	public function test_is_staging_site_will_report_staging_for_wpengine_sites_by_url() {
		add_filter( 'site_url', array( $this, 'pre_test_is_staging_site_will_report_staging_for_wpengine_sites_by_url' ) );
		$this->assertTrue( MockJetpack::is_staging_site() );
		remove_filter( 'site_url', array( $this, 'pre_test_is_staging_site_will_report_staging_for_wpengine_sites_by_url' ) );

	}

	public function pre_test_is_staging_site_will_report_staging_for_wpengine_sites_by_url(){
		return 'http://bjk.staging.wpengine.com';
	}
	/*
	 * @author tonykova
	 * @covers Jetpack::implode_frontend_css
	 */
	public function test_implode_frontend_css_enqueues_bundle_file_handle() {
		global $wp_styles;
		$wp_styles = new WP_styles();

		add_filter( 'jetpack_implode_frontend_css', '__return_true' );

		if ( ! file_exists( plugins_url( 'jetpack-carousel.css', __FILE__ ) ) ) {
			$this->markTestSkipped( 'Required CSS file not found.' );
		}

		// Enqueue some script on the $to_dequeue list
		$style_handle = 'jetpack-carousel';
		wp_enqueue_style( 'jetpack-carousel', plugins_url( 'jetpack-carousel.css', __FILE__ ) );

		Jetpack::init()->implode_frontend_css( true );

		$seen_bundle = false;
		foreach ( $wp_styles->registered as $handle => $handle_obj ) {
			if ( $style_handle === $handle ) {
				$expected = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? "<!-- `{$style_handle}` is included in the concatenated jetpack.css -->\r\n" : '';
				$this->assertEquals( $expected, get_echo( array( $wp_styles, 'do_item' ), array( $handle ) ) );
			} elseif ( 'jetpack_css' === $handle ) {
				$seen_bundle = true;
			}
		}

		$this->assertTrue( $seen_bundle );
	}

	/**
	 * @author tonykova
	 * @covers Jetpack::check_identity_crisis
	 * @since 3.2.0
	 */
	public function test_check_identity_crisis_will_not_report_crisis_if_matching_siteurl() {
		// Store master user data
		Jetpack_Options::update_option( 'master_user', 'test' );
		Jetpack_Options::update_option( 'user_tokens', array( 'test' => 'herp.derp.test' ) );
		add_filter( 'jetpack_development_mode', '__return_false', 1, 1 );

		// Mock get_cloud_site_options
		$jp	= $this->getMockBuilder( 'MockJetpack' )
			->setMethods( array( 'get_cloud_site_options' ) )
			->getMock();

		$jp->init();
		Jetpack::$instance = $jp;

		$jp->expects( $this->any() )
			->method( 'get_cloud_site_options' )
			->will( $this->returnValue( array( 'siteurl' => 'https://test.site.com' ) ) );

		// Save the mismatching option for comparison
		// Using @ to prevent throwing an error on a bug in WP core when attempting to change .htaccess
		@update_option( 'siteurl', 'https://test.site.com' );

		// Attach hook for checking the errors
		add_filter( 'jetpack_has_identity_crisis', array( $this, 'pre_test_check_identity_crisis_will_not_report_crisis_if_matching_siteurl' ) );

		$this->assertTrue( false !== MockJetpack::check_identity_crisis( true ) );
		remove_filter( 'jetpack_has_identity_crisis', array( $this, 'pre_test_check_identity_crisis_will_not_report_crisis_if_matching_siteurl' ) );
	}

	public function pre_test_check_identity_crisis_will_not_report_crisis_if_matching_siteurl( $errors ){
		$this->assertCount( 0, $errors );
	}

	/**
	 * @author tonykova
	 * @covers Jetpack::check_identity_crisis
	 * @since 3.2.0
	 */
	public function test_check_identity_crisis_will_not_report_crisis_if_a_siteurl_mismatch_when_forcing_ssl() {
		// Kick in with force ssl and store master user data
		force_ssl_admin( true );
		Jetpack_Options::update_option( 'master_user', 'test' );
		Jetpack_Options::update_option( 'user_tokens', array( 'test' => 'herp.derp.test' ) );
		add_filter( 'jetpack_development_mode', '__return_false', 1, 1 );

		// Mock get_cloud_site_options
		$jp = $this->getMockBuilder( 'MockJetpack' )
		    ->setMethods( array( 'get_cloud_site_options' ) )
		    ->getMock();

		$jp->init();
		Jetpack::$instance = $jp;

		$jp->expects( $this->any() )
			->method( 'get_cloud_site_options' )
			->will( $this->returnValue( array( 'siteurl' => 'https://test.site.com' ) ) );

		// Save the mismatching option for comparison
		// Using @ to prevent throwing an error on a bug in WP core when attempting to change .htaccess
		@update_option( 'siteurl', 'http://test.site.com' );

		// Attach hook for checking the errors
		add_filter( 'jetpack_has_identity_crisis', array( $this, 'pre_test_check_identity_crisis_will_not_report_crisis_if_a_siteurl_mismatch_when_forcing_ssl') );

		$this->assertTrue( false !== MockJetpack::check_identity_crisis( true ) );
		remove_filter( 'jetpack_has_identity_crisis', array( $this, 'pre_test_check_identity_crisis_will_not_report_crisis_if_a_siteurl_mismatch_when_forcing_ssl') );
	}

	public function pre_test_check_identity_crisis_will_not_report_crisis_if_a_siteurl_mismatch_when_forcing_ssl( $errors ){
		$this->assertCount( 0, $errors );
	}
	
	/**
	 * @author tonykova
	 * @covers Jetpack::implode_frontend_css
	 * @since 3.2.0
	 */
	public function test_implode_frontend_css_does_not_enqueue_bundle_when_disabled_through_filter() {
		global $wp_styles;
		$wp_styles = new WP_styles();

		add_filter( 'jetpack_implode_frontend_css', '__return_false' );

		// Enqueue some script on the $to_dequeue list
		$style_handle = 'jetpack-carousel';
		wp_enqueue_style( 'jetpack-carousel', plugins_url( 'jetpack-carousel.css', __FILE__ ) );

		Jetpack::init()->implode_frontend_css();

		$seen_orig = false;
		foreach ( $wp_styles->registered as $handle => $handle_obj ) {
			$this->assertNotEquals( 'jetpack_css', $handle );
			if ( 'jetpack-carousel' === $handle ) {
				$seen_orig = true;
			}
		}

		$this->assertTrue( $seen_orig );
	}

	/**
	 * @author georgestephanis
	 * @covers Jetpack::dns_prefetch
	 * @since 3.3.0
	 */
	public function test_dns_prefetch() {
		// Save URLs that are already in to remove them later and perform a clean test.
		ob_start();
		Jetpack::dns_prefetch();
		$remove_this = ob_get_clean();

		Jetpack::dns_prefetch( 'http://example1.com/' );
		Jetpack::dns_prefetch( array(
			'http://example2.com/',
			'https://example3.com',
		) );
		Jetpack::dns_prefetch( 'https://example2.com' );

		$expected = "\r\n" .
		            "<link rel='dns-prefetch' href='//example1.com'>\r\n" .
		            "<link rel='dns-prefetch' href='//example2.com'>\r\n" .
		            "<link rel='dns-prefetch' href='//example3.com'>\r\n";

		$this->assertEquals( $expected, str_replace( $remove_this, "\r\n", get_echo( array( 'Jetpack', 'dns_prefetch' ) ) ) );
	}

	public function test_activating_deactivating_modules_fires_actions() {
		self::reset_tracking_of_module_activation();

		add_action( 'jetpack_activate_module', array( __CLASS__, 'track_activated_modules' ) );
		add_action( 'jetpack_deactivate_module', array( __CLASS__, 'track_deactivated_modules' ) );

		Jetpack::update_active_modules( array( 'stats' ) );
		Jetpack::update_active_modules( array( 'stats' ) );
		Jetpack::update_active_modules( array( 'json-api' ) );
		Jetpack::update_active_modules( array( 'json-api' ) );

		$this->assertEquals( self::$activated_modules, array( 'stats', 'json-api' ) );
		$this->assertEquals(  self::$deactivated_modules, array( 'stats' ) );

		remove_action( 'jetpack_activate_module', array( __CLASS__, 'track_activated_modules' ) );
		remove_action( 'jetpack_deactivate_module', array( __CLASS__, 'track_deactivated_modules' ) );
	}

	public function test_activating_deactivating_modules_fires_specific_actions() {
		self::reset_tracking_of_module_activation();
		add_action( 'jetpack_activate_module_stats', array( __CLASS__, 'track_activated_modules' ) );
		add_action( 'jetpack_deactivate_module_stats', array( __CLASS__, 'track_deactivated_modules' ) );

		Jetpack::update_active_modules( array( 'stats' ) );
		Jetpack::update_active_modules( array( 'stats' ) );
		Jetpack::update_active_modules( array( 'json-api' ) );
		Jetpack::update_active_modules( array( 'json-api' ) );

		$this->assertEquals( self::$activated_modules, array( 'stats' ) );
		$this->assertEquals(  self::$deactivated_modules, array( 'stats' ) );

		remove_action( 'jetpack_activate_module_stats', array( __CLASS__, 'track_activated_modules' ) );
		remove_action( 'jetpack_deactivate_module_stats', array( __CLASS__, 'track_deactivated_modules' ) );
	}

	public function test_get_other_linked_admins_one_admin_returns_false() {
		delete_transient( 'jetpack_other_linked_admins' );
		$other_admins = Jetpack::get_other_linked_admins();
		$this->assertFalse( $other_admins );
		$this->assertEquals( 0, get_transient( 'jetpack_other_linked_admins' ) );
	}

	public function test_get_other_linked_admins_more_than_one_not_false() {
		delete_transient( 'jetpack_other_linked_admins' );
		$master_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$connected_admin = $this->factory->user->create( array( 'role' => 'administrator' ) );

		Jetpack_Options::update_option( 'master_user', $master_user );
		Jetpack_Options::update_option( 'user_tokens', array(
			$connected_admin => 'apple.a.' . $connected_admin,
			$master_user     => 'kiwi.a.' . $master_user
		) );

		$other_admins = Jetpack::get_other_linked_admins();
		$this->assertInternalType( 'int', $other_admins );
		$this->assertInternalType( 'int', get_transient( 'jetpack_other_linked_admins' ) );
	}

	public function test_promoting_admin_clears_other_linked_admins_transient() {
		set_transient( 'jetpack_other_linked_admins', 2, HOUR_IN_SECONDS );
		$editor_user = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_update_user( array( 'ID' => $editor_user, 'role' => 'administrator' ) );

		$this->assertEquals( 0, get_transient( 'jetpack_other_linked_admins' ) );
	}

	public function test_demoting_admin_clear_other_linked_admins_transiet() {
		set_transient( 'jetpack_other_linked_admins', 2, HOUR_IN_SECONDS );
		$admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_update_user( array( 'ID' => $admin_user, 'role' => 'editor' ) );

		$this->assertEquals( 0, get_transient( 'jetpack_other_linked_admins' ) );
	}

	function test_changing_non_admin_roles_does_not_clear_other_linked_admins_transient() {
		set_transient( 'jetpack_other_linked_admins', 2, HOUR_IN_SECONDS );
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		foreach ( array( 'contributor', 'author', 'editor' ) as $role ) {
			wp_update_user( array( 'ID' => $user_id, 'role' => $role) );
		}

		$this->assertEquals( 2, get_transient( 'jetpack_other_linked_admins' ) );
	}

	function test_other_linked_admins_transient_set_to_zero_returns_false() {
		set_transient( 'jetpack_other_linked_admins', 0, HOUR_IN_SECONDS );
		$this->assertFalse( Jetpack::get_other_linked_admins() );
	}

	function test_idc_optin_defaults_to_development_version() {
		add_filter( 'jetpack_development_version', '__return_true' );
		$this->assertTrue( Jetpack::sync_idc_optin() );
		remove_filter( 'jetpack_development_version', '__return_true' );
	}

	function test_idc_optin_filter_overrides_development_version() {
		add_filter( 'jetpack_development_version', '__return_true' );
		add_filter( 'jetpack_sync_idc_optin', '__return_false' );
		$this->assertFalse( Jetpack::sync_idc_optin() );
		remove_filter( 'jetpack_development_version', '__return_true' );
		remove_filter( 'jetpack_sync_idc_optin', '__return_false' );
	}

	function test_idc_optin_casts_to_bool() {
		add_filter( 'jetpack_sync_idc_optin', array( $this, '__return_string_1' ) );
		$this->assertTrue( Jetpack::sync_idc_optin() );
		add_filter( 'jetpack_sync_idc_optin', array( $this, '__return_string_1' ) );
	}

	function __return_string_1() {
		return '1';
	}

	static function reset_tracking_of_module_activation() {
		self::$activated_modules = array();
		self::$deactivated_modules = array();
	}

	static function track_activated_modules( $module ) {
		self::$activated_modules[] = $module;
	}

	static function track_deactivated_modules( $module ) {
		self::$deactivated_modules[] = $module;
	}
} // end class
