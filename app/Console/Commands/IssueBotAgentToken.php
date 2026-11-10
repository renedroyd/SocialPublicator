<?php

namespace App\Console\Commands;

use App\Models\BotAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueBotAgentToken extends Command
{
    protected $signature = 'bot-agent:issue-token {workspace_id : Workspace ID} {instance_id : Registered agent instance ID}';

    protected $description = 'Generate or rotate the one-time API token for a WBot agent instance';

    public function handle(): int
    {
        $agent = BotAgent::withoutGlobalScopes()
            ->where('workspace_id', (int) $this->argument('workspace_id'))
            ->where('instance_id', (string) $this->argument('instance_id'))
            ->first();

        if (! $agent) {
            $this->error('No existe un agente con ese workspace e instance_id.');

            return self::FAILURE;
        }

        if (! $agent->is_active) {
            $this->error('El agente está desactivado. Actívalo antes de emitir un token.');

            return self::FAILURE;
        }

        $token = Str::random(64);
        $agent->forceFill([
            'token_hash' => hash('sha256', $token),
        ])->save();

        $this->newLine();
        $this->info('Token generado. Cópialo ahora; no se almacena en texto plano y no podrá consultarse nuevamente.');
        $this->line($token);
        $this->newLine();
        $this->warn('Configura este valor como LARAVEL_API_TOKEN en el .env del agente WBot correspondiente.');

        return self::SUCCESS;
    }
}
