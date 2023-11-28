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

		// init the rest api
		add_action( 'rest_api_init', array( $this, 'action_rest_api_init' ) );

		// init carbon fields
		add_action( 'after_setup_theme', array( $this, 'load_carbon_fields' ) );

		// register the carbon fields
		add_action( 'carbon_fields_register_fields', array( $this, 'add_plugin_settings_page' ) );
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
						Field::make( 'complex', 'endpoints', 'Endpoints' )
							->add_fields( array(
								// Field::make( 'text', 'url', 'URL' ),
								Field::make( 'select', 'url', 'URL' )
									->add_options( array( $this, 'get_routes' ) ),
								Field::make( 'select', 'method', 'Method' )
									->add_options( array( 'GET', 'POST', 'DELETE', 'PATCH', 'PUT' ) ),
							)),
					)),
			) );
	}

	public function get_routes() {
		return array_keys( rest_get_server()->get_routes() );
	}

	public function action_rest_api_init() {
		register_rest_route( $this->route_namespace, '/hello', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'route_hello' ),
			'permission_callback' => '__return_true',
		));
	}

	public function route_hello( $request ) {
		$name = $request['name'] ?? 'world';
		return rest_ensure_response( "hello $name" );
	}
}

$oauth2_plugin = new OAuth2_Plugin();
