<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaColaboradoresAux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaColaboradoresAux';

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
     * @return voID
     */
    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');
        parent::__construct();
        $this->query = "
        SELECT 
            ".env('DB_OWNER')."FLEX_COLABORADOR.*,
            ".env('DB_OWNER')."FLEX_EMPRESA.ID AS IDEMPRESA
            FROM ".env('DB_OWNER')."FLEX_COLABORADOR
            JOIN ".env('DB_OWNER')."FLEX_EMPRESA
                ON ".env('DB_OWNER')."FLEX_EMPRESA.NUMEMP = ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP
        ";
    }

    private function getResponseErrors()
    {
        $responseData = $this->restClient->getResponseData();
        
        $message = isset($responseData['message'])? $responseData['message'] : '';
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

    private function atualizaRegistro($IDEXTERNO, $situacao, $ID)
    {
        $observacao = date('d/m/Y H:i:s') . ' Registro criado/atualizado com sucesso';
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER')."FLEX_COLABORADOR SET ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO = {$situacao}, ".env('DB_OWNER')."FLEX_COLABORADOR.OBSERVACAO = '{$observacao}', ".env('DB_OWNER')."FLEX_COLABORADOR.ID = '{$ID}' WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO = '{$IDEXTERNO}'");
    }

    private function atualizaRegistroComErro($IDEXTERNO, $situacao, $obs = '')
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;
        DB::connection('sqlsrv')->statement("UPDATE ".env('DB_OWNER')."FLEX_COLABORADOR SET ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO = {$situacao}, ".env('DB_OWNER')."FLEX_COLABORADOR.OBSERVACAO = '{$obs}' WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.IDEXTERNO = '{$IDEXTERNO}'");
    }

    private function getColaboradoresNovos()
    {
        $where = 
        "  
            WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO = 0 
            AND ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO IN (0,2)
            AND NOT EXISTS (SELECT * FROM ".env('DB_OWNER')."FLEX_TROCA_EMPRESA 
            WHERE ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.EMPATU = ".env('DB_OWNER')."FLEX_COLABORADOR.NUMEMP
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.TIPCOL = ".env('DB_OWNER')."FLEX_COLABORADOR.TIPCOL
            AND ".env('DB_OWNER')."FLEX_TROCA_EMPRESA.CADATU = ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD)
        ";

        $colaboradores = DB::connection('sqlsrv')->select($this->query . $where);
        return $colaboradores;
    }

    private function getColaboradoresAtualizar()
    {
        $where = " WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO = 1 
                    AND ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO IN (0,2)
                     
                ";
                //AND ".env('DB_OWNER')."FLEX_COLABORADOR.NUMCAD = 2262
        $colaboradores = DB::connection('sqlsrv')->select($this->query . $where);

        return $colaboradores;
    }

    private function enviaColaboradoresNovos()
    {
        $this->info("Iniciando envio:");
        $colaboradores  = $this->getColaboradoresNovos();

        $nColaboradores = sizeof($colaboradores);   
        foreach ($colaboradores as $colaborador) {

            $colaboradorNexti = [
                'externalID'            => rtrim($colaborador->IDEXTERNO),
                'companyID'             => intval($colaborador->IDEMPRESA),
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
                'externalCareerID'      => rtrim($colaborador->CARGO),
                'cpf'                   => str_pad($colaborador->CPF, 11, '0', STR_PAD_LEFT),
                'registerNumber'        => rtrim($colaborador->RG) == 0 ? null : rtrim($colaborador->RG),
                'pis'                   => rtrim($colaborador->PIS) == 0 ? null : rtrim($colaborador->PIS),
                'gender'                => rtrim($colaborador->GENERO),
                'phone'                 => rtrim($colaborador->TELEFONE) == '' ? NULL : rtrim($colaborador->TELEFONE),
                'fathersName'           => rtrim($colaborador->NOME_PAI) == '' ? NULL : rtrim($colaborador->NOME_PAI),
                'mothersName'           => rtrim($colaborador->NOME_MAE) == '' ? NULL : rtrim($colaborador->NOME_MAE),
                'ignoreTimeTracking'    => false,
                'ignoreValIDation'      => false,
                'allowDevicePassword'   => false,
                'allowMobileClocking'   => false,
                'personSituationID'     => 1,
                'personTypeID'          => intval($colaborador->TIPCOL),
                //'scheduleID'            => 265345,
                //'rotationCode'          => 1,
                //'workplaceID'           => 890618,
                //'initialsRegisterNumber' => rtrim($colaborador->ESTADOEMISSOR),
                'publicArea' => rtrim($colaborador->TIPOEND),
            ];
            $ret = $this->enviaColaboradorNovo($colaboradorNexti, $colaborador, $colaborador->ID);
            sleep(2);
        }
        $this->info("Total de {$nColaboradores} colaboradores(s) cadastrados!");
    }

    private function enviaColaboradorNovo($colaborador, $colaboradorArray, $idColaborador)
    {  
        $name = $colaborador['name'];
        $this->info("Enviando o colaborador {$name}");
        $response = $this->restClient->post("persons", [], [
            'json' => $colaborador
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            if ($this->restClient->isResponseStatusCode(412)) {
                $errors = 'Falha nos campos obrigatórios, verificar! ' . $this->getResponseErrors();
            } else {
                $errors = $this->getResponseErrors();
                $this->info("Problema ao enviar o colaborador {$name} para API Nexti: {$errors}");
            }

            $this->atualizaRegistroComErro($colaborador['externalID'], 2, $errors);
        } else {
            $this->info("Colaborador {$name} enviado com sucesso para API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $colaboradorInserIDo = $responseData['value'];
            $ID = $colaboradorInserIDo['ID'];

            $this->atualizaRegistro($colaborador['externalID'], 1, $ID);
        }
    }

    private function enviaColaboradoresAtualizar()
    {
        $this->info("Iniciando atualização:");

        $colaboradores  = $this->getColaboradoresAtualizar();
        $nColaboradores = sizeof($colaboradores);

        foreach ($colaboradores as $colaborador) {
            $colaboradorNexti = [
                'externalID'            => rtrim($colaborador->IDEXTERNO),
                'companyID'             => intval($colaborador->IDEMPRESA),
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
                'externalCareerID'      => rtrim($colaborador->CARGO),
                'cpf'                   => str_pad($colaborador->CPF, 11, '0', STR_PAD_LEFT),
                'registerNumber'        => rtrim($colaborador->RG) == 0 ? null : rtrim($colaborador->RG),
                //'pis'                   => rtrim($colaborador->PIS) == 0 ? null : rtrim($colaborador->PIS),
                'gender'                => rtrim($colaborador->GENERO),
                'phone'                 => rtrim($colaborador->TELEFONE) == '' ? NULL : rtrim($colaborador->TELEFONE),
                'fathersName'           => rtrim($colaborador->NOME_PAI) == '' ? NULL : rtrim($colaborador->NOME_PAI),
                'mothersName'           => rtrim($colaborador->NOME_MAE) == '' ? NULL : rtrim($colaborador->NOME_MAE),
                'personTypeID'          => intval($colaborador->TIPCOL),
                'personSituationID'     => 1,
            ];

            $ret = $this->enviaColaboradorAtualizar($colaborador->IDEXTERNO, $colaboradorNexti, $colaborador->ID);
        }

        $this->info("Total de {$nColaboradores} colaboradores(s) atualizados!");
    }

    private function enviaColaboradorAtualizar($externalID, $colaborador, $IDColaborador)
    {
        //$aviso = $this->buscaMensagemAviso($colaborador, $IDColaborador);

        $this->info("Atualizando o colaborador {$externalID}");
        
        //var_dump($externalID);
       // var_dump(json_encode($colaborador));
        $endpoint = "persons/externalID/{$externalID}";
        // $endpoint = "persons/{$IDColaborador}";
        $response = $this->restClient->put($endpoint, [], [
            'json' => $colaborador
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200) && !$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao atualizar o colaborador {$externalID} na API Nexti: {$errors}");

            $this->atualizaRegistroComErro($externalID, 2, $errors);
        } else {
            $this->info("Colaborador {$externalID} atualizado com sucesso na API Nexti");
            
            $responseData = $this->restClient->getResponseData();
            $colaboradorInserIDo = $responseData['value'];
            $ID = $colaboradorInserIDo['ID'];

            $this->atualizaRegistro($externalID, 1, $ID);
        }
    }

    private function enviaColaboradoresExcluir()
    {
        $teste = "  SELECT * FROM ".env('DB_OWNER')."FLEX_COLABORADOR
                    WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO IN (0,2)
                    AND ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO = 3";

        $colaboradores = DB::connection('sqlsrv')->select($teste);
        $nColaboradores = sizeof($colaboradores);

        //var_dump($nColaboradores);exit;
        $this->info("Iniciando exclusão de dados ({$nColaboradores} trocas de postos)");

        foreach ($colaboradores as $colaborador) {
            $ret = $this->enviaColaboradorExcluir($colaborador->ID, $colaborador->NOMFUN, $colaborador->IDEXTERNO);
            //sleep(2);
        }

        $this->info("Total de {$nColaboradores} colaborador(s) cadastrados!");
    }

    private function enviaColaboradorExcluir($ID, $nomfum, $IDEXTERNO)
    {
        $this->info("Excluindo colaborador: {$nomfum} para colaborador");
        $response = $this->restClient->delete("persons/{$ID}", [], [])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201) && !$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao excluir o colaborador {$nomfum}: {$errors}");
            $this->atualizaRegistroComErro($IDEXTERNO, 2, $errors);
        } else {
            $this->info("Exclusão de colaborador: {$nomfum} enviada com sucesso.");
            $this->atualizaRegistro($IDEXTERNO, 1, $ID);
        }
    }

    private function getColaboradorRecursivo($page = 0)
    {
        $response = $this->restClient->get("persons/all", [
            'page' => $page,
            'size' => 100000,
        ])->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar colaboradores: {$errors}");
        } else {
            $responseData = $this->restClient->getResponseData();
            if ($responseData) {
                $content = $responseData['content'];
                if ($page == 0) {
                    $this->info("Armazendo {$responseData['totalElements']} registros...");
                }

                $this->info("API Nexti retornou os resultados: Pagina {$page}.");
                //var_dump(sizeof($content));exit;
                if (sizeof($content) > 0) {
                    foreach ($content as $reg) {
                        $this->addColaborador($reg);
                    }
                }

                // Chamada recursiva para carregar proxima pagina
                $totalPages = $responseData['totalPages'];
                if ($page < $totalPages) {
                    $this->getColaboradorRecursivo($page + 1);
                }
            } else {
                $this->info("Não foi possível ler a resposta da consulta.");
            }
        }
    }

    private function atualizaColaboradoresSemID(){
        $query = 
        "
            UPDATE ".env('DB_OWNER')."FLEX_COLABORADOR
            SET ".env('DB_OWNER')."FLEX_COLABORADOR.TIPO = 1,
            ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO = 0
            WHERE ".env('DB_OWNER')."FLEX_COLABORADOR.ID = 0
            AND ".env('DB_OWNER')."FLEX_COLABORADOR.SITUACAO = 1
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function insertLogs($msg, $dataInicio, $dataFim) {
        $sql = "INSERT INTO ".env('DB_OWNER')."FLEX_LOG (DATA_INICIO, DATA_FIM, MSG)
                VALUES (convert(datetime,'{$dataInicio}',5), convert(datetime,'{$dataFim}',5), '{$msg}')";
        
        DB::connection('sqlsrv')->statement($sql);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->restClient = new \App\Shared\ProvIDer\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->atualizaColaboradoresSemID();
        $this->enviaColaboradoresExcluir();
        $retColaboradoresAtualizar = $this->enviaColaboradoresAtualizar();
        $retColaboradoresNovos = $this->enviaColaboradoresNovos();
    }
}
