<?php
require_once __DIR__ . '/../commun/ArchivedEndpoint.php'; archivedEndpointGuard(__FILE__);
// 86400 = 24 x 60 x 60 sec ...
echo time()%86400;
