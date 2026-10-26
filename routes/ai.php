<?php

use App\Mcp\Servers\CentinelaAgentServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP — superficie agentes P3a (SDD-250)
|--------------------------------------------------------------------------
|
| Path /mcp (POST JSON-RPC). Misma seguridad que api/v1 salvo agent.readonly
| (MCP usa POST para el protocolo).
|
*/

$agentMcpStack = [
    'agent.enabled',
    'agent.log',
    'agent.ip',
    'throttle:agent',
    'agent.auth',
    'agent.ability:read',
    'agent.year',
];

Mcp::web('/mcp', CentinelaAgentServer::class)
    ->middleware($agentMcpStack);
