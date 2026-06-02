<?php

use App\Mcp\Middleware\SingleUserMiddleware;
use App\Mcp\Servers\RemindServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', RemindServer::class)->middleware(SingleUserMiddleware::class);
