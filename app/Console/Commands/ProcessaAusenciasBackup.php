<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helper\LogColaborador;

class ProcessaAusenciasBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaAusenciasBackup';

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
            SELECT * FROM NEXTI_RET_AUSENCIAS
            WHERE PERSONEXTERNALID = '73813'
            AND TIPO = 0

        ";
    }

    private function getAusenciaNovos()
    {
        return DB::connection('sqlsrv')->select($this->query);
    }

    private function enviarAusenciasNovas()
    {
        $ausencias = $this->getAusenciaNovos();
        $nAusencias = sizeof($ausencias);
        $this->info("Iniciando envio de dados ({$nAusencias} ausências)");
        
        foreach ($ausencias as $itemAusencia) {
            $arrayAusencia = array(
                "absenceSituationId"            => intval($itemAusencia->ABSENCESITUATIONID),
                "startDateTime"                 => date("dmY000000", strtotime($itemAusencia->STARTDATETIME)),
                "startMinute"                   => intval($itemAusencia->STARTMINUTE) == 0 ? null : intval($itemAusencia->STARTMINUTE),
                "personExternalId"              => '76856',
                "finishDateTime"                => date("dmY000000", strtotime($itemAusencia->FINISHDATETIME)),
                "finishMinute"                  => intval($itemAusencia->FINISHMINUTE) == 0 ? null : intval($itemAusencia->FINISHMINUTE)
            );

            $ret = $this->enviaAusencia($arrayAusencia, $itemAusencia);
            sleep(1);
        }

        $this->info("Total de {$nAusencias} ausencia(s) cadastrados!");
    }

    private function enviaAusencia($array, $itemAusencia)
    {
        $response = $this->restClient->post("absences", [], [
            'json' => $array
        ])->getResponse();

        $responseData = $this->restClient->getResponseData();
        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema na ausência para colaborador: {$errors}");
        } else {
            $this->info("Ausencia para colaborador enviada com sucesso.");
        }
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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->enviarAusenciasNovas();
    }
}
