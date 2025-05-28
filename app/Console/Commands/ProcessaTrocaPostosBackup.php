<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helper\LogColaborador;

class ProcessaTrocaPostosBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTrocaPostosBackup';

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
            SELECT * FROM NEXTI_RET_TROCA_POSTOS
            WHERE PERSONEXTERNALID = '73813'
            AND TIPO = 0
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

    private function getTrocaPostosNovos()
    {        
        return DB::connection('sqlsrv')->select($this->query);
    }

    private function enviaTrocaPostosNovos()
    {
        $trocaPostos = $this->getTrocaPostosNovos();
        $nTrocaPostos = sizeof($trocaPostos);
        $this->info("Iniciando envio de dados ({$nTrocaPostos} postos)");

        foreach ($trocaPostos as $trocaPosto) {
            $trocaPostoNexti = [
                'transferDateTime' => date('dmYHis', strtotime($trocaPosto->TRANSFERDATETIME)),
                'personExternalId' => '76856',
                'workplaceId' => $trocaPosto->WORKPLACEID
            ];

            $ret = $this->enviaTrocaPostoNovo($trocaPostoNexti, $trocaPosto);
            sleep(2);
        }
        $this->info("Total de {$nTrocaPostos} trocaPosto(s) cadastrados!");
    }

    private function enviaTrocaPostoNovo($trocaPosto, $trocaOriginal)
    {
        $this->info("Enviando troca de posto para colaborador");

        $response = $this->restClient->post("workplacetransfers", [], [
            'json' => $trocaPosto
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema troca de posto para colaborador: {$errors}");
        } else {
            $this->info("Troca de posto para colaborador enviada com sucesso.");
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->enviaTrocaPostosNovos();
    }
}