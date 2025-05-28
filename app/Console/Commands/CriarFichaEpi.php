<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;

/**
 * Classe CriarFichaEPI
 *
 * Esta classe é responsável por criar fichas EPI e atualizar documentos pendentes.
 */
class CriarFichaEPI extends Command
{
    /**
     * O nome e assinatura do comando no console.
     *
     * @var string
     */
    protected $signature = 'CriarFichaEPI';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Construtor da classe.
     */
    public function __construct()
    {
        date_default_timezone_set('America/Sao_Paulo');
        parent::__construct();
    }

    /**
     * Verifica se uma string é um JSON válido.
     *
     * @param string|null $str A string a ser verificada.
     * @return bool Retorna true se a string for um JSON válido, caso contrário, false.
     */
    private function isJson(?string $str = null): bool
    {
        try {
            $str = trim($str) ?? null;
            if (!$str) {
                return false;
            }

            $json = json_decode($str);

            return $json && $str != $json;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Busca avisos pendentes de geração de fichas EPI.
     *
     * @return array Retorna uma lista de avisos pendentes.
     */
    private function buscaAvisosPendentes()
    {
        $query = "
            SELECT 
                " . env('DB_OWNER') . "NEXTI_AVISOS_EPI.*,
                " . env('DB_OWNER') . "NEXTI_EMPRESA.CNPJ AS CNPJ_EMPRESA
            FROM " . env('DB_OWNER') . "NEXTI_AVISOS_EPI
            JOIN " . env('DB_OWNER') . "NEXTI_EMPRESA
                ON " . env('DB_OWNER') . "NEXTI_EMPRESA.NUMEMP = " . env('DB_OWNER') . "NEXTI_AVISOS_EPI.NUMEMP
                AND " . env('DB_OWNER') . "NEXTI_EMPRESA.CODFIL = " . env('DB_OWNER') . "NEXTI_AVISOS_EPI.CODFIL
            WHERE (
                " . env('DB_OWNER') . "NEXTI_AVISOS_EPI.ARQUIVO = '0'
                OR " . env('DB_OWNER') . "NEXTI_AVISOS_EPI.ARQUIVO = ''
            )
        ";

        return DB::connection('sqlsrv')->select($query);
    }

    /**
     * Busca o documento do aviso de um colaborador.
     *
     * @param object $pendente Os dados do aviso pendente.
     * @return string Retorna o conteúdo do documento.
     */
    private function buscaDocumentoAviso($pendente)
    {
        $this->info("Buscando Ficha Colaborador {$pendente->NUMCAD}.");

        $senhaEncode = base64_encode(env('LoginWebService') . ':' . env('SenhaWebService'));

        // Remover caracteres especiais do CNPJ
        $pendente->CNPJ_EMPRESA = str_replace(['-', '/', '.'], '', $pendente->CNPJ_EMPRESA);

        $startDate = date("d/m/Y", strtotime($pendente->STARTDATE));
        $finishDate = date("d/m/Y", strtotime($pendente->FINISHDATE));

        $array = [
            'empresa' => $pendente->CNPJ_EMPRESA,
            'periodo_inicial' => $startDate,
            'periodo_final' => $finishDate,
            'matricula_inicial' => $pendente->NUMCAD,
            'matricula_final' => $pendente->NUMCAD
        ];

        var_dump($array);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'http://10.13.30.3:9099/rest/WebFichaEPI/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => json_encode($array),
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . $senhaEncode,
            ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    /**
     * Cria avisos a partir dos pendentes.
     */
    private function criarAvisos()
    {
        $pendentes = $this->buscaAvisosPendentes();

        if (!$pendentes) {
            $this->info("Não existem avisos a serem gerados.");
            return;
        }

        foreach ($pendentes as $pendente) {
            $documento = $this->buscaDocumentoAviso($pendente);
            if ($this->isJson($documento)) {
                $response = json_decode($documento);
                $this->error($response->errorMessage ?? 'Erro ao consultar o documento');
                continue;
            }

            $this->atualizarDocumento($pendente, $documento);
        }
    }

    /**
     * Atualiza o documento do aviso no banco de dados.
     *
     * @param object $pendente Os dados do aviso pendente.
     * @param string $documento O conteúdo do documento a ser atualizado.
     */
    private function atualizarDocumento($pendente, $documento)
    {
        //var_dump($documento, strpos($documento, "errorCode"));exit;

        if (strpos($documento, "errorCode") !== false) {
            $this->info("Erro: {$documento}.");
            return;
        } else {
            $documento = base64_encode($documento);

            $query = "
                UPDATE " . env('DB_OWNER') . "NEXTI_AVISOS_EPI
                SET " . env('DB_OWNER') . "NEXTI_AVISOS_EPI.ARQUIVO = '{$documento}'
                WHERE " . env('DB_OWNER') . "NEXTI_AVISOS_EPI.NUMEMP = {$pendente->NUMEMP}
                AND " . env('DB_OWNER') . "NEXTI_AVISOS_EPI.CODFIL = '{$pendente->CODFIL}'
                AND " . env('DB_OWNER') . "NEXTI_AVISOS_EPI.NUMCAD = '{$pendente->NUMCAD}'
                AND " . env('DB_OWNER') . "NEXTI_AVISOS_EPI.STARTDATE = CONVERT(DATETIME, '{$pendente->STARTDATE}', 120)
            ";

            DB::connection('sqlsrv')->statement($query);
        }
    }

    /**
     * Executa o comando de console.
     *
     * @return void
     */
    public function handle()
    {
        $this->criarAvisos();
    }
}
