<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaColaboradoresSanear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaColaboradoresSanear';

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

    private function getColaboradoresAtualizar()
    {
        // $query = 
        // "
        //     SELECT 
        //     (
        //         SELECT NEXTI_COLABORADOR_GERAL.DATADEMISSAO FROM NEXTI_COLABORADOR_GERAL
        //         WHERE NEXTI_COLABORADOR_GERAL.IDEXTERNO_OLD = NEXTI_COLABORADOR_AUX.EXTERNALID
        //     ) AS DATADEMISSAO,
        //     NEXTI_COLABORADOR_AUX.* 
        //     FROM NEXTI_COLABORADOR_AUX
        //     WHERE NEXTI_COLABORADOR_AUX.PERSONSITUATIONID <> 3
        //     AND NOT EXISTS(
        //         SELECT * FROM NEXTI_COLABORADOR
        //         WHERE NEXTI_COLABORADOR.IDEXTERNO = NEXTI_COLABORADOR_AUX.EXTERNALID
        //     )
        // ";

        $query = 
        "
            SELECT * FROM FLEX_COLABORADOR
            WHERE (FLEX_COLABORADOR.DATADEMISSAO = '' OR FLEX_COLABORADOR.DATADEMISSAO IS NULL)
            AND EXISTS(
                SELECT * FROM FLEX_COLABORADOR_AUX
                WHERE FLEX_COLABORADOR_AUX.ID = FLEX_COLABORADOR.ID
                AND FLEX_COLABORADOR_AUX.PERSONSITUATIONID = 3
            )
        ";

        $colaboradores = DB::connection('sqlsrv')->select($query);

        return $colaboradores;
    }

    private function enviaColaboradoresAtualizar()
    {
        $this->info("Iniciando atualização:");

        $colaboradores  = $this->getColaboradoresAtualizar();
        $nColaboradores = sizeof($colaboradores);
        foreach ($colaboradores as $colaborador) {
            
            // $colaboradorNexti = [
            //     'demissionDate' => $colaborador->DATADEMISSAO == null ? '01072024000000' : date('dmYHis', strtotime($colaborador->DATADEMISSAO)),
            //     'personSituationId' => 3
            // ];

            $colaboradorNexti = [
                'personSituationId' => 1
            ];

            // if($colaborador->PERSONSITUATIONID == 3){
            //     $colaboradorNexti['personSituationId'] = intval($colaborador->PERSONSITUATIONID);
            //     $colaboradorNexti['demissionDate'] = date('dmYHis');
            // }

            //var_dump($colaboradorNexti);

            //var_dump(json_encode($colaboradorNexti, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $ret = $this->enviaColaboradorAtualizar($colaborador, $colaboradorNexti);
            sleep(1.5);
        }

        $this->info("Total de {$nColaboradores} colaboradores(s) atualizados!");
    }

    private function enviaColaboradorAtualizar($colaborador, $colaboradorNexti)
    {
        $this->info("Atualizando o colaborador {$colaborador->NOMFUN}");
        
        ///$endpoint = "persons/externalId/{$externalId}";
        $endpoint = "persons/{$colaborador->ID}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $colaboradorNexti
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o colaborador {$colaborador->NOMFUN} na API Nexti: {$errors}");
        } else {
            $this->info("Colaborador {$colaborador->NOMFUN} atualizado com sucesso na API Nexti");
        }
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

        $this->enviaColaboradoresAtualizar();
    }
}
