<?php

require_once 'EOProxy.php';

echo "== Simple EOProxy v4.0.0 by Sausage ==\n";

$eoproxy = new EOProxy();
$eoproxy->RunProxy(
	'127.0.0.1',	8079,	// local endpoint
	'127.0.0.1',	8078	// remote endpoint
);

