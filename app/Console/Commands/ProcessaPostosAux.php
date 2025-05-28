<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaPostosAux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaPostosAux';

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
            SELECT * FROM ".env('DB_OWNER').".dbo.NEXTI_POSTO_AUX2
        ";
    }

    private function getResponseErrors()
    {
        $responseData = $this->restClient->getResponseData();
        
        $message = $responseData['message']??'';
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

    private function getPostosExcluir()
    {
        $where = 
        "  
            WHERE ".env('DB_OWNER').".dbo.NEXTI_POSTO_AUX2.SITUACAO < 999
        ";
        $postos = DB::connection('sqlsrv')->select($this->query . $where);

        return $postos;
    }

    private function enviaPostoExcluir($externalId)
    {
        $this->info("Atualizando o posto {$externalId}");

        $endpoint = "workplaces/externalid/{$externalId}";

        $response = $this->restClient->delete($endpoint, [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao excluir o posto {$externalId} na API Nexti: {$errors}");
        } else {
            $this->info("Posto {$externalId} excluído com sucesso na API Nexti");

            $this->excluirPostoTabela($externalId);
        }
    }

    private function excluirPostoTabela($externalId){
        $query = 
        "
            UPDATE FROM ".env('DB_OWNER').".dbo.NEXTI_POSTO_AUX2 SET SITUACAO = 999
            WHERE ".env('DB_OWNER').".dbo.NEXTI_POSTO_AUX2.EXTERNALID = '{$externalId}'
        ";
    }

    private function enviaPostosExcluir()
    {
        $this->info("Iniciando atualização:");

        $postos = $this->getPostosExcluir();
        $nPostos = sizeof($postos);

        $count = 1;
        foreach ($postos as $posto) {
            var_dump($count++);
            $ret = $this->enviaPostoExcluir($posto->EXTERNALID);
        }

        $this->info("Total de {$nPostos} postos(s) atualizados!");
    }

    private function getPostosAtualizar()
    {
        $where = 
        "  
            WHERE 1=1
        ";
        $postos = DB::connection('sqlsrv')->select($this->query . $where);

        return $postos;
    }

    private function enviaPostoAtualizar($externalId, $posto)
    {
        $this->info("Atualizando o posto {$externalId}");

        $endpoint = "workplaces/externalId/{$externalId}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $posto
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o posto {$externalId} na API Nexti: {$errors}");

            //$this->atualizaRegistroComErro($externalId, 2, $errors);
        } else {
            $this->info("Posto {$externalId} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $postoInserido = $responseData['value'];
            $id = $postoInserido['id'];

            //$this->atualizaRegistro($externalId, 1, $id);
        }
    }

    private function enviaPostosAtualizar()
    {
        $this->info("Iniciando atualização:");

        $postos = $this->getPostosAtualizar();
        $nPostos = sizeof($postos);

        foreach ($postos as $posto) {
            $postoNexti = [
                'name' => $posto->NAME,
                'costCenter' => $posto->COSTCENTER,
                'startDate' => date('dmYHis', strtotime($posto->STARTDATE)),
                'vacantJob' => intval($posto->VACANTJOB),
            ];

            $postoNexti['finishDate'] = date('dmYHis', strtotime($posto->STARTDATE));

            //var_dump($postoNexti);exit;

            $ret = $this->enviaPostoAtualizar($posto->EXTERNALID, $postoNexti);
        }

        $this->info("Total de {$nPostos} postos(s) atualizados!");
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

        $this->enviaPostosAtualizar();
        $dataFim = date('y-m-d H:i:s A');

        //$this->insertLogs("Postos foram enviados para o Nexti.", $dataInicio, $dataFim);
    }
}
