<?php

require_once 'vendor/autoload.php';

// see https://oauth2-client.thephpleague.com/usage/
// and https://devblog.xero.com/use-php-to-connect-with-xero-31945bccd037
// NOTE: mostly from phpleague with some xero stuff added and commented accordingly

// xero article uses this, phpleague docs do not
session_start();

$oauth2_host = 'https://test.wp';
$client_id = 'erzelqpu26lg';
$client_secret = 'Ald5C3xay0KjOd1cuMlEvKFw7LC8ALQas28hu5wvNhClE9li';
// $callback_uri = 'https://' . $_SERVER['HTTP_HOST'] . '/test.php'; // phpcs:ignore
$callback_uri = plugin_dir_url( __FILE__ ) . 'test.php';
$scope = array( 'foo bar baz' );
$route_namespace = 'foo/v1';

$provider = new \League\OAuth2\Client\Provider\GenericProvider(
	array(
		'clientId'                => $client_id,
		'clientSecret'            => $client_secret,
		'redirectUri'             => $callback_uri,
		'urlAuthorize'            => $oauth2_host . '/wp-json/oauth2/authorize',
		'urlAccessToken'          => $oauth2_host . '/wp-json/oauth2/access_token',
		'urlResourceOwnerDetails' => $oauth2_host . '/wp-json/wp/v2/users/me',
	),
	// neither xero article nor phpleague docs use this -- avoid self-signed certificate errors
	array(
		// override the default Guzzle client to avoid self-signed certificate errors
		'httpClient' => new \GuzzleHttp\Client( array( 'verify' => false ) ),
	)
);

// If we don't have an authorization code then get one
if ( !isset( $_GET['code'] ) ) {

	// xero article passes options, phpleague docs do not
	$options = array( 'scope' => $scope );

	// Fetch the authorization URL from the provider; this returns the
	// urlAuthorize option and generates and applies any necessary parameters
	// (e.g. state).
	$authorization_url = $provider->getAuthorizationUrl( $options );

	// Get the state generated for you and store it to the session.
	$_SESSION['oauth2state'] = $provider->getState();

	// // Optional, only required when PKCE is enabled.
	// // Get the PKCE code generated for you and store it to the session.
	// $_SESSION['oauth2pkceCode'] = $provider->getPkceCode();

	// Redirect the user to the authorization URL.
	// header( 'Location: ' . $authorization_url );

	// debug: output the authorization URL and callback URI
    echo 'callback_uri: ' . $callback_uri . '<br>'; // phpcs:ignore
    echo '<a href="' . $authorization_url . '">Log in with OAuth2</a>'; // phpcs:ignore

	exit;

	// Check given state against previously stored one to mitigate CSRF attack
} elseif ( empty( $_GET['state'] ) || empty( $_SESSION['oauth2state'] ) || $_GET['state'] !== $_SESSION['oauth2state'] ) {

	if ( isset( $_SESSION['oauth2state'] ) ) {
		unset( $_SESSION['oauth2state'] );
	}

	exit( 'Invalid state' );

} else {

	try {

		// // Optional, only required when PKCE is enabled.
		// // Restore the PKCE code stored in the session.
		// $provider->setPkceCode( $_SESSION['oauth2pkceCode'] );

		// Try to get an access token using the authorization code grant.
		$access_token = $provider->getAccessToken('authorization_code', array(
			'code' => $_GET['code'],
		));

		// We have an access token, which we may use in authenticated
		// requests against the service provider's API.
		echo 'Access Token: ' . $access_token->getToken() . "<br>"; // phpcs:ignore
		echo 'Refresh Token: ' . $access_token->getRefreshToken() . "<br>"; // phpcs:ignore
		// echo 'Expired in: ' . $access_token->getExpires() . "<br>"; // phpcs:ignore
		// echo 'Already expired? ' . ( $access_token->hasExpired() ? 'expired' : 'not expired' ) . "<br>";
		// NOTE: it seems OAuth 2 for WordPress does not set the expires parameter, so the above two lines throw the following error:
		// Fatal error: Uncaught RuntimeException: "expires" is not set on the token

		error_log('hello?'); // phpcs:ignore

		echo 'Hello world!';

		exit;

		// Using the access token, we may look up details about the
		// resource owner.
		$resource_owner = $provider->getResourceOwner( $access_token );

		echo 'Resource Owner ID: ' . $resource_owner->getId() . "<br>"; // phpcs:ignore

        // phpcs:ignore
		var_export( $resource_owner->toArray() );

		// xero example passes options with headers
		// $options['headers']['xero-tenant-id'] = $xeroTenantIdArray[0]['tenantId'];
		$options['headers']['Accept'] = 'application/json';

		// The provider provides a way to get an authenticated API request for
		// the service, using the access token; it returns an object conforming
		// to Psr\Http\Message\RequestInterface.
		$request = $provider->getAuthenticatedRequest(
			'GET',
			// 'https://service.example.com/resource',
			// 'https://api.xero.com/api.xro/2.0/Organisation',
			'https://test.wp/wp-json/wp/v2/users/me',
			$access_token,
			$options
		);

        // phpcs:ignore
		var_export( $provider->getParsedResponse( $request ) );

	} catch ( \League\OAuth2\Client\Provider\Exception\IdentityProviderException $e ) {

		// Failed to get the access token or user details.
		exit( esc_html( $e->getMessage() ) );

	}
}
