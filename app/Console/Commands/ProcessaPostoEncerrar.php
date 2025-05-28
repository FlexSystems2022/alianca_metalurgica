<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaPostoEncerrar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaPostoEncerrar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private $restClient = null;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');
        parent::__construct();
        $this->query = 
        "
            SELECT * FROM ".env('DB_OWNER')."NEXTI_POSTO
        ";
    }

    private function getResponseErrors()
    {
        $responseData = $this->restClient->getResponseData();
        
        $message = $responseData['message'] ?? '';
        $comments = '';
        if (isset($responseData['comments']) && sizeof($responseData['comments']) > 0) {
            $comments = $responseData['comments'][0];
        }
        
        if ($comments != '') {
            $message = $comments;
        }

        return $message;
    }

    private function getResponseValue()
    {
        $responseData = $this->restClient->getResponseData();
        return $responseData;
    }

    private function atualizaRegistro($idexterno, $situacao, $id)
    {
        $obs = date('d/m/Y H:i:s') . ' - Registro criado/atualizado com sucesso';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_POSTO 
            SET ".env('DB_OWNER')."NEXTI_POSTO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."NEXTI_POSTO.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."NEXTI_POSTO.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."NEXTI_POSTO.EXTERNALID = '{$idexterno}'
        ");
    }

    private function atualizaRegistroComErro($idexterno, $situacao, $obs = '')
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_POSTO 
            SET ".env('DB_OWNER')."NEXTI_POSTO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."NEXTI_POSTO.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."NEXTI_POSTO.EXTERNALID = '{$idexterno}'
        ");
    }

    private function getPostosDeletar()
    {
        $select = 
        "
            SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_POSTO
            WHERE NOT EXISTS(
                SELECT * FROM ".env('DB_OWNER')."NEXTI_POSTO
                WHERE ".env('DB_OWNER')."NEXTI_POSTO.ID = ".env('DB_OWNER')."NEXTI_RET_POSTO.ID
            )
        ";

        $postos = DB::connection('sqlsrv')->select($select);

        return $postos;
    }

    private function deletaPosto($externalId, $idPosto)
    {
        $this->info("Deletando o posto {$externalId}");
        // dd($externalId);
        //$endpoint = "workplaces/externalid/{$externalId}";
        $endpoint = "workplaces/{$idPosto}";
        $response = $this->restClient->delete($endpoint)->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao deletar o posto {$externalId} na API Nexti: {$errors}");

        } else {
            $this->info("Posto {$externalId} deletado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
        }
    }

    private function deletaPostos()
    {
        $this->info("Deletando:");

        $postos = $this->getPostosDeletar();
        $nPostos = sizeof($postos);

        foreach ($postos as $posto) {

            //var_dump($postoNexti);exit;

            $ret = $this->deletaPosto($posto->EXTERNALID, $posto->ID);
        }

        $this->info("Total de {$nPostos} postos(s) atualizados!");
    }

    private function getPostosAtualizar()
    {
        $select = 
        "
            SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_POSTO
            WHERE NOT EXISTS(
                SELECT * FROM ".env('DB_OWNER')."NEXTI_POSTO
                WHERE ".env('DB_OWNER')."NEXTI_POSTO.IDEXTERNO = ".env('DB_OWNER')."NEXTI_RET_POSTO.EXTERNALID
            )
            AND ".env('DB_OWNER')."NEXTI_RET_POSTO.FINISHDATE = '0'
        ";

        $postos = DB::connection('sqlsrv')->select($select);

        return $postos;
    }

    private function enviaPostosAtualizar()
    {
        $this->info("Iniciando atualização:");

        $postos = $this->getPostosAtualizar();
        $nPostos = sizeof($postos);

        foreach ($postos as $posto) {
            $postoNexti = [
                'name' => 'INATIVO - ' . $posto->NAME,
                'finishDate' => '01072024000000',
                'active' => false
            ];

            //var_dump($postoNexti);

            $ret = $this->enviaPostoAtualizar($posto->ID, $postoNexti);
        }

        $this->info("Total de {$nPostos} postos(s) atualizados!");
    }

    private function enviaPostoAtualizar($externalId, $posto)
    {
        $this->info("Atualizando o posto {$externalId}");

        $endpoint = "workplaces/{$externalId}/";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $posto
        ])->getResponse();
        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o posto {$externalId} na API Nexti: {$errors}");
        } else {
            $this->info("Posto {$externalId} atualizado com sucesso na API Nexti");
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dataInicio = date('y-m-d H:i:s A');
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        //$this->deletaPostos();
        $this->enviaPostosAtualizar();
    }
}
