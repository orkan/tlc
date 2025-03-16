<?php
function info() {
	global $action;
	return [
		sprintf( 'REQUEST_METHOD: [%s]', $_SERVER['REQUEST_METHOD'] ),
		sprintf( 'FORM action: [%s]', $action ),
		sprintf( '$_COOKIE: %s', trim( print_r( $_COOKIE, 1 ) ) ),
		sprintf( '$_REQUEST: %s', trim( print_r( $_REQUEST, 1 ) ) ),
	];
}

$cookieName = 'login_form-cookie';
$nonce = 'login_form-nonce';
$action = 'login';
$err = [];

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {

	switch ( $_REQUEST['action'] )
	{
		case 'login':
			if ( $nonce !== $_REQUEST['nonce'] ) {
				$err[] = sprintf( 'Invalid nonce: "%s". Expected: "%s"', $_REQUEST['nonce'], $nonce );
			}
			foreach ( [ 'user', 'pass' ] as $field ) {

				if ( !isset( $_REQUEST[$field] ) ) {
					$err[] = 'Missing field: ' . $field;
				}
				elseif ( empty( $_REQUEST[$field] ) ) {
					$err[] = 'Emprty field: ' . $field;
				}
			}

			if ( !$err ) {
				setcookie( $cookieName, "{$_REQUEST['user']}|{$_REQUEST['pass']}", strtotime( '+1 hour' ) );
			}
			break;

		case 'logout':
			setcookie( $cookieName, '', 0 );
			break;

		default:
			$err[] = sprintf( 'Undefined action: %s', $_REQUEST['action'] );
			setrawcookie( $cookieName, "action={$_REQUEST['action']}", [ 'Expires' => strtotime( '+1 hour' ), 'SameSite' => 'Lax' ] );
	}

	// Save POST data before redirect
	file_put_contents( __FILE__ . '-post.txt', implode( "\n", info() ) );

	if ( $err ) {
		die( sprintf( '<html><body><p id="error">%s</p></body></html>', implode( ', ', $err ) ) );
	}

	exit( header( 'Location: ' . $_SERVER['PHP_SELF'] ) );
}

if ( isset( $_COOKIE[$cookieName] ) ) {
	$action = 'logout';
	$cookie = explode( '|', $_COOKIE[$cookieName] );
	$uname = $cookie[0];
	$upass = $cookie[1];
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>body { line-height: 2em; } pre { line-height: 1em; }</style>
<title><?php echo basename( __DIR__ ) ?> - TLC Demo</title>
</head>
<body>
<h1>TLC Demo: Sign in with cookie</h1>
<pre>
<?php echo implode( "\n", info() ) ?>
</pre>
<form id="form-<?php echo $action ?>" method="post"
<?php printf( 'action="%s?action=%s"', $_SERVER['PHP_SELF'], $action ) ?>><?php
if ( 'login' === $action ) {
	echo implode( "<br>\n", [
		'User: <input type="text" name="user">',
		'Pass: <input type="text" name="pass">',
		'<input type="hidden" name="nonce" value="' . $nonce . '" />',
		'<button>Sign in</button>',
	]);
}
else {
	printf( 'Signed in as: <strong>%s</strong> (pass: %s)<br><button>Sign out</button>', $uname, $upass );
}

?>
</form>
</body>
</html>
