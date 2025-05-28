<?php

namespace App\Console\Commands\Protheus;

use Illuminate\Support\Facades\DB;
use App\Shared\Commands\CommandNexti;
use Carbon\Carbon;

class ProcessaFolhaPagamento extends CommandNexti
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ProcessaFolhaPagamento';

    /**
     * The console command description.
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');

        parent::__construct();
    }

    private function atualizaRegistro(object $folha, string $id): void
    {
        DB::connection('mysql')
            ->table('nexti_protheus_folha_pagamento')
            ->where('TIPCOL', $folha->TIPCOL)
            ->where('NUMEMP', $folha->NUMEMP)
            ->where('CODFIL', $folha->CODFIL)
            ->where('NUMCAD', $folha->NUMCAD)
            ->where('PERIODO', $folha->PERIODO)
            ->where('TIPOFOLHA', $folha->TIPOFOLHA)
            ->where('PARCELA', $folha->PARCELA)
            ->where('VERBA', $folha->VERBA)
            ->update([
                'SITUACAO' => 1, 
                'OBSERVACAO' => date('d/m/Y H:i:s') . ' Registro criado/atualizado com sucesso',
                'ID' => $id,
                'DATA_ENVIO' => now()->format('Y-m-d H:i:s')
            ]);
    }

    private function atualizaRegistroComErro(object $folha, string $obs): void
    {
        DB::connection('mysql')
            ->table('nexti_protheus_folha_pagamento')
            ->where('TIPCOL', $folha->TIPCOL)
            ->where('NUMEMP', $folha->NUMEMP)
            ->where('CODFIL', $folha->CODFIL)
            ->where('NUMCAD', $folha->NUMCAD)
            ->where('PERIODO', $folha->PERIODO)
            ->where('TIPOFOLHA', $folha->TIPOFOLHA)
            ->where('PARCELA', $folha->PARCELA)
            ->where('VERBA', $folha->VERBA)
            ->update([
                'SITUACAO' => 2, 
                'OBSERVACAO' => date('d/m/Y H:i:s') . ' - ' . $obs 
            ]);
    }

    private function getFolhaPagamentoNovos(): array
    {
        $query = "
            SELECT
                nexti_empresa.CNPJ AS CNPJ_EMPRESA,
                nexti_protheus_folha_pagamento.*
            FROM nexti_protheus_folha_pagamento
            JOIN nexti_empresa
                ON(nexti_empresa.NUMEMP = nexti_protheus_folha_pagamento.NUMEMP
                    AND nexti_empresa.CODFIL = nexti_protheus_folha_pagamento.CODFIL
                )
            WHERE nexti_protheus_folha_pagamento.TIPO = 0
            AND nexti_protheus_folha_pagamento.SITUACAO IN(0, 2)
        ";

        return DB::connection('mysql')->select($query);
    }

    private function enviaFolhaPagamentoNovos(): void
    {
        $this->info('Iniciando envio: ');
        $folhas  = $this->getFolhaPagamentoNovos();

        $nFolhaPagamento = 0;
        foreach ($folhas as $folha) {
            $result = $this->enviaFolhaPagamentoNovo($folha);
            if($result) {
                $nFolhaPagamento++;
            }
        }

        $this->info("Total de {$nFolhaPagamento} Folha(s) cadastrados!");
    }

    private function enviaFolhaPagamentoNovo(object $folha): bool
    {
        $data = [
            'empresa' => $folha->CNPJ_EMPRESA,
            'matricula' => $folha->NUMCAD,
            'periodo' => Carbon::parse($folha->PERIODO)->format('Ym'),
            'verba' => $folha->VERBA,
            'tipo' => $folha->VERBA == 640 || $folha->VERBA == 641 ? 'D' : $folha->TIPOFOLHA,
            'valor' => floatval($folha->VALOR),
            'parcela' => intval($folha->PARCELA == 0 ? 1 : $folha->PARCELA), 
            'hora' => floatval($folha->HORAS)
        ];

        $this->info("Enviando o Folha {$data['matricula']} - {$data['verba']}");

        $response = $this->enviaFolhaProtheus($data);
        if(isset($response['errorCode'])) {
            $errors = data_get($response, 'errorMessage', 'Erro ao enviar o registro');

            $this->atualizaRegistroComErro($folha, $errors);

            $this->error("Folha {$data['matricula']} - {$data['verba']}: {$errors}");
            return false;
        }

        if(isset($response['code']) && $response['code'] === 500) {
            $errors = data_get($response, 'message', 'Erro ao enviar o registro');

            $this->atualizaRegistroComErro($folha, $errors);

            $this->error("Folha {$data['matricula']} - {$data['verba']}: {$errors}");
            return false;
        }

        if(data_get($response, 'resultado') !== 'Lancamento Inserido com Sucesso!') {
            $errors = data_get($response, 'resultado', 'Erro ao enviar o registro');

            $this->atualizaRegistroComErro($folha, $errors);

            $this->error("Folha {$data['matricula']} - {$data['verba']}: {$errors}");
            return false;
        }

        $this->info("Folha {$data['matricula']} - {$data['verba']} enviada com sucesso para API Nexti");
        
        $id = $response['id']??0;

        $this->atualizaRegistro($folha, $id);

        return true;
    }
    
    private function enviaFolhaProtheus(array $data): mixed
    {
        var_dump(json_encode($data));
        $curl = curl_init();

        $senhaEncode = base64_encode(env('USER_PROTHEUS') . ':' . env('PASSWORD_PROTHEUS'));

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://ecolimpsistemas166219.protheus.cloudtotvs.com.br:2307/rest/WebLancFolha/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $senhaEncode
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode(mb_convert_encoding($response, 'UTF-8', 'UTF-8'), JSON_THROW_ON_ERROR);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->enviaFolhaPagamentoNovos();
    }
}
