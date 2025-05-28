<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaColaboradoresLimpeza extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaColaboradoresLimpeza';

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

    private function getColaboradoresAtualizar()
    {
        $where = 
        " 
            SELECT * FROM  ".env('DB_OWNER').".dbo.NEXTI_COLABORADOR_AUX
            WHERE NOT EXISTS(
                SELECT * FROM  ".env('DB_OWNER').".dbo.NEXTI_COLABORADOR
                WHERE  ".env('DB_OWNER').".dbo.NEXTI_COLABORADOR.EXTERNALID =  ".env('DB_OWNER').".dbo.NEXTI_COLABORADOR_AUX.EXTERNALID
            )
        ";
        $colaboradores = DB::connection('sqlsrv')->select($this->query . $where);

        return $colaboradores;
    }

    private function enviaColaboradoresAtualizar()
    {
        $this->info("Iniciando atualização:");

        $colaboradores  = $this->getColaboradoresAtualizar();
        $nColaboradores = sizeof($colaboradores);
        foreach ($colaboradores as $colaborador) {
            $colaboradorNexti = [
                'demissionDate' => '28082023000000',
                'personSituationId' => 3,
            ];

            //var_dump($colaboradorNexti);

            $ret = $this->enviaColaboradorAtualizar($colaborador->EXTERNALID, $colaboradorNexti, $colaborador->ID);
            sleep(2);
        }

        $this->info("Total de {$nColaboradores} colaboradores(s) atualizados!");
    }

    private function enviaColaboradorAtualizar($externalId, $colaborador, $idColab)
    {
        $this->info("Atualizando o colaborador {$externalId}");
        
        $endpoint = "persons/externalId/{$externalId}";
        //$endpoint = "persons/{$idColab}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $colaborador
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o colaborador {$externalId} na API Nexti: {$errors}");
        } else {
            $this->info("Colaborador {$externalId} atualizado com sucesso na API Nexti");
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

        $this->enviaColaboradoresAtualizar();
    }
}
