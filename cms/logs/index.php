<?php
// Audit logs are not a public attraction; return to the authenticated CMS instead.
http_response_code(404);
header("Location: /cms/");
