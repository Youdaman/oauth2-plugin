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

		// add_filter( 'rest_pre_dispatch', array( $this, 'filter_rest_pre_dispatch' ), 10, 3 );

		// add_filter( 'rest_authentication_errors', array( $this, 'filter_rest_authentication_errors' ) );

		// init the rest api
		add_action( 'rest_api_init', array( $this, 'action_rest_api_init' ) );

		// init carbon fields
		add_action( 'after_setup_theme', array( $this, 'load_carbon_fields' ) );

		// register the carbon fields
		add_action( 'carbon_fields_register_fields', array( $this, 'add_plugin_settings_page' ) );
	}

	public function filter_rest_pre_dispatch( $result, $server, $request ) {
		if ( $request->get_route() === $this->route_namespace . '/hello' ) {
			$result = $this->route_hello( $request );
		}
		return $result;
	}

	public function filter_rest_authentication_errors( $result ) {
		if ( ! empty( $result ) ) {
			return $result;
		}
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'rest_not_logged_in', 'You are not currently logged in.', array( 'status' => 401 ) );
		}
		return $result;
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
			'permission_callback' => function () {

				$scopes = carbon_get_theme_option( 'scopes' );

				$current_user_id = get_current_user_id();
				// $current_route = $_SERVER['REQUEST_URI'];
				$current_route = '/' . $this->route_namespace . '/hello';
				$current_method = $_SERVER['REQUEST_METHOD'];

				// // phpcs:disable
				error_log( 'current_user_id: ' . $current_user_id );
				error_log( 'current_route: ' . $current_route );
				error_log( 'current_method: ' . $current_method );
				// error_log( 'scopes: ' . print_r( $scopes, true ) );
				// // phpcs:enable

				// filter scopes by current user and current route
				$filtered_scopes = array_filter( $scopes, function ( $scope ) use ( $current_user_id, $current_route, $current_method ) {
					return in_array( $current_user_id, $scope['users'], true )
						&& in_array( $current_route, $scope['routes'], true )
						&& in_array( $current_method, $scope['methods'], true );
				} );
				return ! empty( $filtered_scopes );
			},
		));

		register_rest_route( $this->route_namespace, '/scopes', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => fn() => rest_ensure_response( carbon_get_theme_option( 'scopes' ) ),
			'permission_callback' => '__return_true',
		));
	}

	public function route_hello( $request ) {
		$name = $request['name'] ?? 'world';
		return rest_ensure_response( "hello $name" );
	}
}

$oauth2_plugin = new OAuth2_Plugin();
