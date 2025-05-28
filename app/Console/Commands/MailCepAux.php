<?php

namespace App\Console\Commands;

use App\bibliotecas\PHPMailer;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\ViaCep\Facades\ViaCep;
use App\Services\ViaCep\Exceptions\CepException;
use Rap2hpoutre\FastExcel\SheetCollection;
use Rap2hpoutre\FastExcel\FastExcel;

class MailCepAux extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'MailCepAux {--test}';

    /**
     * The console command description.
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->buscaCepSemRegistroAux();
        $this->enviaEmailPostoCidadeDivergente();
    }

    private function buscaCepSemRegistroAux(): void
    {
        $this->info("Iniciando consulta:");

        $ceps = $this->getCepSemRegistroAux();

        foreach ($ceps as $data) {
	        try {
	            if(!$data->CEP) {
	                continue;
	            }

        		$this->info("Consultando CEP {$data->CEP}...");

	            ViaCep::cep()->get($data->CEP);
	        } catch(CepException $e) {
	            $this->warn($e->getMessage());
	        } catch(\Throwable $e) {
	            $this->warn("Erro ao consultar o CEP [{$data->CEP}] - {$e->getMessage()}");
	        } finally {
	        	sleep(5);
	        }
        }
    }

    private function getCepSemRegistroAux(): array
    {
        return DB::connection('sqlsrv')->select("
			SELECT
				NEXTI_POSTO.CEP
			FROM ".env('DB_OWNER')."NEXTI_POSTO
			WHERE NEXTI_POSTO.CEP <> ''
			AND NOT EXISTS(
				SELECT
					1
				FROM ".env('DB_OWNER')."CEP_AUX
				WHERE CEP_AUX.CEP = NEXTI_POSTO.CEP
			)
			GROUP BY NEXTI_POSTO.CEP
        ");
    }

    private function enviaEmailPostoCidadeDivergente(): void
    {
        if(!$this->verificaDisparoLog() && !$this->option('test')) {
            $this->warn('Horário de envio ainda não chegou!');
            return;
        }

        $postos = $this->getPostoCidadesDivergentes();
        if(!$postos) {
            $this->warn('Sem dados a enviar');
            return;
        }

        $data = [
            'Postos com IBGE Divergente' => array_map(function(object $item) {
                return [
                    'EMPRESA' => $item->NUMEMP,
                    'FILIAL' => $item->CODFIL,
                    'CENTRO DE CUSTO' => $item->CODCCU,
                    'NOME POSTO' => $item->DESPOS,
                    'IBGE Protheus' => $item->IBGE_PROTHEUS ?? 0,
                    'IBGE ViaCep' => $item->IBGE_AUX ?? 0
                ];
            }, $postos),
        ];

        $this->enviaEmail(['parcerias@flexsystems.com.br'], $data);

        if(!$this->option('test')) {
            $this->updateDataLog(date('Y-m-d H:i:s'));
        }
    }

    private function getPostoCidadesDivergentes(): array
    {
        return DB::connection('sqlsrv')->select("
			SELECT
				NEXTI_POSTO.POSTRA,
				NEXTI_POSTO.NUMEMP,
				NEXTI_POSTO.CODFIL,
				NEXTI_POSTO.CODCCU,
				NEXTI_POSTO.DESPOS,
				NEXTI_POSTO.CIDADE AS IBGE_PROTHEUS,
				CEP_AUX.IBGE AS IBGE_AUX
			FROM ".env('DB_OWNER')."NEXTI_POSTO
			JOIN ".env('DB_OWNER')."CEP_AUX
				ON(CEP_AUX.CEP = NEXTI_POSTO.CEP
					AND NEXTI_POSTO.CIDADE <> CEP_AUX.IBGE
				)
			WHERE NEXTI_POSTO.CEP <> ''
        ");
    }

    /**
     * Get Last Log
     **/
    private function verificaDisparoLog(): bool
    {
        $now = Carbon::now();
        if($now->format('H') < 6 && $now->format('H') > 15) {
            return false;
        }

        $last = DB::connection('sqlsrv')
            ->table(env('DB_OWNER').'NEXTI_DATA_LOG_EMAIL')
            ->select('DATA_INICIO')
            ->where('TIPO', 'CEP')
            ->first('DATA_INICIO');

        return Carbon::parse($last->DATA_INICIO)->format('Y-m-d') !== $now->format('Y-m-d');
    }

    /**
     * Update Log
     **/
    private function updateDataLog(string $date): void
    {
        DB::connection('sqlsrv')
            ->table(env('DB_OWNER').'NEXTI_DATA_LOG_EMAIL')
            ->updateOrInsert(
            	['TIPO' => 'CEP'],
                ['DATA_INICIO' => $date]
            );
    }

    private function enviaEmail(array $emails, array $data): bool
    {
        $path = $this->generateXlsx($data);

        $mail = new PHPMailer();
        $mail->Port       = 587;
        $mail->SMTPSecure = 'tls';
        $mail->IsSMTP();
        $mail->Host       = 'outlook.office365.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'flex@resolv.com.br';
        $mail->Password   = 'Roy32189';
        $mail->From       = 'flex@resolv.com.br';
        $mail->CharSet    = 'utf-8';
        $mail->FromName   = 'Resolv';
        $mail->WordWrap   = 50;
        $mail->Body       = 'Em anexo Postos com cidade divergente';

        $mail->Subject    = 'Posto com Cidade Divergente - ' . date('d/m/Y');

        if($this->option('test')) {
            $emails = [
                'parcerias@flexsystems.com.br',
            ];
        }

        foreach($emails as $email) {
            $mail->AddAddress($email);
        }

        $mail->addAttachment($path, 'CEP_' . date('Y_m_d_H_i_s') . '.xlsx');

        if (!$mail->Send()) {
            $this->error("Erro: {$mail->ErrorInfo}");
            return false;
        }

        return true;
    }

    /**
     * Generate Xlsx
     */
    private function generateXlsx(array $data): string
    {
        $path = storage_path('app/public/logs/postos/cep-' . date('d_m_Y_H_i_s') . '.xlsx');
        $sheets = new SheetCollection($data);

        return (new FastExcel($sheets))->export($path);
    }
}
