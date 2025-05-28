<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

class BuscaApuracao extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuscaApuracao';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Rest Client
     */
    private $restClient = null;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->restClient = new \App\Shared\Provider\RestClient('nexti');
        $this->restClient->withOAuthToken('client_credentials');

        $this->busca();
        $this->buscaEventosPorLinha();
        //$this->insereTabelaPrincipal();
    }

    private function getResponseErrors(): string
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

    private function busca(): void
    {
        $response = $this->restClient->get("timetrackingperiods/all", []);

        if (!$response->isResponseStatusCode(200)) {
            $this->info("Problema buscar apurações: {$this->getResponseErrors()}");
            return;
        }

        $responseData = $response->getResponseData();
        if (!$responseData) {
            $this->info("Não foi possível ler a resposta da consulta.");
            return;
        }

        $content = $responseData['value'];

        if (sizeof($content) > 0) {
            foreach ($content as $reg) {
                $this->addApuracao($reg);
            }
        }
    }

    private function addApuracao(array $dados): void
    {
        $existe = $this->verificaExiste($dados['id']);

        if(sizeof($existe) > 0){
            $this->info("Apuração já existe");
            return;
        }

        $array = [
            'ID' => $dados['id'],
            'NAME' => $dados['name'],
            'STARTDATE' => date("Y-m-d H:i:s", strtotime($dados['startDate'])),
            'FINISHDATE' => date("Y-m-d H:i:s", strtotime($dados['finishDate'])),
            'STATUS' => $dados['timeTrackingPeriodStatusId'] ?? ''
        ];

        DB::connection('sqlsrv')
            ->table(env('DB_OWNER').'FLEX_RET_APURACAO')
            ->insert($array);

        $this->info("Apuracao salva com sucesso.");
    }

    private function verificaExiste(int $id): array
    {
        $query = "
            SELECT * FROM ".env('DB_OWNER')."FLEX_RET_APURACAO
            WHERE ".env('DB_OWNER')."FLEX_RET_APURACAO.ID = {$id}
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function buscaPeriodos(): array
    {
        $periodo_inicial = date('Y-m-01', strtotime('-1 month'));
        $periodo_final = date('Y-m-t', strtotime('-1 month'));
        $query = "
            SELECT * FROM ".env('DB_OWNER')."FLEX_RET_APURACAO
            WHERE STATUS = '1'
            AND STARTDATE >= CONVERT(DATE, '{$periodo_inicial}', 120)
            AND STARTDATE <= CONVERT(DATE, '{$periodo_final}', 120)
        ";
        return DB::connection('sqlsrv')->select($query);
    }

    private function buscaEventosPorLinha(): void
    {
        $periodos = $this->buscaPeriodos();

        $this->limpaTabelaFopag();
        
        if(!$periodos) {
            $this->info("Não Existe Periodos a consultar");
            return;
        }
        
        foreach($periodos as $periodo){
            $this->info("Buscando eventos [{$periodo->ID}]");

            $response = $this->restClient->get("payroll/export/{$periodo->ID}", []);            
            if (!$response->isResponseStatusCode(200)) {
                $this->info("Problema buscar eventos: {$this->getResponseErrors()}");
                continue;
            }

            $responseData = $response->getResponseData();
            if (!$responseData) {
                $this->info("Não foi possível ler a resposta da consulta.");
                continue;
            }

            $content = $responseData['value'] ?? [];

            foreach($content as $item) {
                $this->addApuracaoFopag($periodo->ID, $item);
            }
        }
    }

    private function limpaTabelaFopag()
    {
        DB::connection('sqlsrv')
            ->table(env('DB_OWNER').'FLEX_RET_APURACAO_FOPAG')->delete();
    }

    private function addApuracaoFopag(int $periodId, array $dados): void
    {
        $existe = $this->verificaExisteApuracaoFopag($periodId, $dados['periodName'], $dados['layoutName']);
        if(sizeof($existe) > 0){
            $this->info("Evento já existe");
            return;
        }

        DB::connection('sqlsrv')
            ->table('FLEX_RET_APURACAO_FOPAG')
            ->insert([
                'PERIODID' => $periodId,
                'PERIODNAME' => $dados['periodName'],
                'LAYOUTNAME' => $dados['layoutName'],
                'FOPAGLINES' => $dados['fopagLines']
            ]);
    }

    private function verificaExisteApuracaoFopag(int $periodId, string $periodName, string $layoutName): array
    {
        $query = "
            SELECT * FROM ".env('DB_OWNER')."FLEX_RET_APURACAO_FOPAG
            WHERE PERIODID = {$periodId}
            AND PERIODNAME = '{$periodName}'
            AND LAYOUTNAME = '{$layoutName}'
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function insereTabelaPrincipal(): void
    {
        $apuracoes = $this->buscaEventos('Protheus Customizável 7');

        foreach($apuracoes as $apuracao) {
            $this->info("Inserindo {$apuracao->PERIODNAME} - {$apuracao->LAYOUTNAME}");

            $eventos = collect(explode("\n", $apuracao->FOPAGLINES))->map(function(string $line) {
                $horas = str_replace(',', '', substr($line, 20, 10));
                $minutos = str_replace(',', '', substr($line, 31, 2));

                $horas = $horas .'.'. $minutos;

                return [
                    'original' => $line,
                    'empresa' => substr($line, 6, 2),
                    'filial' => substr($line, 8, 2),
                    'matricula' => substr($line, 10, 6),
                    'verba' => substr($line, 16, 3),
                    'tipo' => 'H',
                    'valor' => 0,
                    'parcela' => 1,
                    'horas' => floatval($horas),
                ];
            });

            if($eventos->count() === 0) {
                $this->info('Sem eventos a gerar');
                continue;
            }
            
            foreach($eventos as $evento) {
                $this->addFolhaPagamento($apuracao, $evento);
            }
        }
    }

    private function buscaEventos(string $layoutName): array
    {
        $query = "
            SELECT
                FLEX_RET_APURACAO_FOPAG.PERIODNAME,
                FLEX_RET_APURACAO_FOPAG.LAYOUTNAME,
                FLEX_RET_APURACAO_FOPAG.FOPAGLINES,
                FLEX_RET_APURACAO.STARTDATE AS DATE
            FROM ".env('DB_OWNER')."FLEX_RET_APURACAO_FOPAG
            JOIN ".env('DB_OWNER')."FLEX_RET_APURACAO
                ON(FLEX_RET_APURACAO.id = FLEX_RET_APURACAO_FOPAG.PERIODID)
            WHERE LAYOUTNAME = '{$layoutName}'
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    private function addFolhaPagamento(object $apuracao, array $evento): void
    {
        $colaborador = $this->getColaborador($evento['matricula'], $evento['empresa'], $evento['filial']);
        if(!$colaborador) {
            $this->error('Colaboarador não encontrado');
            return;
        }

        $data = [
            'NUMEMP' => $colaborador->NUMEMP,
            'CODFIL' => $colaborador->CODFIL,
            'TIPCOL' => $colaborador->TIPCOL,
            'NUMCAD' => $colaborador->NUMCAD,
            'NOMFUN' => trim($colaborador->NOMFUN),
            'PERIODO' => Carbon::parse($apuracao->DATE)->format('Y-m-d'),
            'TIPOFOLHA' => $evento['tipo'],
            'PARCELA' => $evento['parcela'],
            'HORAS'  => $evento['horas'],
            'VALOR' => $evento['valor'],
            'VERBA' => $evento['verba'],
            'LINHA' => $evento['original']
        ];

        $existe = $this->verificaExisteFolhaPagamento($data);
        if($existe > 0){
            $this->info("Evento já existe no banco");
            return;
        }

        DB::connection('sqlsrv')
            ->table(env('DB_OWNER').'FLEX_PROTHEUS_FOLHA_PAGAMENTO')
            ->insert($data);

        $this->info("Evento cadastrado com sucesso");
    }

    private function getColaborador(string $matricula, string $empresa, string $filial): ?object
    {
        $matricula = str_pad($matricula, 8, '0', STR_PAD_LEFT);
        $empresa = str_pad($empresa, 8, '0', STR_PAD_LEFT);
        $filial = str_pad($filial, 8, '0', STR_PAD_LEFT);

        $query = "
            SELECT
                NUMEMP,
                CODFIL,
                TIPCOL,
                NUMCAD,
                NOMFUN
            FROM ".env('DB_OWNER')."FLEX_COLABORADOR
            WHERE LPAD(FLEX_COLABORADOR.NUMCAD, 8, '0') = '{$matricula}'
            AND LPAD(FLEX_COLABORADOR.NUMEMP, 8, '0') = '{$empresa}'
            AND LPAD(FLEX_COLABORADOR.CODFIL, 8, '0') = '{$filial}'
            AND FLEX_COLABORADOR.ID > 0
            AND FLEX_COLABORADOR.TIPO <> 3
        ";

        return DB::connection('sqlsrv')->selectOne($query);
    }

    private function verificaExisteFolhaPagamento(array $data): int
    {
        $query = "
            SELECT
                COUNT(1) as total
            FROM ".env('DB_OWNER')."FLEX_PROTHEUS_FOLHA_PAGAMENTO
            WHERE TIPCOL = '{$data['TIPCOL']}'
            AND NUMEMP = '{$data['NUMEMP']}'
            AND CODFIL = '{$data['CODFIL']}'
            AND NUMCAD = '{$data['NUMCAD']}'
            AND PERIODO = '{$data['PERIODO']}'
            AND TIPOFOLHA = '{$data['TIPOFOLHA']}'
            AND PARCELA = '{$data['PARCELA']}'
            AND VERBA = '{$data['VERBA']}'
        ";

        return DB::connection('sqlsrv')->selectOne($query)->total ?? 0;
    }
}