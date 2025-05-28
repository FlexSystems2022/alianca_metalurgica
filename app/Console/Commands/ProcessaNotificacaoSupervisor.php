<?php

namespace App\Console\Commands;

use App\bibliotecas\PHPMailer;
use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\SheetCollection;
use Rap2hpoutre\FastExcel\FastExcel;

class ProcessaNotificacaoSupervisor extends Command
{
    protected $signature = 'ProcessaNotificacaoSupervisor {--test}';

    protected $description = 'Command description';

    public function handle(): void
    {
        if(in_array(Carbon::now()->dayOfWeek, [0, 6])) {
            $this->warn('Sem envio por hoje');
            return;
        }

        if(!$this->verificaDisparoLog() && !$this->option('test')) {
            $this->warn('Horário de envio ainda não chegou!');
            return;
        }

        $sancoes = $this->buscaSancoesNotificar(date("Y-m-d"));
        if(!$sancoes) {
            $this->warn('Sem notificações a enviar');
            return;
        }

        $notificacoes_adm = [
            'mails' => [
                'jessica.martins@resolv.com.br',
                'priscila@resolv.com.br',
                'parcerias@flexsystems.com.br',
            ],
            'data' => [
                'Supervisor Sem Email' => array_map(function(object $item) { return (array) $item; }, $this->buscaSupervisoresSemEmail()),
                'Colaboradores Advertidos' => [],
                'Colaboradores Não Advertidos' => array_map(function(object $item) {
                    return [
                        'EMPRESA' => $item->NUMEMP,
                        'FILIAL' => $item->CODFIL,
                        'MATRICULA' => $item->NUMCAD,
                        'COLABORADOR' => $item->NOMFUN,
                        'DATA_FALTA' => Carbon::parse($item->STARTDATE)->format('d/m/Y'),
                        'DIA_LANCADO' => Carbon::parse($item->LASTUPDATE)->subHours(3)->format('d/m/Y'),
                        'CODIGO USUARIO' => $item->USERREGISTERID ?? 0
                    ];
                }, $this->buscaAdvertenciasNaoEnviadas()),
            ]
        ];

        $notificacoes = [];
        foreach ($sancoes as $sancao) {
            $key = "{$sancao->ID_AUSENCIA}-{$sancao->NUMEMP}-{$sancao->TIPCOL}-{$sancao->NUMCAD}-{$sancao->NOME}-{$sancao->STARTDATE}";

            $item = [
                'EMPRESA' => $sancao->NUMEMP,
                'FILIAL' => $sancao->CODFIL,
                'MATRICULA' => $sancao->NUMCAD,
                'COLABORADOR' => $sancao->NOMFUN,
                'DATA FALTA' => Carbon::parse($sancao->STARTDATE)->format('d/m/Y'),
                'TIPO' => $sancao->NOME,
            ];

            $notificacoes_adm['data']['Colaboradores Advertidos'][$key] = $item;

            $supervisores = $this->buscaSupervisoresNotificar($sancao->POSTO);
            if(!$supervisores) {
                continue;
            }

            foreach($supervisores as $supervisor) {
                if(!isset($notificacoes[$supervisor->CADRES])) {
                    $notificacoes[$supervisor->CADRES] = [
                        'mails' => [],
                        'data' => [
                            'Colaboradores Advertidos' => []
                        ],
                        'sancoes' => []
                    ];
                }

                $notificacoes[$supervisor->CADRES]['mails'][] = $supervisor->EMAIL;
                $notificacoes[$supervisor->CADRES]['data']['Colaboradores Advertidos'][$key] = $item;
                $notificacoes[$supervisor->CADRES]['sancoes'][$key] = $sancao;
            }
        }

        foreach ($notificacoes as $notificacao) {
            $emails = collect($notificacao['mails'])->filter()->unique()->toArray();

            if($emails) {
                if($this->enviaEmail($emails, $notificacao['data'])) {
                    $this->atualizaRegistros($notificacao['sancoes']);
                }
            }
        }

        $this->enviaEmail($notificacoes_adm['mails'], $notificacoes_adm['data']);

        if(!$this->option('test')) {
            $this->updateDataLog(date('Y-m-d H:i:s'));
        }
    }

