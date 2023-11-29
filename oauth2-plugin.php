<?php
/**
 * Plugin Name: OAuth2 Plugin
 *
 * @package OAuth2_Plugin
 */

namespace OAuth2_Plugin;

require_once 'vendor/autoload.php';

use Carbon_Fields\Container;
use Carbon_Fields\Field;
class OAuth2_Plugin {

	private $route_namespace = 'foo/v1';

	/**
	 * OAuth2_Plugin constructor.
	 */
	public function __construct() {

		// use this instead of per route permission callbacks
		add_filter( 'rest_authentication_errors', array( $this, 'filter_rest_authentication_errors' ) );

		// init the rest api
		add_action( 'rest_api_init', array( $this, 'action_rest_api_init' ) );

		// init carbon fields
		add_action( 'after_setup_theme', array( $this, 'load_carbon_fields' ) );

		// register the carbon fields
		add_action( 'carbon_fields_register_fields', array( $this, 'add_plugin_settings_page' ) );
	}

	public function filter_rest_authentication_errors( $errors ) {
		if ( is_wp_error( $errors ) ) {
			return $errors;
		}

		error_log( 'filter_rest_authentication_errors' ); // phpcs:ignore
		error_log( $this->user_can_access_route() ); // phpcs:ignore

		return $this->user_can_access_route() ? $errors : new \WP_Error(
			'rest_forbidden',
			__( 'You cannot view the requested resource.' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function load_carbon_fields() {
		\Carbon_Fields\Carbon_Fields::boot();
	}

	public function add_plugin_settings_page() {
		Container::make( 'theme_options', __( 'OAuth2 Plugin Settings' ) )
			->set_page_parent( 'options-general.php' )
			->add_fields( array(
				Field::make( 'complex', 'scopes', 'Scopes' )
					->add_fields( array(
						Field::make( 'text', 'name', 'Name' ),
						Field::make( 'multiselect', 'users', 'Users' )
							->add_options( array( $this, 'get_users' ) ),
						Field::make( 'multiselect', 'routes', 'Routes' )
							->add_options( array( $this, 'get_routes' ) ),
						Field::make( 'multiselect', 'methods', 'Methods' )
							->add_options( array(
								'GET' => 'GET',
								'POST' => 'POST',
								'DELETE' => 'DELETE',
								'PATCH' => 'PATCH',
								'PUT' => 'PUT',
							) ),
					)),
			) );
	}

	public function get_users() {
		// associative array of users with id => name
		$users = array();
		// get all users
		$wp_users = get_users();
		// loop through users
		foreach ( $wp_users as $wp_user ) {
			// add user to users array
			$users[ $wp_user->ID ] = $wp_user->display_name;
		}
		return $users;
	}

	public function get_routes() {
		// associative array of routes with route => route
		$routes = array();
		// get all routes
		$wp_routes = rest_get_server()->get_routes();
		// loop through routes
		foreach ( $wp_routes as $wp_route => $wp_route_data ) {
			// add route to routes array
			$routes[ $wp_route ] = $wp_route;
		}
		return $routes;
	}

	public function action_rest_api_init() {

		register_rest_route( $this->route_namespace, '/hello', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'route_hello' ),
			// 'permission_callback' => array( $this, 'hello_route_permission_callback' ),
			'permission_callback' => '__return_true',
		));

		// debug route to see all scopes
		register_rest_route( $this->route_namespace, '/scopes', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => fn() => rest_ensure_response( carbon_get_theme_option( 'scopes' ) ),
			'permission_callback' => '__return_true',
		));
	}

	public function user_can_access_route() {

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$scopes = carbon_get_theme_option( 'scopes' );

		$current_user_id = get_current_user_id();
		$current_route = strtok( $_SERVER['REQUEST_URI'], '?' );
		$current_method = $_SERVER['REQUEST_METHOD'];

		// remove /wp-json from current route
		// this is needed because scope routes are stored without /wp-json
		// and we want to compare the current route with the scope routes below
		$current_route = str_replace( '/wp-json', '', $current_route );

		// if current route starts with /oauth2/ return true
		// this is needed so the oauth2 plugin can work
		if ( strpos( $current_route, '/oauth2/' ) === 0 ) {
			return true;
		}

		// // phpcs:disable
		// error_log( 'user_can_access_route' );
		// error_log( $current_user_id );
		// error_log( $current_route );
		// error_log( $current_method );
		// error_log( json_encode( $scopes ) );
		// // phpcs:enable

		// filter scopes by current user and current route
		$filtered_scopes = array_filter( $scopes, function ( $scope ) use ( $current_user_id, $current_route, $current_method ) {
			return in_array( $current_user_id, $scope['users'], true )
				&& in_array( $current_route, $scope['routes'], true )
				&& in_array( $current_method, $scope['methods'], true );
		} );

		return ! empty( $filtered_scopes );
	}

	public function hello_route_permission_callback() {

		$scopes = carbon_get_theme_option( 'scopes' );

		$current_user_id = get_current_user_id();
		$current_route = '/' . $this->route_namespace . '/hello';
		$current_method = $_SERVER['REQUEST_METHOD'];

		// filter scopes by current user and current route
		$filtered_scopes = array_filter( $scopes, function ( $scope ) use ( $current_user_id, $current_route, $current_method ) {
			return in_array( $current_user_id, $scope['users'], true )
				&& in_array( $current_route, $scope['routes'], true )
				&& in_array( $current_method, $scope['methods'], true );
		} );

		return ! empty( $filtered_scopes );
	}

	public function route_hello( $request ) {
		$name = $request['name'] ?? 'world';
		return rest_ensure_response( "hello $name" );
	}
}

$oauth2_plugin = new OAuth2_Plugin();
