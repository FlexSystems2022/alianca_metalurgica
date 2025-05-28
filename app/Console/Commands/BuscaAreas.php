<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaAreas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaAreas';

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
        $this->query = "";
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

    private function buscaAreas()
    {   
        $response = $this->restClient->get("/areas/all", ['size' => 1000])->getResponse();
        if (!$this->restClient->isResponseStatusCode(200)) 
        {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar areas: {$errors}");
        } 
        else 
        {
            $responseData = $this->restClient->getResponseData();

            $this->limpaTabelas();

            foreach($responseData['content'] as $content){
                $this->salvarArea($content);
            }
        }
    }

    private function limpaTabelas(){
        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."NEXTI_RET_AREA
        ";

        DB::connection('sqlsrv')->statement($query);

        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."NEXTI_RET_AREA_POSTO
        ";

        DB::connection('sqlsrv')->statement($query);

        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."NEXTI_RET_AREA_SUPERVISOR
        ";

        DB::connection('sqlsrv')->statement($query);

        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."NEXTI_AREA_POSTOS
        ";

        DB::connection('sqlsrv')->statement($query);

        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."NEXTI_AREA_SUPERVISOR
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function salvarArea($dados){
        $dados['active'] = $dados['active']??true;
        $dados['externalId'] = $dados['externalId']??'0';
        $dados['code'] = $dados['code']??'0';
        $arrayArea = [
            'ID'                   => $dados['id'],
            'EXTERNALID'           => $dados['externalId'],
            'NAME'                 => $dados['name'],
            'CODE'                 => $dados['code'],
            'PERSONRESPONSIBLEID'  => $dados['personResponsibleId'],
            'ACTIVE'               => $dados['active'] == true ? 1 : 0
        ];

        DB::connection("sqlsrv")
        ->table(env('DB_OWNER')."NEXTI_RET_AREA")
        ->insert($arrayArea);

        if(isset($dados['personSupervisorIds'])){
            foreach($dados['personSupervisorIds'] as $supervisor){
                $arraySupervisor = [
                    'ID_AREA' =>  $dados['id'],
                    'ID_SUPERVISOR' => $supervisor,
                ];

                DB::connection('sqlsrv')
                ->table(env('DB_OWNER')."NEXTI_RET_AREA_SUPERVISOR")
                ->insert($arraySupervisor);
            }
        }

        if(isset($dados['workplaceIds'])){
            foreach($dados['workplaceIds'] as $posto){
                $arrayPostos = [
                    'ID_AREA' =>  $dados['id'],
                    'ID_POSTO' => $posto,
                ];

                DB::connection('sqlsrv')
                ->table(env('DB_OWNER')."NEXTI_RET_AREA_POSTO")
                ->insert($arrayPostos);
            }
        }

        $this->info("Area {$dados['name']} Cadastrada Com Sucesso!");
    }

    private function inserePostosSupervisorAreas(){
        $query = 
        "
            INSERT INTO ".env('DB_OWNER')."NEXTI_AREA_POSTOS
            SELECT 
            ".env('DB_OWNER')."NEXTI_RET_AREA_POSTO.ID_AREA AS CADRES,
            ".env('DB_OWNER')."NEXTI_POSTO.IDEXTERNO AS IDEXTERNOPOSTO,
            ".env('DB_OWNER')."NEXTI_POSTO.ID AS IDPOSTO,
            0 AS SITUACAO,
            0 AS TIPO,
            'A' AS ATIVO 
            FROM ".env('DB_OWNER')."NEXTI_RET_AREA_POSTO
            JOIN ".env('DB_OWNER')."NEXTI_POSTO
                ON ".env('DB_OWNER')."NEXTI_POSTO.ID = ".env('DB_OWNER')."NEXTI_RET_AREA_POSTO.ID_POSTO
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."NEXTI_AREA
                WHERE ".env('DB_OWNER')."NEXTI_AREA.ID = ".env('DB_OWNER')."NEXTI_RET_AREA_POSTO.ID_AREA
            )
        ";

        DB::connection('sqlsrv')->statement($query);

        $query = 
        "
            INSERT INTO ".env('DB_OWNER')."NEXTI_AREA_SUPERVISOR
            SELECT 
            ".env('DB_OWNER')."NEXTI_RET_AREA_SUPERVISOR.ID_AREA AS CADRES,
            ".env('DB_OWNER')."NEXTI_RET_AREA_SUPERVISOR.ID_SUPERVISOR AS SUPERVISOR
            FROM ".env('DB_OWNER')."NEXTI_RET_AREA_SUPERVISOR
            WHERE EXISTS(
                SELECT * FROM ".env('DB_OWNER')."NEXTI_AREA
                WHERE ".env('DB_OWNER')."NEXTI_AREA.ID = ".env('DB_OWNER')."NEXTI_RET_AREA_SUPERVISOR.ID_AREA
            )
        ";

        DB::connection('sqlsrv')->statement($query);
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

        $this->buscaAreas();
        $this->inserePostosSupervisorAreas();
    }
}