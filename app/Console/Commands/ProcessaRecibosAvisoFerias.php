<?php

namespace App\Console\Commands;

use DateTime;
use Carbon\Carbon;
use iio\libmergepdf\Merger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessaRecibosAvisoFerias extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ProcessaRecibosAvisoFerias';

    /**
     * The console command description.
     */
    protected $description = 'Command description';

    private $restClient = null;

    private $query = '';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');

        parent::__construct();

        $this->query = "
            SELECT 
                NEXTI_AVISOS_FERIAS.*,
                NEXTI_COLABORADOR.ID AS IDCOLABORADOR,
                NEXTI_COLABORADOR.NUMCAD AS MATRICULA,
                NEXTI_COLABORADOR.NOMFUN AS COLABORADOR
            FROM ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR
                ON(NEXTI_COLABORADOR.NUMEMP = NEXTI_AVISOS_FERIAS.NUMEMP
                    AND NEXTI_COLABORADOR.CODFIL = NEXTI_AVISOS_FERIAS.CODFIL
                    AND NEXTI_COLABORADOR.TIPCOL = NEXTI_AVISOS_FERIAS.TIPCOL
                    AND NEXTI_COLABORADOR.NUMCAD = NEXTI_AVISOS_FERIAS.NUMCAD
                )
            WHERE NEXTI_AVISOS_FERIAS.NOME = 'Aviso de Férias'
        ";
    }

    private function getResponseErrors() {
        $responseData = $this->restClient->getResponseData();
       
        $message = $responseData['message'] ?? '';
        $comments = '';
        if(isset($responseData['comments']) && sizeof($responseData['comments']) > 0) {
            $comments = $responseData['comments'][0];
        }
        
        if($comments != '') 
            $message = $comments;

        return $message;
    }

    private function getInformesNovos(): array
    {   
        $where = "  
            AND NEXTI_AVISOS_FERIAS.TIPO = 0 
            AND (
                NEXTI_AVISOS_FERIAS.ARQUIVO <> '0'
                AND NEXTI_AVISOS_FERIAS.ARQUIVO <> ''
            )
            AND NEXTI_AVISOS_FERIAS.SITUACAO IN (0,2)
            AND NEXTI_AVISOS_FERIAS.DATAPGTO <= CONVERT(DATE, GETDATE(), 120)
        ";

        // $where = "  
        //     AND NEXTI_AVISOS_FERIAS.NUMCAD = '058235'
        // ";

        return DB::connection('sqlsrv')->select($this->query . $where);
    }

    private function getRecibo(object $aviso): ?object
    {   
        $query = "
            SELECT 
                NEXTI_AVISOS_FERIAS.*,
                NEXTI_COLABORADOR.ID AS IDCOLABORADOR,
                NEXTI_COLABORADOR.NUMCAD AS MATRICULA,
                NEXTI_COLABORADOR.NOMFUN AS COLABORADOR
            FROM ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR
                ON(NEXTI_COLABORADOR.NUMEMP = NEXTI_AVISOS_FERIAS.NUMEMP
                    AND NEXTI_COLABORADOR.CODFIL = NEXTI_AVISOS_FERIAS.CODFIL
                    AND NEXTI_COLABORADOR.TIPCOL = NEXTI_AVISOS_FERIAS.TIPCOL
                    AND NEXTI_COLABORADOR.NUMCAD = NEXTI_AVISOS_FERIAS.NUMCAD
                )
            WHERE NEXTI_AVISOS_FERIAS.NOME = 'Recibo de Férias'
            AND NEXTI_AVISOS_FERIAS.TIPO = 0 
            AND NEXTI_AVISOS_FERIAS.ARQUIVO <> '0'
            AND NEXTI_AVISOS_FERIAS.STARTDATE = CONVERT(DATE, '{$aviso->STARTDATE}', 120)
            AND NEXTI_AVISOS_FERIAS.FINISHDATE = CONVERT(DATE, '{$aviso->FINISHDATE}', 120)
            AND NEXTI_AVISOS_FERIAS.NUMEMP = '{$aviso->NUMEMP}'
            AND NEXTI_AVISOS_FERIAS.CODFIL = '{$aviso->CODFIL}'
            AND NEXTI_AVISOS_FERIAS.TIPCOL = '{$aviso->TIPCOL}'
            AND NEXTI_AVISOS_FERIAS.NUMCAD = '{$aviso->NUMCAD}'
        ";

        return DB::connection('sqlsrv')->selectOne($query);
    }

    private function enviaInformesNovos(): void
    {
        $this->info("Iniciando envio de dados:");

        $informes = $this->getInformesNovos();

        $nInformes = 0;
        foreach ($informes as $informe) {
            $this->info("Iniciando envio de dados: {$informe->NUMCAD}");
            $iniAqui = Carbon::parse($informe->STARTDATE_AQUI)->format('d/m/Y');
            $fimAqui = Carbon::parse($informe->FINISHDATE_AQUI)->format('d/m/Y');
            $iniGozo = Carbon::parse($informe->STARTDATE)->format('d/m/Y');
            $fimGozo = Carbon::parse($informe->FINISHDATE)->format('d/m/Y');
            $retornoFerias = Carbon::parse($informe->FINISHDATE)->addDays(1)->format('d/m/Y');

            $mensagem = 
            " Nos termos da legislação vigente, suas férias serão concedidas conforme o demonstrativo abaixo: Período Aquisitivo: {$iniAqui} A {$fimAqui} Período de Gozo: {$iniGozo} A {$fimGozo}  Retorno ao Trabalho: {$retornoFerias}";

            $notices = [
                'finishDate'            => date('dmY', strtotime("+90days")).'235959',
                'name'                  => "{$informe->NOME} - {$informe->COLABORADOR}",
                'noticeCollectorId'     => 3,
                'noticeShowId'          => 3,
                'noticeStatusId'        => 1,
                'noticeTypeId'          => 2,
                'persons'               => [intval($informe->IDCOLABORADOR)], 
                'startDate'             => date('dmY') . '000001',
                'text'                  => $mensagem,
            ];

            $merger = new Merger;

            $pdfContent = base64_decode($informe->ARQUIVO);
            if($this->is_json($pdfContent)) {
                $this->error("Conteudo informe não é valido: {$informe->COLABORADOR}");
                continue;
            }

            $merger->addRaw($pdfContent);

            $recibo = $this->getRecibo($informe);

            if($recibo) {
                $pdfContentRecibo = base64_decode($recibo->ARQUIVO);
                if(!$this->is_json($pdfContent)) {
                    $merger->addRaw($pdfContentRecibo);
                } else {
                    $recibo = null;
                }
            }

            $filePath = base_path('tempAvisos/documento.pdf');

            file_put_contents($filePath, $merger->merge());

            if(file_exists($filePath)) {
                $informesNexti = [
                    'attachment' => fopen($filePath, 'r'),
                    'noticeJson' => strval(json_encode($notices))
                ];
                
                if($this->enviaInformeNovo($informesNexti, $informe, $recibo)) {
                    $nInformes++;
                }
            }else{
                $erro = "Não foi encontrado Informe: {$informe->COLABORADOR}";

                $this->error($erro);

                $this->atualizaRegistroComErro(
                    $informe,
                    2, 
                    $erro
                );
            }

            unlink($filePath);
            sleep(2);
        }

        $this->info("Total de {$nInformes} informe(s) informesNexti!");
    }

    private function enviaInformeNovo(array $informesNexti, object $informe, ?object $recibo=null): bool
    {
        $this->info("Enviando o informe {$informe->COLABORADOR} para API Nexti");

        $response = $this->restClient->postMultipartSimple("notices/attachment", $informesNexti);
        if (!$response->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();

            $this->info("Problema ao enviar o informe {$informe->COLABORADOR} para API Nexti: {$errors}");

            $this->atualizaRegistroComErro($informe, 2, $errors);

            return false;
        }
        
        $this->info("Informe {$informe->COLABORADOR} enviado com sucesso para API Nexti");
    
        $responseData = $response->getResponseData();
        $informeInserido = $responseData['value'];
        $id = $informeInserido['id'];

        $this->atualizaRegistro($informe, 1, $id);
        if($recibo) {
            $this->atualizaRegistroRecibo($recibo, 1, $id);
        }

        return true;
    }

    private function atualizaRegistro(object $informe, int $situacao, string $id): void
    {
        $obs = date('d/m/Y H:i:s') . ' - Enviado com Sucesso';

        DB::connection('sqlsrv')
            ->table(env('DB_OWNER')."NEXTI_AVISOS_FERIAS")
            ->where('NUMEMP', $informe->NUMEMP)
            ->where('CODFIL', $informe->CODFIL)
            ->where('NUMCAD', $informe->NUMCAD)
            ->where('STARTDATE', $informe->STARTDATE)
            ->where('FINISHDATE', $informe->FINISHDATE)
            ->where('NOME', 'Aviso de Férias')
            ->update([
                'SITUACAO' => $situacao,
                'OBSERVACAO' => $obs,
                'ID' => $id
            ]);
    }

    private function atualizaRegistroRecibo(object $recibo, int $situacao, string $id): void
    {
        $obs = date('d/m/Y H:i:s') . ' - Enviado com Sucesso';

        DB::connection('sqlsrv')
            ->table(env('DB_OWNER')."NEXTI_AVISOS_FERIAS")
            ->where('NUMEMP', $recibo->NUMEMP)
            ->where('CODFIL', $recibo->CODFIL)
            ->where('NUMCAD', $recibo->NUMCAD)
            ->where('STARTDATE', $recibo->STARTDATE)
            ->where('FINISHDATE', $recibo->FINISHDATE)
            ->where('NOME', 'Recibo de Férias')
            ->update([
                'SITUACAO' => $situacao,
                'OBSERVACAO' => $obs,
                'ID' => $id
            ]);
    }

    private function atualizaRegistroComErro(object $informe, int $situacao, string $obs = ''): void
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;

        DB::connection('sqlsrv')
            ->table(env('DB_OWNER')."NEXTI_AVISOS_FERIAS")
            ->where('NUMEMP', $informe->NUMEMP)
            ->where('CODFIL', $informe->CODFIL)
            ->where('NUMCAD', $informe->NUMCAD)
            ->where('STARTDATE', $informe->STARTDATE)
            ->where('FINISHDATE', $informe->FINISHDATE)
            ->where('NOME', 'Aviso de Férias')
            ->update([
                'SITUACAO' => $situacao,
                'OBSERVACAO' => $obs,
            ]);
    } 

    private function buscaAvisosExcluir(){
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS
            WHERE ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS.TIPO = 3
            AND ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS.SITUACAO IN(0,2)
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function enviaInformesExcluir(){
        $avisos = $this->buscaAvisosExcluir();

        if($avisos){
            foreach($avisos as $aviso){
                $this->enviaExcluir($aviso);
            }
        }
    }

    private function enviaExcluir($aviso){
        $this->info("Enviando o informe {$aviso->ID} para API Nexti");

        $response = $this->restClient->put("notices/" . $aviso->ID, []);
        if (!$response->isResponseStatusCode(201) && !$response->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();

            $this->info("Problema ao enviar o informe {$aviso->ID} para API Nexti: {$errors}");

            $this->atualizaRegistroComErroExcluir($aviso, $errors);

            return false;
        }
        
        $this->info("Informe {$aviso->ID} excluído com sucesso para API Nexti");

        $this->atualizaRegistroReciboExcluir($aviso);

        return true;
    }

    private function atualizaRegistroReciboExcluir(object $aviso): void
    {
        $obs = date('d/m/Y H:i:s') . ' - Excluído com Sucesso';

        DB::connection('sqlsrv')
            ->table(env('DB_OWNER')."NEXTI_AVISOS_FERIAS")
            ->where('ID', $aviso->ID)
            ->update([
                'SITUACAO' => 1,
                'OBSERVACAO' => $obs
            ]);
    }

    private function atualizaRegistroComErroExcluir(object $aviso, string $obs): void
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;

        DB::connection('sqlsrv')
            ->table(env('DB_OWNER')."NEXTI_AVISOS_FERIAS")
            ->where('ID', $aviso->ID)
            ->update([
                'SITUACAO' => 2,
                'OBSERVACAO' => $obs
            ]);
    } 

    private function limparTabela(){
        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS
            WHERE ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS.TIPO = 3
            AND ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS.SITUACAO = 1
        ";

        return DB::connection('sqlsrv')->statement($query);
    }

    private function reativaGeracaoDocComprometido(){
        $query = 
        "
            UPDATE ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS
            SET ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS.SITUACAO = 0,
            ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS.ARQUIVO ='0'
            WHERE ".env('DB_OWNER')."NEXTI_AVISOS_FERIAS.STARTDATE >= '20240901'
            AND SUBSTRING(".env('DB_OWNER')."NEXTI_AVISOS_FERIAS.ARQUIVO, 1,2) = 'ey'
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->enviaInformesExcluir();
        $this->limparTabela();
        $this->enviaInformesNovos();
        $this->reativaGeracaoDocComprometido();
    }

    private function is_json(?string $str=null): bool
    {
        try {
            $str = trim($str) ?? null;
            if(!$str) {
                return false;
            }

            $json = json_decode($str);

            return $json && $str != $json;
        } catch(\Throwable $e) {
            return false;
        }
    }
}