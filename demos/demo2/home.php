<?php
function info( $nl = "\n" ) {
	global $action;
	$text = [
		sprintf( 'REQUEST_METHOD: [%s]', $_SERVER['REQUEST_METHOD'] ),
		sprintf( 'FORM action: [%s]', $action ),
		sprintf( '$_COOKIE: %s', trim( print_r( $_COOKIE, 1 ) ) ),
		sprintf( '$_REQUEST: %s', trim( print_r( $_REQUEST, 1 ) ) ),
	];
	return implode( $nl, $text );
}

$cookieName = 'demo2cookie';
$nonce = 'demo2-nonce';
$action = 'login';
$err = [];

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {

	switch ( $_REQUEST['action'] )
	{
		case 'login':
			if ( $nonce !== $_REQUEST['nonce'] ) {
				$err[] = sprintf( 'Invalid nonce: "%s" (should be "%s")', $_REQUEST['nonce'], $nonce );
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
	file_put_contents( __FILE__ . '-post.txt', info() );

	if ( $err ) {
		die( implode( ', ', $err ) );
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
<style>body { line-height: 2em; } pre { line-height: .7em; }</style>
<title>orkan/tlc - demo2: Loging in with cookie</title>
</head>
<body>
<pre>
<?php echo info( "<br>\n" ) ?><br>
</pre>
<form id="form-<?php echo $action ?>" method="post"
<?php printf( 'action="%s?action=%s"', $_SERVER['PHP_SELF'], $action ) ?>><?php
if ( 'login' === $action ) {
	echo 'User: <input type="text" name="user" /><br>';
	echo 'Pass: <input type="text" name="pass" /><br>';
	echo '<input type="hidden" name="nonce" value="' . $nonce . '" />';
	echo '<button>Log In</button>';
}
else {
	printf( 'Loged in as: <strong>%s</strong> (pass: %s)<br><button>Log Out</button>', $uname, $upass );
}

?>
</form>

</body>
</html>