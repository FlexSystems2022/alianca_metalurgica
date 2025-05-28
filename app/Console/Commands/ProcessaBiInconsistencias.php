<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class ProcessaBiInconsistencias extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ProcessaBiInconsistencias';

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

    private function limpa(){
        $query = 
        "
            DELETE FROM ".env('DB_OWNER')."BI_INCONSISTENCIA
        ";

        DB::connection('sqlsrv')->statement($query);
    }

    private function insere(){
        $query = 
        "
            INSERT INTO ".env('DB_OWNER')."BI_INCONSISTENCIA
            SELECT 
            empresa.RAZSOC AS EMPRESA,
            cliente.NOMOEM AS CLIENTE,
            colab.NUMCAD AS MATRICULA,
            marcacoes.CLOCKINGTYPENAME AS STATUS,
            marcacoes.CLOCKINGDATE AS DATA_MARCACAO,
            marcacoes.DEVICECODE AS TERMINAL,
            posto.DESPOS AS POSTO,
            posto.CODCCU AS CENTRO_CUSTO,
            marcacoes.DIRECTIONTYPEID AS DIRECAO_ACESSO,
            cargo.TITCAR AS CARGO,
            STUFF((
                SELECT area.NAME + ',' FROM ".env('DB_OWNER')."NEXTI_RET_AREA_POSTO area_posto
                JOIN ".env('DB_OWNER')."NEXTI_RET_AREA area
                    ON area.ID = area_posto.ID_AREA
                WHERE area_posto.ID_POSTO = marcacoes.WORKPLACEID
                AND area.ACTIVE = 1
                FOR XML PATH(''), TYPE
            ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS AREA,
            STUFF((
                SELECT responsavel.NAME + ',' FROM ".env('DB_OWNER')."NEXTI_RET_AREA_POSTO area_posto
                JOIN ".env('DB_OWNER')."NEXTI_RET_AREA area
                    ON area.ID = area_posto.ID_AREA
                JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR_AUX responsavel
                    ON responsavel.USERACCOUNTID = area.PERSONRESPONSIBLEID
                WHERE area_posto.ID_POSTO = marcacoes.WORKPLACEID
                AND area.ACTIVE = 1
                FOR XML PATH(''), TYPE
            ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS RESPONSAVEL,
            STUFF((
                SELECT supervisor.NAME + ',' FROM NEXTI_RET_AREA_POSTO area_posto
                JOIN ".env('DB_OWNER')."NEXTI_RET_AREA area
                    ON area.ID = area_posto.ID_AREA
                JOIN ".env('DB_OWNER')."NEXTI_RET_AREA_SUPERVISOR area_supervisor
                    ON area_supervisor.ID_AREA = area.ID
                JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR_AUX supervisor
                    ON supervisor.ID = area_supervisor.ID_SUPERVISOR
                WHERE area_posto.ID_POSTO = marcacoes.WORKPLACEID
                AND area.ACTIVE = 1
                FOR XML PATH(''), TYPE
            ).value('.', 'NVARCHAR(MAX)'), 1, 2, '') AS SUPERVISOR
            FROM ".env('DB_OWNER')."NEXTI_RET_MARCACOES marcacoes
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR colab
                ON colab.IDEXTERNO = marcacoes.EXTERNALPERSONID
            JOIN ".env('DB_OWNER')."NEXTI_CARGO cargo
                ON cargo.IDEXTERNO = colab.CARGO
            JOIN ".env('DB_OWNER')."NEXTI_POSTO posto
                ON posto.IDEXTERNO = marcacoes.EXTERNALWORKPLACEID
            JOIN ".env('DB_OWNER')."NEXTI_CLIENTE cliente
                ON cliente.IDEXTERNO = posto.CODOEM
            JOIN ".env('DB_OWNER')."NEXTI_UNIDADE
                ON ".env('DB_OWNER')."NEXTI_UNIDADE.IDEXTERNO = posto.UNIDADE_NEGOCIO
            JOIN ".env('DB_OWNER')."NEXTI_EMPRESA empresa
                ON empresa.NUMEMP = colab.NUMEMP
                AND empresa.CODFIL = colab.CODFIL
            WHERE marcacoes.CLOCKINGDATE >= CONVERT(DATETIME, GETDATE()-91,120)
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

        $this->limpa();
        $this->insere();
    }
}