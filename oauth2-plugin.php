<?php
/**
 * Plugin Name: OAuth2 Plugin
 *
 * @package OAuth2_Plugin
 */

namespace OAuth2_Plugin;

class OAuth2_Plugin {

	/**
	 * OAuth2_Plugin constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'action_rest_api_init' ) );
	}

	/**
	 * Fires when preparing to serve a REST API request.
	 */
	public function action_rest_api_init() {

		$route_namespace = 'foo/v1';

		register_rest_route( $route_namespace, '/hello', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'hello' ),
			'permission_callback' => '__return_true',
		));
	}

	public function hello( $request ) {
		$name = $request['name'] ?? 'world';
		return rest_ensure_response( "hello $name" );
	}
}

new OAuth2_Plugin();
