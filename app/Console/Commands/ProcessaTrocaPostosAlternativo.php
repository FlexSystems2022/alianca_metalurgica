<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helper\LogColaborador;

class ProcessaTrocaPostosAlternativo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTrocaPostosAlternativo';

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

    private function getTrocaPostosExcluir()
    {
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS
            WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.WORKPLACEID = 890618
            AND ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.TIPO = 0
        ";
        
        $trocaPostos = DB::connection('sqlsrv')->select($query);

        return $trocaPostos;
    }

    private function enviaTrocaPostosExcluir()
    {
        $trocaPostos = $this->getTrocaPostosExcluir();
        $nTrocaPostos = sizeof($trocaPostos);
        $this->info("Iniciando exclusão de dados ({$nTrocaPostos} trocas de postos)");

        foreach ($trocaPostos as $trocaPosto) {
            $ret = $this->enviaTrocaPostoExcluir($trocaPosto);
        }

        $this->info("Total de {$nTrocaPostos} trocaPosto(s) cadastrados!");
    }

    private function enviaTrocaPostoExcluir($trocaPosto)
    {
        
        $this->info("Excluindo troca de posto");
        $response = $this->restClient->delete("workplacetransfers/{$trocaPosto->ID}", [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema troca de posto: {$errors}");
        } else {
            $this->info("Exclusão de troca de posto enviada com sucesso.");
            $this->removeItemBanco($trocaPosto->ID);
        }
    }

    private function removeItemBanco($id)
    {
        $sql = "DELETE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS WHERE ".env('DB_OWNER')."FLEX_RET_TROCA_POSTOS.ID = {$id}";
        DB::connection('sqlsrv')->statement($sql);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->enviaTrocaPostosExcluir();
    }
}