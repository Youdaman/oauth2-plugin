<?php
/**
 * Plugin Name: OAuth2 Plugin
 *
 * @package OAuth2_Plugin
 */

// see https://oauth2-client.thephpleague.com/usage/
// and https://devblog.xero.com/use-php-to-connect-with-xero-31945bccd037

namespace OAuth2_Plugin;

require_once 'vendor/autoload.php';

class OAuth2_Plugin {

	private $provider;
	private $client_id = 'erzelqpu26lg';
	private $client_secret = 'Ald5C3xay0KjOd1cuMlEvKFw7LC8ALQas28hu5wvNhClE9li';
	private $redirect_uri = 'https://oauth2-plugin.wp/wp-json/foo/v1/callback';
	private $scope = array( 'openid email profile offline_access foo.bar' );
	private $route_namespace = 'foo/v1';

	/**
	 * OAuth2_Plugin constructor.
	 */
	public function __construct() {

		// Start a session since we need to store the state parameter
		session_start();

		// Instantiate the OAuth2 client
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
				// override the default Guzzle client to avoid self-signed certificate errors
				'httpClient' => new \GuzzleHttp\Client( array( 'verify' => false ) ),
			)
		);

		// init the rest api
		add_action( 'rest_api_init', array( $this, 'action_rest_api_init' ) );
	}

	/**
	 * Fires when preparing to serve a REST API request.
	 */
	public function action_rest_api_init() {

		// generic route for testing
		register_rest_route( $this->route_namespace, '/hello', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'route_hello' ),
			'permission_callback' => '__return_true',
		));

		// route for initiating the oauth2 flow
		register_rest_route( $this->route_namespace, '/auth', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'route_auth' ),
			'permission_callback' => '__return_true',
		));

		// route for the oauth2 callback
		register_rest_route( $this->route_namespace, '/callback', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'route_callback' ),
			'permission_callback' => '__return_true',
		));

		// route for revoking the oauth2 token
		register_rest_route( $this->route_namespace, '/revoke', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'route_revoke' ),
			// 'permission_callback' => fn() => current_user_can( 'read' ), // only logged in users
			'permission_callback' => '__return_true',
		));
	}

	public function route_hello( $request ) {
		$name = $request['name'] ?? 'world';
		return rest_ensure_response( "hello $name" );
	}

	public function route_auth() {

		$authorization_url = $this->provider->getAuthorizationUrl( array(
			'scope' => $this->scope,
			// 'state' => 'foobar123',
		) );

		$_SESSION['oauth2state'] = $this->provider->getState();

		header( 'Location: ' . $authorization_url );
		exit;
	}

	public function route_callback() {

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

	public function route_revoke( $request ) {
		$this->check_auth( $request );
		return rest_ensure_response( 'revoke' );
	}

	public function check_auth( $request ) {

		if ( isset( $_SESSION['oauth2'] ) ) {

			// // get tenant id from request/user/session and set on user so /connections returns last used tenant id for frontend to use
			// $tenant_id = $request['tenant_id'] ?? get_field('tenant_id', 'user_' . get_current_user_id()) ?? $_SESSION['oauth2']['tenant_id'];
			// update_field('tenant_id', $tenant_id, 'user_' . get_current_user_id());

			if ( $_SESSION['oauth2']['expires'] < time() ) {

				$access_token = $this->provider->getAccessToken(
					'refresh_token',
					array(
						'refresh_token' => $_SESSION['oauth2']['refresh_token'],
					)
				);

				$_SESSION['oauth2'] = array(
					'token' => $access_token->getToken(),
					'expires' => $access_token->getExpires(),
					'refresh_token' => $access_token->getRefreshToken(),
					'id_token' => $access_token->getValues()["id_token"],
					// // 'tenant_id' => $_SESSION['oauth2']['tenant_id'],
					// 'tenant_id' => $tenant_id,
				);
			}
		} else {
			wp_redirect( rest_url( $this->route_namespace . '/auth' ) );
			exit;
		}
	}
}

new OAuth2_Plugin();
