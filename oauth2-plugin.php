<?php
/**
 * Plugin Name: OAuth2 Plugin
 *
 * @package OAuth2_Plugin
 */

// see https://oauth2-client.thephpleague.com/usage/
// and https://devblog.xero.com/use-php-to-connect-with-xero-31945bccd037

namespace OAuth2_Plugin;

require 'vendor/autoload.php';

class OAuth2_Plugin {

	private $provider;
	private $client_id = 'erzelqpu26lg';
	private $client_secret = 'Ald5C3xay0KjOd1cuMlEvKFw7LC8ALQas28hu5wvNhClE9li';
	private $redirect_uri = 'https://oauth2-plugin.wp/wp-json/foo/v1/callback';
	private $scope = array( 'openid email profile offline_access foo.bar' );

	/**
	 * OAuth2_Plugin constructor.
	 */
	public function __construct() {
		session_start();

		// $http_client = new \GuzzleHttp\Client( array( 'curl' => array( CURLOPT_SSL_VERIFYPEER => false ) ) );
		$http_client = new \GuzzleHttp\Client( array( 'verify' => false ) );

		$this->provider = new \League\OAuth2\Client\Provider\GenericProvider(
			array(
				'clientId'                => $this->client_id,
				'clientSecret'            => $this->client_secret,
				'redirectUri'             => $this->redirect_uri,
				'urlAuthorize'            => 'https://test.wp/wp-json/oauth2/authorize',
				'urlAccessToken'          => 'https://test.wp/wp-json/oauth2/access_token',
				'urlResourceOwnerDetails' => 'https://test.wp/wp-json/wp/v2/users/me',
			),
			array(
				'httpClient' => $http_client,
			)
		);

		add_action( 'rest_api_init', array( $this, 'action_rest_api_init' ) );
		// add_action( 'init', array( $this, 'action_init' ) );
	}

	/**
	 * Fires once WordPress has finished loading but before any headers are sent.
	 */
	// public function action_init() {
	// }

	/**
	 * Fires when preparing to serve a REST API request.
	 */
	public function action_rest_api_init() {

		$route_namespace = 'foo/v1';

		register_rest_route( $route_namespace, '/hello', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'hello_route' ),
			'permission_callback' => '__return_true',
		));

		register_rest_route( $route_namespace, '/auth', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'auth_route' ),
			'permission_callback' => '__return_true',
		));

		register_rest_route( $route_namespace, '/callback', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'callback_route' ),
			'permission_callback' => '__return_true',
		));
	}

	public function hello_route( $request ) {
		$name = $request['name'] ?? 'world';
		return rest_ensure_response( "hello $name" );
	}

	public function auth_route() {

		// Fetch the authorization URL from the provider; this returns the
		// urlAuthorize option and generates and applies any necessary parameters (e.g. state).
		// $authorization_url = $this->provider->getAuthorizationUrl( array(
		// 	'scope' => $this->scope,
		// ) );
		$authorization_url = $this->provider->getAuthorizationUrl();

		// Get the state generated for you and store it to the session.
		$_SESSION['oauth2state'] = $this->provider->getState();

		// Redirect the user to the authorization URL.
		header( 'Location: ' . $authorization_url );
		exit;
	}

	public function callback_route() {

		// Check given state against previously stored one to mitigate CSRF attack
		if ( empty( $_GET['state'] ) || empty( $_SESSION['oauth2state'] ) || ( $_GET['state'] !== $_SESSION['oauth2state'] ) ) {

			if ( isset( $_SESSION['oauth2state'] ) ) {
				error_log( 'oauth2state: ' . $_SESSION['oauth2state'] );
				unset( $_SESSION['oauth2state'] );
			} else {
				error_log( 'oauth2state: empty' );
			}

			wp_die( 'Invalid state' );
		}

		if ( empty( $_GET['code'] ) ) {
			wp_die( 'Missing code' );
		}

		try {
			// Try to get an access token using the authorization code grant.
			$access_token = $this->provider->getAccessToken('authorization_code', array(
				'code' => $_GET['code'],
			));

			// wp_die( json_encode($access_token) ); // {"token_type":"bearer","access_token":"ZUrHwT3nRHHN"}

			// We have an access token, which we may use in authenticated requests
			// Retrieve the user's profile
			// $options['headers']['Accept'] = 'application/json';
			$me_request = $this->provider->getAuthenticatedRequest(
				'GET',
				'https://test.wp/wp-json/wp/v2/users/me',
				$access_token
				// $access_token->getToken(),
				// $options
			);
			$me = $this->provider->getParsedResponse( $me_request );

			$posts_request = $this->provider->getAuthenticatedRequest(
				'GET',
				'https://test.wp/wp-json/wp/v2/posts',
				$access_token
			);
			$posts = $this->provider->getParsedResponse( $posts_request );

			wp_send_json( array(
				'access_token' => $access_token->getToken(),
				'refresh_token' => $access_token->getRefreshToken(),
				// 'expires' => $access_token->getExpires(), // expires is not set so this errors
				// 'has_expired' => $access_token->hasExpired(), // expires is not set so this errors
				'resource_owner' => $this->provider->getResourceOwner( $access_token )->toArray(),
				'me' => $me,
				'posts' => $posts,
			));

		} catch ( \League\OAuth2\Client\Provider\Exception\IdentityProviderException $e ) {
			// Failed to get the access token or user details.
			wp_die( esc_html( $e->getMessage() ) );
		}
	}
}

new OAuth2_Plugin();
