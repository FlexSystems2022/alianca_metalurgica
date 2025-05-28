<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaTiposServicos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaTiposServicos';

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
            SELECT 
            *
            FROM ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO
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

    private function getResponseValue() {
        $responseData = $this->restClient->getResponseData();
        return $responseData;
    }

    private function getTipoServicoNovos()
    {   
        $where = 
        " 
            WHERE ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.TIPO = 0 
            AND ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.SITUACAO IN(0,2)
        ";
        $tipoServicos = DB::connection('sqlsrv')->select($this->query . $where);

        return $tipoServicos;
    }

    private function getCargosAtualizar()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.TIPO = 1 
            AND ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.SITUACAO IN(0,2)
        ";
        $tipoServicos = DB::connection('sqlsrv')->select($this->query . $where);

        return $tipoServicos;
    }

    private function enviaTiposServicosNovos() {
        $this->info("Iniciando envio de dados:");
        $tipoServicos = $this->getTipoServicoNovos();
        $nCargos = sizeof($tipoServicos);
        
        foreach ($tipoServicos as $tipoServico) {
            $tipoServicoNexti = [
                'name'          => $tipoServico->NAME,
                'externalId'    => $tipoServico->EXTERNALID
            ];
            $ret = $this->enviaTipoServicoNovo($tipoServicoNexti);
        }
        $this->info("Total de {$nCargos} tipo servico(s) cadastrados!");
    }

    private function enviaTipoServicoNovo($tipoServico) {
        $name = $tipoServico['name'];
        $this->info("Enviando o tipo servico {$name} para API Nexti");

        $response = $this->restClient->post("servicetypes", [], [
            'json' => $tipoServico
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o tipo servico {$name} para API Nexti: {$errors}");
            $this->atualizaRegistroComErro($tipoServico['externalId'], 2, $errors);
        } else {
            $this->info("Cargo {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $tipoServicoInserido = $responseData['value'];
            $id = $tipoServicoInserido['id'];

            $this->atualizaRegistro($tipoServico['externalId'], 1, $id);
        }
    }

    private function enviaTiposServicosAtualizar() {
        $this->info("Iniciando atualizações:");
        $tipoServicos = $this->getCargosAtualizar();
        $nCargos = sizeof($tipoServicos);
        
        foreach ($tipoServicos as $tipoServico) {
            $tipoServicoNexti = [
                'name' => $tipoServico->NAME,
            ];
            $ret = $this->enviaTipoServicoAtualizar($tipoServico->EXTERNALID, $tipoServicoNexti);
        }
        $this->info("Total de {$nCargos} tipo servico(s) atualizados!");
    }

    private function enviaTipoServicoAtualizar($externalId, $tipoServico) {
        $this->info("Atualizando o cargo {$externalId} via API Nexti");

        $endpoint = "servicetypes/externalId/{$externalId}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $tipoServico
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {

            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o tipo servico {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);

        } else {
            $this->info("tipo de serviço {$externalId} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $tipoServicoInserido = $responseData['value'];
            $id = $tipoServicoInserido['id'];

            $this->atualizaRegistro($externalId, 1, $id);
        }
    }

    private function atualizaRegistro($idexterno, $situacao, $id) {
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO 
            SET ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.OBSERVACAO = '', 
            ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.ID = '{$id}' 
            WHERE ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.EXTERNALID = '{$idexterno}'
        ");
    } 

    private function atualizaRegistroComErro($idexterno, $situacao, $obs = '') {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO 
            SET ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER').".dbo.NEXTI_TIPO_SERVICO.EXTERNALID = '{$idexterno}'
        ");
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

        $this->enviaTiposServicosNovos();
        $this->enviaTiposServicosAtualizar();
    }
}
