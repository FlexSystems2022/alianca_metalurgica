<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\ViaCep\Facades\ViaCep;
use App\Services\ViaCep\Exceptions\CepException;
use Illuminate\Support\Facades\Log;

class ProcessaPostos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaPostos';

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
            ".env('DB_OWNER')."FLEX_POSTO.*,
            ".env('DB_OWNER')."FLEX_EMPRESA.ID AS IDEMPRESA,
            ".env('DB_OWNER')."FLEX_CLIENTE.ID AS IDCLIENTE
            FROM ".env('DB_OWNER')."FLEX_POSTO
            JOIN ".env('DB_OWNER')."FLEX_EMPRESA
                ON ".env('DB_OWNER')."FLEX_EMPRESA.NUMEMP = ".env('DB_OWNER')."FLEX_POSTO.NUMEMP
            JOIN ".env('DB_OWNER')."FLEX_CLIENTE
                ON ".env('DB_OWNER')."FLEX_CLIENTE.NUMEMP = ".env('DB_OWNER')."FLEX_POSTO.NUMEMP
                AND ".env('DB_OWNER')."FLEX_CLIENTE.CODOEM = ".env('DB_OWNER')."FLEX_POSTO.CODOEM
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

    private function atualizaRegistro($idexterno, $situacao, $id)
    {
        $obs = date('d/m/Y H:i:s') . ' - Registro criado/atualizado com sucesso';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_POSTO 
            SET ".env('DB_OWNER')."FLEX_POSTO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_POSTO.OBSERVACAO = '{$obs}', 
            ".env('DB_OWNER')."FLEX_POSTO.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_POSTO.IDEXTERNO = '{$idexterno}'
        ");
    }

    private function atualizaRegistroComErro($idexterno, $situacao, $obs = '')
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_POSTO 
            SET ".env('DB_OWNER')."FLEX_POSTO.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_POSTO.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_POSTO.IDEXTERNO = '{$idexterno}'
        ");
    }

    private function getPostosNovos()
    {
        $where = 
        "  
            WHERE ".env('DB_OWNER')."FLEX_POSTO.TIPO = 0
            AND ".env('DB_OWNER')."FLEX_POSTO.SITUACAO IN (0,2)
        ";

        $postos = DB::connection('sqlsrv')->select($this->query . $where);

        return $postos;
    }

    private function enviaPostosNovos()
    {
        $this->info("Iniciando envio:");
        $postos = $this->getPostosNovos();
        $nPostos = sizeof($postos);
        foreach ($postos as $posto) {
            $estpos = $posto->ESTPOS;
            $postra = $posto->POSTRA;
            $postoNexti = [
                'externalId' => $posto->IDEXTERNO,
                'name' => $posto->NUMEMP . ' - ' . $posto->DESPOS,
                'active' => true,
                'zipCode' => $posto->CEP,
                'address' => $posto->ENDERECO,
                'addressNumber' => $posto->NUMERO,
                'district' => $posto->BAIRRO,
                'clientId' => $posto->IDCLIENTE,
                'companyId' => $posto->IDEMPRESA,
                'costCenter' => $posto->CODCCU,
                'startDate' => date('dmYHis', strtotime($posto->DATCRI)),
                'startDate' => '01011990000000',
                'forceCompanyTransfer' => false,
                'serviceTypeId' => 8628,
                'service' => 'Padrão',
                'timezone' => $posto->TIMEZONE,
                'vacantJob' => intval($posto->VAGAS),
                'workPlaceNumber' => $posto->CODCCU,
                //'cityExternalId' => trim($posto->CEP) == '' ? null : $this->getCityExternalId($posto),
                'state' => $posto->ESTADO,
            ];

            // if($posto->IDUNIDADE != null && $posto->IDUNIDADE > 0){
            //     $postoNexti['businessUnitId'] = $posto->IDUNIDADE;
            // }else{
            //     $postoNexti['businessUnitId'] = 22875;
            // }

            $postoNexti['businessUnitId'] = 22875;

            $posto->DATEXT = trim($posto->DATEXT);
            $posto->DATEXT = $posto->DATEXT == '' ? $posto->DATCRI : $posto->DATEXT;

            //var_dump($posto->DATEXT);

            if($posto->CTT_BLOQ == 1){
                $posto->DATEXT = date('dmY', strtotime($posto->DATEXT));
                $postoNexti['finishDate'] = $posto->DATEXT . '000000';
                $postoNexti['active'] = false;
            }else{
                $postoNexti['finishDate'] = NULL;
            }

            //var_dump($postoNexti);exit;

            $ret = $this->enviaPostoNovo($postoNexti);
            sleep(2);
        }

        $this->info("Total de {$nPostos} postos(s) cadastrados!");
    }

    private function enviaPostoNovo($posto)
    {
        $name = $posto['name'];
        
        $this->info("Enviando o posto {$name}");
        
        $response = $this->restClient->post("workplaces", [], [
            'json' => $posto
        ])->getResponse();


        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o posto {$name} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($posto['externalId'], 2, $errors);
        } else {
            $this->info("Posto {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $postoInserido = $responseData['value'];
            $id = $postoInserido['id'];

            $this->atualizaRegistro($posto['externalId'], 1, $id);
        }
    }

    private function getPostosAtualizar()
    {
        $where = 
        "  
            WHERE ".env('DB_OWNER')."FLEX_POSTO.TIPO = 1 
            AND ".env('DB_OWNER')."FLEX_POSTO.SITUACAO IN(0,2)
        ";
        $postos = DB::connection('sqlsrv')->select($this->query . $where);

        return $postos;
    }

    private function enviaPostoAtualizar($externalId, $postoNexti, $posto)
    {
        $this->info("Atualizando o posto {$externalId}");

        $endpoint = "workplaces/externalId/{$externalId}/";
        //$endpoint = "workplaces/{$posto->ID}/";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $postoNexti, $posto
        ])->getResponse();
        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o posto {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);
        } else {
            $this->info("Posto {$externalId} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $postoInserido = $responseData['value'];
            $id = $postoInserido['id'];

            $this->atualizaRegistro($externalId, 1, $id);
        }
    }

    private function enviaPostosAtualizar()
    {
        $this->info("Iniciando atualização:");

        $postos = $this->getPostosAtualizar();
        $nPostos = sizeof($postos);

        foreach ($postos as $posto) {
            $postoNexti = [
                'externalId' => $posto->IDEXTERNO,
                'name' => $posto->NUMEMP . ' - ' . $posto->DESPOS,
                'active' => true,
                'zipCode' => $posto->CEP,
                'address' => $posto->ENDERECO,
                'addressNumber' => $posto->NUMERO,
                'district' => $posto->BAIRRO,
                'clientId' => $posto->IDCLIENTE,
                'companyId' => $posto->IDEMPRESA,
                'costCenter' => $posto->CODCCU,
                'startDate' => '01011990000000',
                'forceCompanyTransfer' => false,
                'service' => 'Padrão',
                'timezone' => $posto->TIMEZONE,
                'vacantJob' => intval($posto->VAGAS),
                'workPlaceNumber' => $posto->CODCCU,                
                'state' => $posto->ESTADO,
                //'cityExternalId' => trim($posto->CEP) == '' ? null : $this->getCityExternalId($posto)
            ];

            // if($posto->IDUNIDADE != null && $posto->IDUNIDADE > 0){
            //     $postoNexti['businessUnitId'] = $posto->IDUNIDADE;
            // }else{
            //     $postoNexti['businessUnitId'] = 22875;
            // }

            $postoNexti['businessUnitId'] = 22875;

            $posto->DATEXT = trim($posto->DATEXT);

            if($posto->CTT_BLOQ == 1){
                $posto->DATEXT = $posto->DATEXT == '' ? date('dmY',strtotime('-1 days')) : date('dmY', strtotime($posto->DATEXT));
                $postoNexti['finishDate'] = $posto->DATEXT . '000000';
                $postoNexti['active'] = false;
            }else{
                $postoNexti['finishDate'] = NULL;
            }

            $ret = $this->enviaPostoAtualizar($posto->IDEXTERNO, $postoNexti, $posto);
        }

        $this->info("Total de {$nPostos} postos(s) atualizados!");
    }

    private function insertLogs($msg, $dataInicio, $dataFim)
    {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',5), convert(datetime,'{$dataFim}',5), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    private function getCityExternalId(object $posto): ?string
    {
        try {
            if(!$posto->CEP) {
                return null;
            }

            $cep = ViaCep::cep()->get($posto->CEP);

            return $cep->ibge ?? null;
        } catch(CepException $e) {
            $this->warn($e->getMessage());

            return null;
        } catch(\Throwable $e) {
            $this->warn("Erro ao consultar o CEP [{$posto->CEP}] - {$e->getMessage()}");

            return null;
        }
    }

    private function getPostosDeletar()
    {
        $select = 
        "
            SELECT * FROM ".env('DB_OWNER')."FLEX_RET_POSTO
            WHERE NOT EXISTS(
                SELECT * FROM ".env('DB_OWNER')."FLEX_POSTO
                WHERE ".env('DB_OWNER')."FLEX_POSTO.ID = ".env('DB_OWNER')."FLEX_RET_POSTO.ID
            )
        ";

        $postos = DB::connection('sqlsrv')->select($select);

        return $postos;
    }

    private function deletaPosto($externalId, $idPosto)
    {
        $this->info("Deletando o posto {$externalId}");
        // dd($externalId);
        //$endpoint = "workplaces/externalid/{$externalId}";
        $endpoint = "workplaces/{$idPosto}";
        $response = $this->restClient->delete($endpoint)->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao deletar o posto {$externalId} na API Nexti: {$errors}");

        } else {
            $this->info("Posto {$externalId} deletado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
        }
    }

    private function deletaPostos()
    {
        $this->info("Deletando:");

        $postos = $this->getPostosDeletar();
        $nPostos = sizeof($postos);

        foreach ($postos as $posto) {

            //var_dump($postoNexti);exit;

            $ret = $this->deletaPosto($posto->EXTERNALID, $posto->ID);
        }

        $this->info("Total de {$nPostos} postos(s) atualizados!");
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

        $this->deletaPostos();
        $this->enviaPostosAtualizar();
        $this->enviaPostosNovos();
    }
}
