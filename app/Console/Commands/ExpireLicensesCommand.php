<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;
use Log;

class ExpireLicensesCommand extends Command
{
    protected $signature = 'licenses:expire';
    protected $description = 'Atualiza licenças ativas para expiradas.';

    public function __construct(
        protected LicenseService $licenseService
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {

        $this->info("Iniciando verificação de licenças expiradas...");
        $affected = $this->licenseService->expiredLicensesCheck();
        if ($affected === 0) {
            $this->info("Nenhuma licença expirada foi encontrada.");
        } else {
            $this->info("{$affected} licenças expiradas atualizadas com sucesso.");
        }

        Log::channel('commands')->info('Execução de comando', [
            'command'  => $this->getName(),
            'affected' => $affected,
        ]);

        return self::SUCCESS;
    }
}
