<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaColaboradores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaColaboradores';

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
            ".env('DB_OWNER')."FLEX_COLABORADOR.*,
            ".env('DB_OWNER')."FLEX_EMPRESA.ID AS IDEMPRESA
            FROM ".env('DB_OWNER')."FLEX_COLABORADOR
            JOIN ".env('DB_OWNER')."FLEX_EMPRESA
                ON ".env('DB_OWNER')."FLEX_EMPRESA.CODFIL = ".env('DB_OWNER')."FLEX_COLABORADOR.CODFIL
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
        $observacao = date('d/m/Y H:i:s') . ' Registro criado/atualizado com sucesso';
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_COLABORADOR 
            SET ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_COLABORADOR.OBSERVACAO = '{$observacao}', 
            ".env('DB_OWNER')."FLEX_COLABORADOR.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO = '{$idexterno}'
        ");
    }

    private function atualizaRegistroComErro($idexterno, $situacao, $obs = '')
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."FLEX_COLABORADOR 
            SET ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO = {$situacao}, 
            ".env('DB_OWNER')."FLEX_COLABORADOR.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO = '{$idexterno}'
        ");
    }

    private function getColaboradoresNovos()
    {
        $where = 
        "  
            WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO IN(0,2)
            AND NOT EXISTS (
                SELECT * FROM ".env('DB_OWNER')."FLEX_TROCA_EMPRESA 
                WHERE ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.IDEXTERNO_NOVO = ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO
            )
        ";
        $colaboradores = DB::connection('sqlsrv')->select($this->query . $where);

        return $colaboradores;
    }

    private function getColaboradoresAtualizar()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO = 1 
            AND ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO IN(0,2)
        ";
        $colaboradores = DB::connection('sqlsrv')->select($this->query . $where);

        return $colaboradores;
    }

    private function getColaboradoresExcluir()
    {
        $where = 
        " 
            WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO = 3 
            AND ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO IN(0,2)
        ";
        
        $trocaPostos = DB::connection('sqlsrv')->select($this->query . $where);

        return $trocaPostos;
    }

    private function enviaColaboradoresNovos()
    {
        $this->info("Iniciando envio:");
        $colaboradores  = $this->getColaboradoresNovos();
        $nColaboradores = sizeof($colaboradores);
        foreach ($colaboradores as $colaborador) {
            $colaborador->CPF = str_replace(".","",$colaborador->CPF);
            $colaborador->CPF = str_replace("-","",$colaborador->CPF);

            $colaborador->RG = str_replace(".","",$colaborador->RG);
            $colaborador->RG = str_replace("-","",$colaborador->RG);

            $colaboradorNexti = [
                'externalId'            => rtrim($colaborador->IDEXTERNO),
                'companyId'             => intval($colaborador->IDEMPRESA),
                'enrolment'             => rtrim($colaborador->NUMCAD),
                'name'                  => rtrim($colaborador->NOMFUN),
                'zipCode'               => rtrim($colaborador->CEP),
                'address'               => rtrim($colaborador->ENDERECO),
                'complement'            => rtrim($colaborador->COMPLEMENTO),
                'houseNumber'           => rtrim($colaborador->NUMERO),
                'district'              => rtrim($colaborador->BAIRRO),
                'city'                  => rtrim($colaborador->CIDADE),
                'federatedUnitInitials' => rtrim($colaborador->ESTADO),
                'admissionDate'         => date('dmYHis', strtotime($colaborador->DATADMISSAO)),
                'birthDate'             => date('dmYHis', strtotime($colaborador->DATANASCIMENTO)),
                'externalCareerId'      => rtrim($colaborador->CARGO),
                'cpf'                   => str_pad($colaborador->CPF, 11, '0', STR_PAD_LEFT),
                'registerNumber'        => rtrim($colaborador->RG) == 0 ? null : rtrim($colaborador->RG),
                'pis'                   => rtrim($colaborador->PIS) == 0 ? null : rtrim($colaborador->PIS),
                'gender'                => rtrim($colaborador->GENERO),
                'phone'                 => rtrim($colaborador->TELEFONE) == '' ? NULL : rtrim($colaborador->TELEFONE),
                'fathersName'           => rtrim($colaborador->NOME_PAI) == '' ? NULL : rtrim($colaborador->NOME_PAI),
                'mothersName'           => rtrim($colaborador->NOME_MAE) == '' ? NULL : rtrim($colaborador->NOME_MAE),
                'ignoreTimeTracking'    => false,
                'ignoreValidation'      => false,
                'allowDevicePassword'   => false,
                'allowMobileClocking'   => false,
                'personSituationId'     => 1,
                'personTypeId'          => intval($colaborador->TIPCOL),
                //'scheduleId'            => 265345,
                //'rotationCode'          => 1,
                //'workplaceId'           => 890618,
                //'initialsRegisterNumber' => rtrim($colaborador->ESTADOEMISSOR),
                'publicArea' => rtrim($colaborador->TIPOEND),

            ];

            // echo json_encode($colaboradorNexti, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // var_dump($colaboradorNexti);exit;
            $ret = $this->enviaColaboradorNovo($colaboradorNexti);
            sleep(2);
        }
        $this->info("Total de {$nColaboradores} colaboradores(s) cadastrados!");
    }

    private function enviaColaboradorNovo($colaborador)
    {   
        $name = $colaborador['name'];
        $this->info("Enviando o colaborador {$name}");
        
        $response = $this->restClient->post("persons", [], [
            'json' => $colaborador
        ])->getResponse();

        //dd($response);exit;

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar o colaborador {$name} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($colaborador['externalId'], 2, $errors);
        } else {
            $this->info("Colaborador {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $colaboradorInserido = $responseData['value'];
            $id = $colaboradorInserido['id'];

            $this->atualizaRegistro($colaborador['externalId'], 1, $id);
        }
    }

    private function enviaColaboradoresAtualizarId()
    {
        $this->info("Iniciando atualização:");

        $colaboradores  = $this->getColaboradoresAtualizar();
        $nColaboradores = sizeof($colaboradores);
        foreach ($colaboradores as $colaborador) {
            
            $colaboradorNexti = [
                'externalId'            => rtrim($colaborador->IDEXTERNO),
                'companyId'             => intval($colaborador->IDEMPRESA),
                'enrolment'             => rtrim($colaborador->NUMCAD),
                'name'                  => rtrim($colaborador->NOMFUN),
                'zipCode'               => rtrim($colaborador->CEP),
                'address'               => rtrim($colaborador->ENDERECO),
                'complement'            => rtrim($colaborador->COMPLEMENTO),
                'houseNumber'           => rtrim($colaborador->NUMERO),
                'district'              => rtrim($colaborador->BAIRRO),
                'city'                  => rtrim($colaborador->CIDADE),
                'federatedUnitInitials' => rtrim($colaborador->ESTADO),
                'admissionDate'         => date('dmYHis', strtotime($colaborador->DATADMISSAO)),
                'birthDate'             => date('dmYHis', strtotime($colaborador->DATANASCIMENTO)),
                'externalCareerId'      => rtrim($colaborador->CARGO),
                'cpf'                   => str_pad($colaborador->CPF, 11, '0', STR_PAD_LEFT),
                'registerNumber'        => rtrim($colaborador->RG) == 0 ? null : rtrim($colaborador->RG),
                //'pis'                   => rtrim($colaborador->PIS) == 0 ? null : rtrim($colaborador->PIS),
                'gender'                => rtrim($colaborador->GENERO),
                'phone'                 => rtrim($colaborador->TELEFONE) == '' ? NULL : rtrim($colaborador->TELEFONE),
                'fathersName'           => rtrim($colaborador->NOME_PAI) == '' ? NULL : rtrim($colaborador->NOME_PAI),
                'mothersName'           => rtrim($colaborador->NOME_MAE) == '' ? NULL : rtrim($colaborador->NOME_MAE),
                'personTypeId'          => intval($colaborador->TIPCOL),
                'personSituationId'     => 1,
            ];

            // if(trim($colaborador->DATADEMISSAO) != ''){
            //     $colaboradorNexti['personSituationId'] = 3;
            //     $colaboradorNexti['demissionDate'] = date('dmY', strtotime($colaborador->DATADEMISSAO)) . '000000';
            // }

            //var_dump($colaboradorNexti);

            //var_dump(json_encode($colaboradorNexti, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $ret = $this->enviaColaboradorAtualizarId(rtrim($colaborador->IDEXTERNO), $colaboradorNexti, rtrim($colaborador->ID), rtrim($colaborador->IDEXTERNO));
        }

        $this->info("Total de {$nColaboradores} colaboradores(s) atualizados!");
    }

    private function enviaColaboradorAtualizarId($externalId, $colaborador, $idColab, $idExternoOld)
    {
        $this->info("Atualizando o colaborador {$externalId}");
        
        $endpoint = "persons/externalId/{$externalId}";
        // $endpoint = "persons/{$idColab}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $colaborador
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o colaborador {$externalId} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalId, 2, $errors);
        } else {
            $this->info("Colaborador {$externalId} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $colaboradorInserido = $responseData['value'];
            $id = $colaboradorInserido['id'];

            $this->atualizaRegistro($externalId, 1, $id);
        }
    }

    private function enviaColaboradoresExcluir()
    {
        $teste = "  
            SELECT * FROM ".env('DB_OWNER')."FLEX_COLABORADOR
            WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO IN (0,2)
            AND ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO = 3
        ";

        $colaboradores = DB::connection('sqlsrv')->select($teste);
        $nColaboradores = sizeof($colaboradores);

        $this->info("Iniciando exclusão de dados ({$nColaboradores} colaboradores)");

        foreach ($colaboradores as $colaborador) {
            $ret = $this->enviaColaboradorExcluir($colaborador->ID, $colaborador->NOMFUN, $colaborador->IDEXTERNO);
        }

        $this->info("Total de {$nColaboradores} colaborador(s) cadastrados!");
    }

    private function enviaColaboradorExcluir($id, $nomfum, $idExterno)
    {
        $this->info("Excluindo colaborador: {$nomfum} para colaborador");
        $response = $this->restClient->delete("persons/{$id}", [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao excluir o colaborador {$nomfum}: {$errors}");
            $this->atualizaRegistroComErro($idExterno, 2, $errors);
        } else {
            $this->info("Exclusão de colaborador: {$nomfum} enviada com sucesso.");
            $this->atualizaRegistro($idExterno, 1, $id);
        }
    }

    private function removerColaboradorTabela(){
        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."FLEX_COLABORADOR WHERE TIPO = 3 AND SITUACAO = 1
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function updateColaboradorJaExiste(){
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_COLABORADOR
            SET ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO = 1
            WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO IN(0,2)
            AND ".env('DB_OWNER')."FLEX_COLABORADOR.OBSERVACAO LIKE '%Já existe um registro salvo para o campo externalId com valor%'
            AND ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO = 0
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
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->enviaColaboradoresExcluir();
        $this->enviaColaboradoresAtualizarId();
        // $this->enviaColaboradoresAtualizarIdExterno();
        $this->enviaColaboradoresNovos();
        // $this->updateColaboradorJaExiste();
        // $this->removerColaboradorTabela();
    }
}
