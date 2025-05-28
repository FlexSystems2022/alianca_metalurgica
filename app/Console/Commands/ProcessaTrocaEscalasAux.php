<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helper\LogColaborador;

class ProcessaTrocaEscalasAux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTrocaEscalasAux';

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

    private function getTrocaEscalasExcluir()
    {
        $query = 
        " 
            SELECT * FROM ".env('DB_OWNER')."FLEX_RET_TROCA_ESCALAS
            WHERE SCHEDULEID = 265345
            AND TIPO = 0
        ";
        $trocaEscalas = DB::connection('sqlsrv')->select($query);

        return $trocaEscalas;
    }


    private function processaTrocaEscalasExcluir()
    {
        $trocaEscalas = $this->getTrocaEscalasExcluir();
        $nTrocaEscalas = sizeof($trocaEscalas);
        $this->info("Iniciando exclusão de dados ({$nTrocaEscalas} trocas de escalas)");

        foreach ($trocaEscalas as $trocaEscala) {
            $trocaEscalaNexti = [
                'id' => $trocaEscala->ID
            ];
            $ret = $this->enviaTrocaEscalaExcluir($trocaEscalaNexti, $trocaEscala);
            //sleep(2);
        }
        $this->info("Total de {$nTrocaEscalas} trocaEscala(s) cadastrados!");
    }

    private function enviaTrocaEscalaExcluir($trocaEscala, $trocaOriginal)
    {
        $scheduleExternalId = $trocaEscala['id'];
        $idCol = $trocaEscala['id'];

        $this->info("Excluindo troca de escala: {$scheduleExternalId} para colaborador {$idCol}");

        $response = $this->restClient->delete("scheduletransfers/" . $trocaEscala['id'], [], [])->getResponse();
        
        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema troca de escala: {$scheduleExternalId} para colaborador {$idCol}: {$errors}");
        } else {
            $this->info("Exclusão de troca de escala: {$scheduleExternalId} para colaborador {$idCol} enviada com sucesso.");
            $responseData = $this->restClient->getResponseData();
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dataInicio = date('Y-m-d H:i:s A');
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->processaTrocaEscalasExcluir();
    }
}
