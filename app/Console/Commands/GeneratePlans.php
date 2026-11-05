<?php

namespace App\Console\Commands;

use App\Models\ServiceContract;
use App\Models\Tenant;
use App\Modules\Contract\GeneratePlanHorizon;
use App\Modules\Platform\CurrentTenant;
use Illuminate\Console\Command;

class GeneratePlans extends Command
{
    protected $signature = 'sessia:generate-plans {--months=2 : Horizonte de 1 a 3 meses}';

    protected $description = 'Gera competências, mensalidades e agenda de planos ativos, sem duplicidade.';

    public function handle(): int
    {
        $months = filter_var($this->option('months'), FILTER_VALIDATE_INT);
        if ($months === false || $months < 1 || $months > 3) {
            $this->error('Informe de 1 a 3 meses.');

            return self::FAILURE;
        }
        foreach (Tenant::cursor() as $tenant) {
            app(CurrentTenant::class)->forJob($tenant, function () use ($months) {
                foreach (ServiceContract::where('type', 'plan')->where('status', 'active')->cursor() as $contract) {
                    foreach (app(GeneratePlanHorizon::class)->handle($contract->id, $months) as $message) {
                        $this->warn('Contrato #'.$contract->id.': '.$message);
                    }
                }
            });
        }
        $this->info('Geração concluída. Competências parciais ou conflitos precisam de revisão.');

        return self::SUCCESS;
    }
}
