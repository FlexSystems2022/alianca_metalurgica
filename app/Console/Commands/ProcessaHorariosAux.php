<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaHorariosAux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaHorariosAux';

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
            SELECT * FROM ".env('DB_OWNER')."NEXTI_RET_HORARIO
            WHERE NOT EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_HORARIO
                WHERE ".env('DB_OWNER')."FLEX_HORARIO.IDEXTERNO = ".env('DB_OWNER')."NEXTI_RET_HORARIO.IDEXTERNO
            )
        ";
    }

    private function getResponseErrors() {
        $responseData = $this->restClient->getResponseData();
       
        $message = $responseData['message']??'';
        $comments = '';
        if(isset($responseData['comments']) && sizeof($responseData['comments']) > 0) {
            $comments = $responseData['comments'][0];
        }
        
        if($comments != '') 
            $message = $comments;

        return $message;
    }

    private function getResponseValue() {
        $responseData = $this->restClient->getResponseData();
        return $responseData;
    }

    private function getHorariosAtualizar()
    {
        $where = "AND 1=1";
        $horarios = DB::connection('sqlsrv')->select($this->query . $where);

        return $horarios;
    }

    function horaParaMinutos($hora){
        $partes = explode(":", $hora);
        $partes[1] = substr($partes[1], 0, 2);
        $minutos = $partes[0]*60+$partes[1];
        return ($minutos);
    }

    private function enviaHorariosAtualizar() {
        $this->info("Inicializando atualização:");
        $horarios = $this->getHorariosAtualizar();
        $nHorarios = sizeof($horarios);

        foreach ($horarios as $horario) {

            $horarioNexti = [
                'active' => false,
                'name' => 'INATIVO(Não Usar) - ' . $horario->NAME
            ];

            //var_dump($horarioNexti);exit;
            // exit;
            
            $ret = $this->enviaHorarioAtualizar($horario->IDEXTERNO,$horarioNexti, $horario->ID);
        }

        $this->info("Total de {$nHorarios} horarios(s) atualizados!");
    }


    private function enviaHorarioAtualizar($externalId, $horario, $idHorario) {
        $this->info("Atualizando o horario {$externalId} via API Nexti");
        
        $endpoint = "shifts/externalId/{$externalId}";

        // var_dump($externalId);
        // var_dump(json_encode($horario));exit;
        $response = $this->restClient->put($endpoint, [], [
            'json' => $horario
        ])->getResponse();

        //print_r($response);

        if (!$this->restClient->isResponseStatusCode(200)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o horario {$externalId} na API Nexti: {$errors}");

        } else {
            $this->info("Horario {$externalId} atualizado com sucesso na API Nexti");
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

        $this->enviaHorariosAtualizar();
    }
}
