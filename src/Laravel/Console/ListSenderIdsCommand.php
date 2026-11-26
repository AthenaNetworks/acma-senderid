<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Console;

use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotSummary;
use AthenaNetworks\AcmaSenderId\Exceptions\SenderIdException;
use AthenaNetworks\AcmaSenderId\SenderIdClient;
use Illuminate\Console\Command;

final class ListSenderIdsCommand extends Command
{
    protected $signature = 'senderid:list
        {--status=* : Filter by verification status (not-started, pending, complete)}
        {--json : Output raw JSON}';

    protected $description = 'List Sender IDs (chatbots) registered by your account';

    public function handle(SenderIdClient $client): int
    {
        try {
            /** @var list<string> $statuses */
            $statuses = (array) $this->option('status');
            $senderIds = $client->senderIds()->list($statuses);
        } catch (SenderIdException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(array_map(static fn (ChatbotSummary $s): array => $s->raw, $senderIds), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($senderIds === []) {
            $this->components->info('No Sender IDs found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Sender ID', 'Brand', 'Verified', 'ACMA status', 'Expires'],
            array_map(static fn (ChatbotSummary $s): array => [
                $s->id,
                $s->serviceName,
                $s->brandName,
                $s->verified,
                ($s->acmaStatusName ?? '') . ($s->hasEmailBounce() ? ' ⚠' : ''),
                $s->expiresOn?->format('Y-m-d'),
            ], $senderIds),
        );

        return self::SUCCESS;
    }
}
