<?php

namespace App\Console\Commands;

use DateTime;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessaSancoes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaSancoes';

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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if(in_array(Carbon::now()->dayOfWeek, [0, 6])) {
            $this->warn('Sem envio por hoje');
            return;
        }

        $this->restClient = new \Someline\Rest\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->gerarNovasSancoes();
        $this->enviaAdvertencia();
        $this->enviaSuspensao();
    }

    /**
     * Returna Carbon com o dia da semana equivalente.
     **/
    private function getDataAfastamentos(int $dayOfTheWeek): array
    {
        $weekMap = [
            1 => [ // Segunda
                'start' => 'previous wednesday',
                'finish' => 'previous friday',
            ],
            2 => [ // Terça
                'start' => 'previous saturday',
                'finish' => 'previous saturday',
            ],
            3 => [ // Quarta
                'start' => 'previous sunday',
                'finish' => 'previous sunday',
            ],
            4 => [ // Quinta
                'start' => 'previous monday',
                'finish' => 'previous monday',
            ],
            5 => [ // Sexta
                'start' => 'previous tuesday',
                'finish' => 'previous tuesday',
            ]
        ];

        $days = $weekMap[$dayOfTheWeek] ?? null;
        if(!$days) {
            throw new \Exception('Invalid Day Week');
        }

        return [
            'start' => Carbon::now()->change($days['start']),
            'finish' => Carbon::now()->change($days['finish']),
        ];
    }
    
    private function gerarNovasSancoes(): void
    {
        $today = Carbon::today();

        $dates = $this->getDataAfastamentos(Carbon::now()->dayOfWeek);

        $query = "
            INSERT INTO ".env('DB_OWNER')."NEXTI_SANCAO
            SELECT
                0 AS ID,
                NEXTI_RET_AUSENCIAS.ID AS ID_AUSENCIA,
                NEXTI_COLABORADOR.NUMEMP,
                NEXTI_COLABORADOR.TIPCOL,
                NEXTI_COLABORADOR.NUMCAD,
                IIF(
                    (
                        SELECT
                            COUNT(1)
                        FROM ".env('DB_OWNER')."NEXTI_SANCAO 
                        WHERE NEXTI_SANCAO.NUMEMP = NEXTI_COLABORADOR.NUMEMP
                        AND NEXTI_SANCAO.TIPCOL = NEXTI_COLABORADOR.TIPCOL
                        AND NEXTI_SANCAO.NUMCAD = NEXTI_COLABORADOR.NUMCAD
                        AND DATEDIFF(DAY, NEXTI_SANCAO.STARTDATE, GETDATE()) < 90
                        AND NEXTI_SANCAO.NOME = 'Advertencia'
                    ) >= 1,
                    'Suspensao',
                    'Advertencia'
                ) AS NOME,
                NEXTI_RET_AUSENCIAS.STARTDATETIME AS STARTDATE,
                NULL AS FINISHDATE,
                NEXTI_RET_AUSENCIAS.ABSENCESITUATIONID AS ABSENCESITUATIONID,
                0 AS TIPO,
                0 AS SITUACAO,
                NULL AS OBSERVACAO,
                NULL AS DATA_ENVIO_NEXTI,
                GETDATE() AS DATA_INSERCAO,
                (
                    SELECT 
                        TOP(1) CONVERT(INT, NEXTI_RET_TROCA_POSTOS.WORKPLACEID)
                    FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS
                    WHERE NEXTI_RET_TROCA_POSTOS.TRANSFERDATETIME = (
                        SELECT
                            MAX(TP.TRANSFERDATETIME)
                        FROM ".env('DB_OWNER')."NEXTI_RET_TROCA_POSTOS TP
                        WHERE TP.PERSONEXTERNALID = NEXTI_RET_TROCA_POSTOS.PERSONEXTERNALID
                        AND TP.REMOVED = 0
                    )
                    AND NEXTI_RET_TROCA_POSTOS.PERSONEXTERNALID = NEXTI_COLABORADOR.IDEXTERNO
                ) AS POSTO
            FROM ".env('DB_OWNER')."NEXTI_RET_AUSENCIAS
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR
                ON (NEXTI_COLABORADOR.IDEXTERNO = NEXTI_RET_AUSENCIAS.PERSONEXTERNALID
                    AND EXISTS(
                        SELECT
                            1
                        FROM ".env('DB_OWNER')."NEXTI_EMPRESA
                        WHERE NEXTI_EMPRESA.NUMEMP = NEXTI_COLABORADOR.NUMEMP
                        AND NEXTI_EMPRESA.CODFIL = NEXTI_COLABORADOR.CODFIL
                        AND NEXTI_EMPRESA.GERA_SANCOES = 1
                    )
                )
            WHERE NEXTI_RET_AUSENCIAS.STARTDATETIME >= CONVERT(DATETIME, '{$dates['start']->format('Y-m-d')} 00:00:00', 120)
            AND NEXTI_RET_AUSENCIAS.STARTDATETIME <= CONVERT(DATETIME, '{$dates['finish']->format('Y-m-d')} 23:59:59', 120)
            AND NEXTI_RET_AUSENCIAS.ABSENCESITUATIONID IN(4343)
            AND NEXTI_RET_AUSENCIAS.TIPO = 0
            AND NEXTI_RET_AUSENCIAS.LASTUPDATE >= CONVERT(DATETIME, '{$today->format('Y-m-d')} 00:00:00', 120)
            AND NEXTI_RET_AUSENCIAS.LASTUPDATE <= CONVERT(DATETIME, '{$today->format('Y-m-d')} 23:59:59', 120)
            AND NOT EXISTS (
                SELECT
                    1
                FROM ".env('DB_OWNER')."NEXTI_SANCAO
                WHERE NEXTI_SANCAO.ID_AUSENCIA = NEXTI_RET_AUSENCIAS.ID
                AND NEXTI_SANCAO.NUMEMP = NEXTI_COLABORADOR.NUMEMP
                AND NEXTI_SANCAO.TIPCOL = NEXTI_COLABORADOR.TIPCOL
                AND NEXTI_SANCAO.NUMCAD = NEXTI_COLABORADOR.NUMCAD
                AND NEXTI_SANCAO.STARTDATE = NEXTI_RET_AUSENCIAS.STARTDATETIME
            )
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function enviaAdvertencia(): void
    {
        $advertencias = $this->buscaAdvertencias();
        if(sizeof($advertencias) === 0) {
            return;
        }

        foreach($advertencias as $adv){
            $diaFalta = date("d/m/Y", strtotime($adv->STARTDATE));

            $mensagem = 
            "   
                <!DOCTYPE html>
                <html>
                    <body>
                        <p>  
                            Pelo presente fica V.S. advertido por falta sem justificativa legal no dia {$diaFalta}. <br/>
                            Esperamos que V.S. procure não incorrer em novas faltas, pois caso contrário, poderão ser tomadas medidas mais severas, que nos são facultadas pela CLT.
                        </p>
                    </body>
                </html>
            ";

            $array = [
                "finishDate"            => date("dmY235959", strtotime("+15 days", strtotime($adv->STARTDATE))),
                "name"                  => 'Aviso de Advertência - ' . $adv->NOMFUN,
                "noticeCollectorId"     => 3,
                "noticeShowId"          => 3,
                "noticeStatusId"        => 1,
                "noticeTypeId"          => 2,
                "persons"               => [intval($adv->ID_COLABORADOR)], 
                "startDate"             => date("dmY000000"),
                "text"                  => $mensagem
            ];

            $this->restClient->post("notices", [], ['json' => $array])->getResponse();

            if (!$this->restClient->isResponseStatusCode(201)) {
                $errors = $this->getResponseErrors();

                $this->info("Problema ao enviar a âdvertência {$adv->NOMFUN} para API Nexti: {$errors}");
                $this->atualizaRegistroComErro($adv, 2, $errors);
            } else {
                $this->info("Advertência {$adv->NOMFUN} enviada com sucesso para API Nexti");
                $responseData = $this->restClient->getResponseData();
                $id = $responseData['value']['id'];
                $this->atualizaRegistro($adv, 1, $id);
            }

            sleep(2);
        }
    }

    private function buscaAdvertencias(): array
    {
        $query = "
            SELECT 
                NEXTI_SANCAO.*,
                NEXTI_COLABORADOR.ID AS ID_COLABORADOR,
                NEXTI_COLABORADOR.NOMFUN
            FROM ".env('DB_OWNER')."NEXTI_SANCAO
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR
                ON (NEXTI_COLABORADOR.NUMEMP = NEXTI_SANCAO.NUMEMP
                    AND NEXTI_COLABORADOR.TIPCOL = NEXTI_SANCAO.TIPCOL
                    AND NEXTI_COLABORADOR.NUMCAD = NEXTI_SANCAO.NUMCAD
                )
            WHERE NEXTI_SANCAO.NOME = 'Advertencia'
            AND NEXTI_SANCAO.SITUACAO IN(0, 2)
            AND NEXTI_SANCAO.TIPO = 0
            AND EXISTS(
                SELECT
                    1
                FROM ".env('DB_OWNER')."NEXTI_RET_AUSENCIAS
                WHERE NEXTI_RET_AUSENCIAS.ID = NEXTI_SANCAO.ID_AUSENCIA
                AND (DATEDIFF(hour, NEXTI_RET_AUSENCIAS.LASTUPDATE, GETDATE()) + 3) >= 1
            )
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function enviaSuspensao(): void
    {
        $sancoes = $this->buscaSancoes();

        if(sizeof($sancoes) === 0) {
            return;
        }

        foreach($sancoes as $sc){
            $qtd = $this->buscaQtdSancoes($sc);

            $sc->STARTDATE = date("Y-m-d H:i:s", strtotime($sc->STARTDATE));
            $data = DateTime::createFromFormat('Y-m-d H:i:s', $sc->STARTDATE);
            $hoje = DateTime::createFromFormat('Y-m-d', date("Y-m-d"));
            $proxDia = $hoje->modify('+1 day');

            $diaTrabalhar = $this->buscaProximoDiaTrabalho($sc, $data, $proxDia);

            if(!$diaTrabalhar) {
                $this->info("Não foi possível identificar o próximo dia trabalhado.");
                continue;
            }

            if($qtd >= 2){
                $afastamento = $this->criarAfastamentoSuspensao($sc, $diaTrabalhar, 2);
                $this->processaSuspensao($sc, $afastamento, 2);
            }else {
                $afastamento = $this->criarAfastamentoSuspensao($sc, $diaTrabalhar, 1);
                $this->processaSuspensao($sc, $afastamento, 1);
            }
        }
    }

    private function buscaSancoes(): array
    {
        $query = "
            SELECT 
                NEXTI_SANCAO.*,
                NEXTI_COLABORADOR.ID AS ID_COLABORADOR,
                NEXTI_COLABORADOR.CODFIL, 
                NEXTI_COLABORADOR.NOMFUN, 
                NEXTI_COLABORADOR.IDEXTERNO,
                NEXTI_COLABORADOR.IDEXTERNO AS IDEXTERNO_COLABORADOR
            FROM ".env('DB_OWNER')."NEXTI_SANCAO
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR
                ON (NEXTI_COLABORADOR.NUMEMP = NEXTI_SANCAO.NUMEMP
                    AND NEXTI_COLABORADOR.TIPCOL = NEXTI_SANCAO.TIPCOL
                    AND NEXTI_COLABORADOR.NUMCAD = NEXTI_SANCAO.NUMCAD
                )
            WHERE NEXTI_SANCAO.NOME = 'Suspensao'
            AND NEXTI_SANCAO.SITUACAO IN(0,2)
            AND NEXTI_SANCAO.TIPO = 0
            AND EXISTS(
                SELECT
                    1
                FROM ".env('DB_OWNER')."NEXTI_RET_AUSENCIAS
                WHERE NEXTI_RET_AUSENCIAS.ID = NEXTI_SANCAO.ID_AUSENCIA
                AND (DATEDIFF(hour, NEXTI_RET_AUSENCIAS.LASTUPDATE, GETDATE()) + 3) >= 1
            )
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function buscaQtdSancoes(object $sc): int
    {
        $query = "
            SELECT
                *
            FROM ".env('DB_OWNER')."NEXTI_SANCAO
            WHERE NEXTI_SANCAO.NUMEMP = {$sc->NUMEMP}
            AND NEXTI_SANCAO.TIPCOL = {$sc->TIPCOL}
            AND NEXTI_SANCAO.NUMCAD = {$sc->NUMCAD}
            AND NEXTI_SANCAO.ID_AUSENCIA <> {$sc->ID_AUSENCIA}
            AND EXISTS(
                SELECT
                    1
                FROM ".env('DB_OWNER')."NEXTI_SANCAO AS advertencia
                WHERE advertencia.NUMEMP = NEXTI_SANCAO.NUMEMP
                AND advertencia.TIPCOL = NEXTI_SANCAO.TIPCOL
                AND advertencia.NUMCAD = NEXTI_SANCAO.NUMCAD
                AND DATEDIFF(DAY, advertencia.STARTDATE, GETDATE()) < 90
                AND advertencia.NOME = 'Advertencia'
            )
        ";

        return sizeof(DB::connection('sqlsrv')->select($query));
    }

    private function buscaProximoDiaTrabalho($sc, $startDate, $proxDia)
    {
        $referenceDate = $proxDia->format('dmYHis');

        $this->restClient->get("shifts/personExternalId/{$sc->IDEXTERNO}/referenceDate/{$referenceDate}", [])
                        ->getResponse();

        if (!$this->restClient->isResponseStatusCode(200)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema buscar dia trabalhado: {$errors}");
            return false;
        }

        $responseData = $this->restClient->getResponseData();
        if (!$responseData) {
            $this->info("Não foi possível ler a this->restClient da consulta.");
            return false;
        }

        if(isset($responseData['value'])){
            $dados = $responseData['value'];

            if(in_array($dados['shiftTypeId'], [7, 8, 9])) {
                $proxDia->modify("+1 day");

                return $this->buscaProximoDiaTrabalho($sc, $startDate, $proxDia);
            }
        }
            
        return $proxDia;
    }

    private function criarAfastamentoSuspensao($sc, $diaTrabalho, $qtdDias): array
    {
        if($qtdDias == 1){
            $dataInicioSusp = $diaTrabalho->format('Y-m-d');
            $dataFimSusp = $diaTrabalho->format('Y-m-d');
        }else{
            $dataInicioSusp = $diaTrabalho->format('Y-m-d');
            $dataFimSusp = $diaTrabalho->modify("+1 day");
            $dataFimSusp = $dataFimSusp->format('Y-m-d');
        }

        $existe = $this->verificaExisteSuspensao($sc, $dataInicioSusp, $dataFimSusp);

        if(sizeof($existe) == 0){
            $query = 
            "
                INSERT INTO ".env('DB_OWNER')."NEXTI_AUSENCIAS_PROTHEUS (
                    NUMEMP, 
                    CODFIL, 
                    TIPCOL, 
                    NUMCAD, 
                    ABSENCESITUATIONEXTERNALID, 
                    ABSENCESITUATIONID, 
                    FINISHDATETIME, 
                    FINISHMINUTE, 
                    STARTDATETIME, 
                    STARTMINUTE, 
                    PERSONEXTERNALID, 
                    PERSONID, 
                    TIPO, 
                    SITUACAO, 
                    OBSERVACAO, 
                    ID, 
                    IDEXTERNO
                )
                VALUES (
                    {$sc->NUMEMP}, 
                    {$sc->CODFIL}, 
                    {$sc->TIPCOL}, 
                    {$sc->NUMCAD}, 
                    '99999', 
                    6378, 
                    CONVERT(DATETIME, '{$dataFimSusp}', 120), 
                    0, 
                    CONVERT(DATETIME, '{$dataInicioSusp}', 120),
                    0, 
                    '{$sc->IDEXTERNO_COLABORADOR}', 
                    {$sc->ID_COLABORADOR}, 
                    0, 
                    0, 
                    '', 
                    0, 
                    '{$sc->IDEXTERNO_COLABORADOR}'
                )
            ";

            DB::connection('sqlsrv')->statement($query);
        }

        return [
            'dataInicioSusp' => $dataInicioSusp,
            'dataFimSusp' => $dataFimSusp
        ];
    }

    private function verificaExisteSuspensao(object $sc, string $dataInicioSusp, string $dataFimSusp)
    {
        $query = 
        "
            SELECT * FROM ".env('DB_OWNER')."NEXTI_AUSENCIAS_PROTHEUS
            WHERE NEXTI_AUSENCIAS_PROTHEUS.NUMEMP = {$sc->NUMEMP}
            AND NEXTI_AUSENCIAS_PROTHEUS.TIPCOL = {$sc->TIPCOL}
            AND NEXTI_AUSENCIAS_PROTHEUS.NUMCAD = {$sc->NUMCAD}
            AND NEXTI_AUSENCIAS_PROTHEUS.STARTDATETIME = CONVERT(DATETIME, '{$dataInicioSusp}', 120)
            AND NEXTI_AUSENCIAS_PROTHEUS.FINISHDATETIME = CONVERT(DATETIME, '{$dataFimSusp}', 120)
            AND NEXTI_AUSENCIAS_PROTHEUS.ABSENCESITUATIONID = 6378
            AND NEXTI_AUSENCIAS_PROTHEUS.TIPO = 0
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function processaSuspensao(object $sc, array $afastamento, int $qtdDias): void
    {
        $diaSuspenso = date("d/m/Y", strtotime($afastamento['dataInicioSusp']));
        $mensagem = 
        "   
            <!DOCTYPE html>
            <html>
                <body>
                    <p>  
                        Pelo presente o notificamos que a partir de {$diaSuspenso}, está suspenso do exercício de suas funções, pelo prazo de {$qtdDias} dia(s), devendo, portanto, apresentar-se novamente ao serviço no horário usual em seu próximo plantão após os dias de suspensão, salvo outra resolução nossa, que lhe daremos se for o caso.<br/>
                        MOTIVO DA SUSPENSÃO: Falta sem justificativa legal.
                    </p>
                </body>
            </html>
        ";

        $array = [
            "finishDate"            => date("dmY235959", strtotime("+15 days", strtotime($sc->STARTDATE))),
            "name"                  => 'Aviso de Suspensão - ' . $sc->NOMFUN,
            "noticeCollectorId"     => 3,
            "noticeShowId"          => 3,
            "noticeStatusId"        => 1,
            "noticeTypeId"          => 2,
            "persons"               => [intval($sc->ID_COLABORADOR)], 
            //"persons"               => [2196852],
            "startDate"             => date("dmY000000"),
            "text"                  => $mensagem
        ];

        $this->restClient->post("notices", [], ['json' => $array])->getResponse();

        if (!$this->restClient->isResponseStatusCode(201)) {
            $errors = $this->getResponseErrors();
            $this->info("Problema ao enviar a suspensão {$sc->NOMFUN} para API Nexti: {$errors}");
            $this->atualizaRegistroComErro($sc, 2, $errors);
        } else {
            $this->info("Suspensão {$sc->NOMFUN} enviada com sucesso para API Nexti");
            $responseData = $this->restClient->getResponseData();
            $id = $responseData['value']['id'];
            $this->atualizaRegistro($sc, 1, $id);
        }

        sleep(2);
    }

    private function atualizaRegistro(object $aviso, int $situacao, int $id): void
    {
        $dataEnvio = today()->format('Y-m-d H:i:s');

        $obs = date('d/m/Y H:i:s') . ' - Enviado com Sucesso';

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_SANCAO 
            SET ".env('DB_OWNER')."NEXTI_SANCAO.SITUACAO = {$situacao}, 
                ".env('DB_OWNER')."NEXTI_SANCAO.DATA_ENVIO_NEXTI = '{$dataEnvio}',
                ".env('DB_OWNER')."NEXTI_SANCAO.OBSERVACAO = '{$obs}', 
                ".env('DB_OWNER')."NEXTI_SANCAO.ID = '{$id}' 
            WHERE ".env('DB_OWNER')."NEXTI_SANCAO.NUMEMP = {$aviso->NUMEMP}
            AND ".env('DB_OWNER')."NEXTI_SANCAO.TIPCOL = {$aviso->TIPCOL}
            AND ".env('DB_OWNER')."NEXTI_SANCAO.NUMCAD = {$aviso->NUMCAD}
            AND ".env('DB_OWNER')."NEXTI_SANCAO.ID_AUSENCIA = {$aviso->ID_AUSENCIA}
            AND ".env('DB_OWNER')."NEXTI_SANCAO.STARTDATE = CONVERT(DATETIME, '{$aviso->STARTDATE}', 120)
            AND ".env('DB_OWNER')."NEXTI_SANCAO.NOME = '{$aviso->NOME}'
        ");
    } 

    private function atualizaRegistroComErro(object $aviso, int $situacao, ?string $obs = ''): void
    {
        $obs = date('d/m/Y H:i:s') . ' - ' . $obs;

        DB::connection('sqlsrv')->statement("
            UPDATE ".env('DB_OWNER')."NEXTI_SANCAO 
            SET ".env('DB_OWNER')."NEXTI_SANCAO.SITUACAO = {$situacao}, 
                ".env('DB_OWNER')."NEXTI_SANCAO.OBSERVACAO = '{$obs}' 
            WHERE ".env('DB_OWNER')."NEXTI_SANCAO.NUMEMP = {$aviso->NUMEMP}
            AND ".env('DB_OWNER')."NEXTI_SANCAO.TIPCOL = {$aviso->TIPCOL}
            AND ".env('DB_OWNER')."NEXTI_SANCAO.NUMCAD = {$aviso->NUMCAD}
            AND ".env('DB_OWNER')."NEXTI_SANCAO.ID_AUSENCIA = {$aviso->ID_AUSENCIA}
            AND ".env('DB_OWNER')."NEXTI_SANCAO.STARTDATE = CONVERT(DATETIME, '{$aviso->STARTDATE}', 120)
            AND ".env('DB_OWNER')."NEXTI_SANCAO.NOME = '{$aviso->NOME}'
        ");
    }
}
