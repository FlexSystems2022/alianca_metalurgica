<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaHorariosNexti extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'BuscaHorariosNexti';

    /**
     * The console command description.
     */
    protected $description = 'Command description';

    private $restClient = null;

    /**
     * Create a new command instance.
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

    private function buscaHorario(){
        $this->getHorarioRecursiva(0);
    }

    private function getHorarioRecursiva($page)
    {
        $response = $this->restClient->get("shifts/all", [
            'page' => $page,
            'size' => 10000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar horarios: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            if ($responseData) {
                $content = $responseData['content'];
                if ($page == 0) {
                    $this->info("Armazenando {$responseData['totalElements']} registros...");
                }

                $this->info("API Nexti retornou os resultados: Pagina {$page}.");

                if (sizeof($content) > 0) {

                    foreach ($content as $reg) {
                        $reg['id'] = $reg['id']??0;
                        $reg['externalId'] = $reg['externalId']??0;
                        $reg['name'] = $reg['name']??0;
                        $reg['shiftTypeId'] = $reg['shiftTypeId']??0;
                        $reg['active'] = $reg['active'] == true ? 1 : 0;

                        $existe = $this->verificaExiste($reg);

                        if(!$existe){
                            $this->addHorario($reg);
                        }else{
                            $this->atualizaHorario($reg);
                         }
                    }

                    $this->getHorarioRecursiva($page + 1);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function verificaExiste($reg){
        return DB::table('NEXTI_RET_HORARIO')
        	->where('ID', $reg['id'])
        	->get()
        	->toArray();
    }

    private function addHorario($reg)
    {
        DB::table('NEXTI_RET_HORARIO')
        	->insert([
				'ID' => $reg['id'],
				'IDEXTERNO' => $reg['externalId'],
				'NAME' => $reg['name'],
				'SHIFTTYPEID' => $reg['shiftTypeId'],
				'SITUACAO' => $reg['active'] == 1 ? 0 : 3,
				'ACTIVE' => $reg['active']
        	]);

        $this->info("Horario {$reg['name']} Inserido com Sucesso!");
    }

    public function atualizaHorario($reg)
    {
        DB::table('NEXTI_RET_HORARIO')
	        ->where('ID', $reg['id'])
	        ->update([
	            'IDEXTERNO' => $reg['externalId'],
	            'NAME' => $reg['name'], 
	            'SHIFTTYPEID' => $reg['shiftTypeId'],
                'SITUACAO' => $reg['active'] == 1 ? 0 : 3,
	            'ACTIVE' => $reg['active']
	        ]);

        $this->info("Horario {$reg['name']} Atualizado com Sucesso!");
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

        $this->buscaHorario();
    }
}