    private function buscaSancoesNotificar(string $date): array
    {
        $query = "
            SELECT 
                NEXTI_SANCAO.ID_AUSENCIA, 
                NEXTI_SANCAO.NUMEMP, 
                NEXTI_COLABORADOR.CODFIL, 
                NEXTI_SANCAO.TIPCOL, 
                NEXTI_SANCAO.NUMCAD,
                NEXTI_SANCAO.NOME, 
                NEXTI_SANCAO.STARTDATE,
                NEXTI_COLABORADOR.NOMFUN, 
                NEXTI_COLABORADOR.IDEXTERNO AS IDEXTERNO_COLABORADOR,
                NEXTI_SANCAO.POSTO
            FROM ".env('DB_OWNER')."NEXTI_SANCAO
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR
                ON(NEXTI_COLABORADOR.NUMEMP = NEXTI_SANCAO.NUMEMP
                    AND NEXTI_COLABORADOR.TIPCOL = NEXTI_SANCAO.TIPCOL
                    AND NEXTI_COLABORADOR.NUMCAD = NEXTI_SANCAO.NUMCAD
                )
            WHERE NEXTI_SANCAO.SITUACAO = 1
            AND NEXTI_SANCAO.TIPO = 0
            AND NEXTI_SANCAO.DATA_ENVIO_NEXTI = '{$date}'
            AND NOT EXISTS(
                SELECT
                    1
                FROM ".env('DB_OWNER')."NEXTI_SANCAO_NOTIFICACAO
                WHERE NEXTI_SANCAO_NOTIFICACAO.ID_AUSENCIA = NEXTI_SANCAO.ID_AUSENCIA
                AND NEXTI_SANCAO_NOTIFICACAO.NUMEMP      = NEXTI_SANCAO.NUMEMP
                AND NEXTI_SANCAO_NOTIFICACAO.TIPCOL      = NEXTI_SANCAO.TIPCOL
                AND NEXTI_SANCAO_NOTIFICACAO.NUMCAD      = NEXTI_SANCAO.NUMCAD
                AND NEXTI_SANCAO_NOTIFICACAO.NOME        = NEXTI_SANCAO.NOME
                AND NEXTI_SANCAO_NOTIFICACAO.STARTDATE   = NEXTI_SANCAO.STARTDATE
                AND NEXTI_SANCAO_NOTIFICACAO.POSTO       = NEXTI_SANCAO.POSTO
                AND NEXTI_SANCAO_NOTIFICACAO.DATA_ENVIO  = '".date('Y-m-d')."'
            )
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function buscaSupervisoresNotificar(int $posto): array
    {
        $query = "
            SELECT 
                NEXTI_AREA_POSTOS.CADRES,
                NEXTI_AREA.PERSONRESPONSIBLEID,
                NEXTI_COLABORADOR_AUX.NAME,
                NEXTI_COLABORADOR_AUX.EMAIL
            FROM ".env('DB_OWNER')."NEXTI_AREA_POSTOS
            JOIN ".env('DB_OWNER')."NEXTI_AREA
                ON(NEXTI_AREA.CADRES = NEXTI_AREA_POSTOS.CADRES)
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR_AUX
                ON(NEXTI_COLABORADOR_AUX.USERACCOUNTID = NEXTI_AREA.PERSONRESPONSIBLEID)
            WHERE NEXTI_AREA_POSTOS.IDPOSTO IN({$posto})
            AND NEXTI_AREA_POSTOS.TIPO <> 3

            UNION ALL

            SELECT 
                NEXTI_AREA_POSTOS.CADRES,
                NEXTI_AREA_SUPERVISOR.SUPERVISOR AS PERSONRESPONSIBLEID,
                NEXTI_COLABORADOR_AUX.NAME,
                NEXTI_COLABORADOR_AUX.EMAIL
            FROM ".env('DB_OWNER')."NEXTI_AREA_POSTOS
            JOIN ".env('DB_OWNER')."NEXTI_AREA_SUPERVISOR
                ON(NEXTI_AREA_SUPERVISOR.CADRES = NEXTI_AREA_POSTOS.CADRES)
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR_AUX
                ON(NEXTI_COLABORADOR_AUX.ID = NEXTI_AREA_SUPERVISOR.SUPERVISOR)
            WHERE NEXTI_AREA_POSTOS.IDPOSTO IN({$posto})
            AND NEXTI_AREA_POSTOS.TIPO <> 3
        ";

        return collect(DB::connection('sqlsrv')->select($query))
            ->filter(function(object $supervisor) {
                return mb_strlen($supervisor->EMAIL) > 0;
            })
            ->unique()
            ->toArray();
    }

    private function buscaSupervisoresSemEmail(): array
    {
        $query = "
            SELECT 
                NEXTI_COLABORADOR_AUX.ID,
                NEXTI_COLABORADOR_AUX.NAME AS NOME,
                NEXTI_COLABORADOR_AUX.EMAIL
            FROM ".env('DB_OWNER')."NEXTI_AREA_POSTOS
            JOIN ".env('DB_OWNER')."NEXTI_AREA
                ON(NEXTI_AREA.CADRES = NEXTI_AREA_POSTOS.CADRES)
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR_AUX
                ON(NEXTI_COLABORADOR_AUX.USERACCOUNTID = NEXTI_AREA.PERSONRESPONSIBLEID)
            WHERE NEXTI_AREA_POSTOS.TIPO <> 3
            AND (NEXTI_COLABORADOR_AUX.EMAIL = '' OR NEXTI_COLABORADOR_AUX.EMAIL IS NULL)

            UNION

            SELECT 
                NEXTI_COLABORADOR_AUX.ID,
                NEXTI_COLABORADOR_AUX.NAME AS NOME,
                NEXTI_COLABORADOR_AUX.EMAIL
            FROM ".env('DB_OWNER')."NEXTI_AREA_POSTOS
            JOIN ".env('DB_OWNER')."NEXTI_AREA_SUPERVISOR
                ON(NEXTI_AREA_SUPERVISOR.CADRES = NEXTI_AREA_POSTOS.CADRES)
            JOIN ".env('DB_OWNER')."NEXTI_COLABORADOR_AUX
                ON(NEXTI_COLABORADOR_AUX.ID = NEXTI_AREA_SUPERVISOR.SUPERVISOR)
            WHERE NEXTI_AREA_POSTOS.TIPO <> 3
            AND (NEXTI_COLABORADOR_AUX.EMAIL = '' OR NEXTI_COLABORADOR_AUX.EMAIL IS NULL)
        ";

        return DB::connection('sqlsrv')->select($query);
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
    
    private function buscaAdvertenciasNaoEnviadas(): array
    {
        $dates = $this->getDataAfastamentos(Carbon::now()->dayOfWeek);

        $today = Carbon::today();

        $query = "
            SELECT
                NEXTI_RET_AUSENCIAS.ID AS ID_AUSENCIA,
                NEXTI_COLABORADOR.NUMEMP,
                NEXTI_COLABORADOR.CODFIL,
                NEXTI_COLABORADOR.TIPCOL,
                NEXTI_COLABORADOR.NUMCAD,
                NEXTI_COLABORADOR.NOMFUN,
                NEXTI_RET_AUSENCIAS.STARTDATETIME AS STARTDATE,
                NEXTI_RET_AUSENCIAS.LASTUPDATE,
                NEXTI_RET_AUSENCIAS.USERREGISTERID
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
            WHERE NEXTI_RET_AUSENCIAS.STARTDATETIME < CONVERT(DATE, '{$dates['start']->format('Y-m-d')}', 120)
            AND NEXTI_RET_AUSENCIAS.ABSENCESITUATIONID IN(4343)
            AND NEXTI_RET_AUSENCIAS.TIPO = 0
            AND NEXTI_RET_AUSENCIAS.LASTUPDATE >= CONVERT(DATETIME, '{$today->format('Y-m-d')} 00:00:00', 120)
            AND NEXTI_RET_AUSENCIAS.LASTUPDATE <= CONVERT(DATETIME, '{$today->format('Y-m-d')} 23:59:59', 120)
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function enviaEmail(array $emails, array $data): bool
    {
        $path = $this->generateXlsx($data);

        $mail = new PHPMailer();
        $mail->Port       = 587;
        $mail->SMTPSecure = 'tls';
        $mail->IsSMTP();
        $mail->Host       = 'outlook.office365.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'flex@resolv.com.br';
        $mail->Password   = 'Roy32189';
        $mail->From       = 'flex@resolv.com.br';
        $mail->CharSet    = 'utf-8';
        $mail->FromName   = 'Resolv';
        $mail->WordWrap   = 50;
        $mail->Body       = 'Em anexo Colaboradores Suspensos/Advertidos do dia ' . date('d/m/Y');

        $mail->Subject    = 'Colaboradores Suspensos/Advertidos - ' . date('d/m/Y');

        if($this->option('test')) {
            $emails = [
                'parcerias@flexsystems.com.br',
            ];
        }

        foreach($emails as $email) {
            $mail->AddAddress($email);
        }

        $mail->addAttachment($path, 'Sancao_' . date('Y_m_d_H_i_s') . '.xlsx');

        if (!$mail->Send()) {
            $this->error("Erro: {$mail->ErrorInfo}");
            return false;
        }

        return true;
    }

    private function atualizaRegistros(array $sancoes): void
    {
        if($this->option('test')) {
            return;
        }

        foreach($sancoes as $sancao) {
            DB::connection("sqlsrv")
                ->table(env('DB_OWNER')."NEXTI_SANCAO_NOTIFICACAO")
                ->insert([
                    'ID_AUSENCIA' => $sancao->ID_AUSENCIA,
                    'NUMEMP'      => $sancao->NUMEMP,
                    'TIPCOL'      => $sancao->TIPCOL,
                    'NUMCAD'      => $sancao->NUMCAD,
                    'NOME'        => $sancao->NOME,
                    'STARTDATE'   => $sancao->STARTDATE,
                    'POSTO'       => $sancao->POSTO,
                    'DATA_ENVIO'  => date('Y-m-d')
                ]);
        }
    }

    /**
     * Get Last Log
     **/
    private function verificaDisparoLog(): bool
    {
        $now = Carbon::now();
        if($now->format('H') < 18) {
            return false;
        }

        $last = DB::connection('sqlsrv')
            ->table(env('DB_OWNER').'NEXTI_DATA_LOG_EMAIL')
            ->select('DATA_INICIO')
            ->where('TIPO', 'SANCAO')
            ->first();

        return Carbon::parse($last->DATA_INICIO)->format('Y-m-d') !== $now->format('Y-m-d');
    }

    /**
     * Update Log
     **/
    private function updateDataLog(string $date): void
    {
        DB::connection('sqlsrv')
            ->table(env('DB_OWNER').'NEXTI_DATA_LOG_EMAIL')
            ->where('TIPO', 'SANCAO')
            ->update([
                'DATA_INICIO' => $date
            ]);
    }

    /**
     * Generate Xlsx
     */
    private function generateXlsx(array $data): string
    {
        $path = storage_path('app/public/logs/error/Sancao' . date('d_m_Y_H_i_s') . '.xlsx');
        $sheets = new SheetCollection($data);

        return (new FastExcel($sheets))->export($path);
    }
}