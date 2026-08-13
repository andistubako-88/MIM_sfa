<?php
declare(strict_types=1);
require __DIR__.'/auth.php';
json_response(['success'=>true,'user'=>require_auth()]);
