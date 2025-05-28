@php
$tipo = '';
$dicionarioTipo = array(
'1' => 'ENVIO DE AUSÊNCIAS DE PROTHEUS PARA O NEXTI',
'2' => 'CARGOS',
'3' => 'COLABORADORES',
'4' => 'CADASTRO DE ESCALA',
'5' => 'HORÁRIOS',
'6' => 'CADASTRO DE POSTOS',
'7' => 'CADASTRO DE SITUAÇÕES',
'8' => 'CLIENTES',
'9' => 'ENVIO DE TROCA DE ESCALAS DE PROTHEUS PARA O NEXTI',
'10' => 'TROCA DE POSTOS',
'11' => 'RETORNO DE AUSÊNCIAS NEXTI PARA PROTHEUS',
'12' => 'RETORNO DE TROCA DE ESCALA NEXTI PARA PROTHEUS',
'13' => 'ENVIO CONTRACHEQUES DO PROTHEUS PARA NEXTI',
'14' => 'RETORNO TROCA POSTOS NEXTI PARA PROTHEUS'
);
@endphp

@if ($retorno['cargos'])
    <strong>TIPO : ENVIO DE CARGOS DO PROTHEUS PARA O NEXTI</strong>
    <table>
        <thead>
            <td class="per10 text-center"><strong>ESTCAR</strong></td>
            <td class="per10 text-center"><strong>CODCAR</strong></td>
            <td class="per10 text-center"><strong>CARGO</strong></td>
            <td class="per70 text-left"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="4">&nbsp;</td>
            </tr>
            @forelse ($retorno['cargos'] as $item)
                <tr>
                    <td class="text-center">{{ $item['ESTCAR'] }}</td>
                    <td class="text-center">{{ $item['CODCAR'] }}</td>
                    <td class="text-center">{{ $item['TITCAR'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="4">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['horarios'])
    <strong>TIPO : ENVIO DE HORARIOS DO PROTHEUS PARA O NEXTI</strong>
    <table>
        <thead>
            <td class="per10 text-center"><strong>HORARIO</strong></td>
            <td class="per30 text-center"><strong>NOME</strong></td>
            <td class="per60 text-left"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
            @forelse ($retorno['horarios'] as $item)
                <tr>
                    <td class="text-center">{{ $item['HORARIO'] }}</td>
                    <td class="text-center">{{ $item['NOME'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['escalas'])
    <strong>TIPO : ENVIO DE ESCALAS DO PROTHEUS PARA O NEXTI</strong>
    <table>
        <thead>
            <td class="per10 text-center"><strong>ESCALA</strong></td>
            <td class="per30 text-center"><strong>NOME</strong></td>
            <td class="per60 text-left"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
            @forelse ($retorno['escalas'] as $item)
                <tr>
                    <td class="text-center">{{ $item['ESCALA'] }}</td>
                    <td class="text-center">{{ $item['NOME'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['postos'])
    <strong>TIPO : ENVIO DE POSTOS DO PROTHEUS PARA O NEXTI</strong>
    <table>
        <thead>
            <td class="per10 text-center"><strong>ORGANOGRAMA</strong></td>
            <td class="per30 text-center"><strong>NOME</strong></td>
            <td class="per60 text-left"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
            @forelse ($retorno['postos'] as $item)
                <tr>
                    <td class="text-center">{{ $item['POSTRA'] }}</td>
                    <td class="text-center">{{ $item['DESPOS'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['situacoes'])
    <strong>TIPO : ENVIO DE SITUAÇÕES DO PROTHEUS PARA O NEXTI</strong>
    <table>
        <thead>
            <td class="per10 text-center"><strong>SITUACAO</strong></td>
            <td class="per30 text-center"><strong>NOME</strong></td>
            <td class="per60 text-left"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
            @forelse ($retorno['situacoes'] as $item)
                <tr>
                    <td class="text-center">{{ $item['SITUACAO'] }}</td>
                    <td class="text-center">{{ $item['NOME'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['tomadores'])
    <strong>TIPO : ENVIO DE CLIENTES DO PROTHEUS PARA O NEXTI</strong>
    <table>
        <thead>
            <td class="per10 text-center"><strong>CLIENTE</strong></td>
            <td class="per30 text-center"><strong>NOME</strong></td>
            <td class="per60 text-left"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
            @forelse ($retorno['tomadores'] as $item)
                <tr>
                    <td class="text-center">{{ $item['CLIENTE'] }}</td>
                    <td class="text-center">{{ $item['NOME'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['colaboradores'])
    <strong>TIPO : ENVIO DE COLABORADORES DO PROTHEUS PARA O NEXTI</strong>
    <table>
        <thead>
            <td class="per10 text-center" style="width: 10%"><strong>COLABORADOR</strong></td>
            <td class="per45 text-center" style="width: 45%"><strong>NOME</strong></td>
            <td class="per45 text-left" style="width: 45%"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
            @forelse ($retorno['colaboradores'] as $item)
                <tr>
                    <td class="text-center">{{ $item['COLAB'] }}</td>
                    <td class="text-center">{{ $item['NOME'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif





@if ($retorno['ausencias'])
    <strong>TIPO : ENVIO DE AUSÊNCIAS DO PROTHEUS PARA O NEXTI</strong>
    <table>
        <thead>
            <td class="per5 text-center" style="width: 5%"><strong>EMPRESA</strong></td>
            <td class="per10 text-center" style="width: 10%"><strong>MATRICULA</strong></td>
            <td class="per30 text-left" style="width: 30%"><strong>COLABORADOR</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>DATA_INICIAL</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>HORA_INICIAL</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>DATA_FINAL</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>HORA_FINAL</strong></td>
            <td class="per35 text-left" style="width: 35%"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="8">&nbsp;</td>
            </tr>
            @forelse ($retorno['ausencias'] as $item)
                <tr>
                    <td class="text-center">{{ $item['EMPRESA'] }}</td>
                    <td class="text-center">{{ $item['MATRICULA'] }}</td>
                    <td class="text-left">{{ $item['COLABORADOR'] }}</td>
                    <td class="text-center">{{ $item['DATA_INICIAL'] }}</td>
                    <td class="text-center">{{ $item['HORA_INICIAL'] }}</td>
                    <td class="text-center">{{ $item['DATA_FINAL'] }}</td>
                    <td class="text-center">{{ $item['HORA_FINAL'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['retTrocaAusencias'])
    <strong>TIPO : RETORNO DE AUSÊNCIAS DO NEXTI PARA O PROTHEUS</strong>
    <table>
        <thead>
            <td class="per5 text-center" style="width: 5%"><strong>EMPRESA</strong></td>
            <td class="per10 text-center" style="width: 10%"><strong>MATRICULA</strong></td>
            <td class="per30 text-left" style="width: 30%"><strong>COLABORADOR</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>DATA_INICIAL</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>HORA_INICIAL</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>DATA_FINAL</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>HORA_FINAL</strong></td>
            <td class="per35 text-left" style="width: 35%"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="8">&nbsp;</td>
            </tr>
            @forelse ($retorno['retTrocaAusencias'] as $item)
                <tr>
                    <td class="text-center">{{ $item['EMPRESA'] }}</td>
                    <td class="text-center">{{ $item['MATRICULA'] }}</td>
                    <td class="text-left">{{ $item['COLABORADOR'] }}</td>
                    <td class="text-center">{{ $item['DATA_INICIAL'] }}</td>
                    <td class="text-center">{{ $item['HORA_INICIAL'] }}</td>
                    <td class="text-center">{{ $item['DATA_FINAL'] }}</td>
                    <td class="text-center">{{ $item['HORA_FINAL'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['trocaEscalas'])
    <strong>TIPO : ENVIO DE TROCA DE ESCALAS DO PROTHEUS PARA O NEXTI</strong>
    <table>
        <thead>
            <td class="per5 text-center" style="width: 5%"><strong>EMPRESA</strong></td>
            <td class="per10 text-center" style="width: 10%"><strong>MATRICULA</strong></td>
            <td class="per30 text-left" style="width: 30%"><strong>COLABORADOR</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>ESCALA</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>TURMA</strong></td>
            <td class="per10 text-center" style="width: 10%"><strong>DATA_ALTERAÇÃO</strong></td>
            <td class="per35 text-left" style="width: 35%"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="8">&nbsp;</td>
            </tr>
            @forelse ($retorno['trocaEscalas'] as $item)
                <tr>
                    <td class="text-center">{{ $item['EMPRESA'] }}</td>
                    <td class="text-center">{{ $item['MATRICULA'] }}</td>
                    <td class="text-left">{{ $item['COLABORADOR'] }}</td>
                    <td class="text-center">{{ $item['ESCALA'] }}</td>
                    <td class="text-center">{{ $item['TURMA'] }}</td>
                    <td class="text-center">{{ $item['DATA_ALTERACAO'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['retTrocaEscalas'])
    <strong>TIPO : RETORNO DE TROCA DE ESCALAS DO NEXTI PARA O PROTHEUS</strong>
    <table>
        <thead>
            <td class="per5 text-center" style="width: 5%"><strong>EMPRESA</strong></td>
            <td class="per10 text-center" style="width: 10%"><strong>MATRICULA</strong></td>
            <td class="per30 text-left" style="width: 30%"><strong>COLABORADOR</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>ESCALA</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>TURMA</strong></td>
            <td class="per10 text-center" style="width: 10%"><strong>DATA_ALTERAÇÃO</strong></td>
            <td class="per35 text-left" style="width: 35%"><strong>OBSERVAÇÃO</strong></td>
            <td class="per35 text-left"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="8">&nbsp;</td>
            </tr>
            @forelse ($retorno['retTrocaEscalas'] as $item)
                <tr>
                    <td class="text-center">{{ $item['EMPRESA'] }}</td>
                    <td class="text-center">{{ $item['MATRICULA'] }}</td>
                    <td class="text-left">{{ $item['COLABORADOR'] }}</td>
                    <td class="text-center">{{ $item['ESCALA'] }}</td>
                    <td class="text-center">{{ $item['TURMA'] }}</td>
                    <td class="text-center">{{ $item['DATA_ALTERACAO'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['trocaPostos'])
    <strong>TIPO : ENVIO DE TROCA DE POSTOS DO PROTHEUS PARA O NEXTI</strong>
    <table>
        <thead>
            <td class="per5 text-center" style="width: 5%"><strong>EMPRESA</strong></td>
            <td class="per10 text-center" style="width: 10%"><strong>MATRICULA</strong></td>
            <td class="per30 text-left" style="width: 30%"><strong>COLABORADOR</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>POSTO</strong></td>
            <td class="per10 text-center" style="width: 10%"><strong>DATA_ALTERAÇÃO</strong></td>
            <td class="per40 text-left" style="width: 40%"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="8">&nbsp;</td>
            </tr>
            @forelse ($retorno['trocaPostos'] as $item)
                <tr>
                    <td class="text-center">{{ $item['EMPRESA'] }}</td>
                    <td class="text-center">{{ $item['MATRICULA'] }}</td>
                    <td class="text-left">{{ $item['COLABORADOR'] }}</td>
                    <td class="text-center">{{ $item['POSTO'] }}</td>
                    <td class="text-center">{{ $item['DATA_ALTERACAO'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($retorno['retTrocaPostos'])
    <strong>TIPO : RETORNO DE TROCA DE POSTOS DO NEXTI PARA O PROTHEUS</strong>
    <table>
        <thead>
            <td class="per5 text-center" style="width: 5%"><strong>EMPRESA</strong></td>
            <td class="per10 text-center" style="width: 10%"><strong>MATRICULA</strong></td>
            <td class="per30 text-left" style="width: 30%"><strong>COLABORADOR</strong></td>
            <td class="per5 text-center" style="width: 5%"><strong>POSTO</strong></td>
            <td class="per10 text-center" style="width: 10%"><strong>DATA_ALTERAÇÃO</strong></td>
            <td class="per40 text-left" style="width: 40%"><strong>OBSERVAÇÃO</strong></td>
        </thead>
        <tbody>
            <tr>
                <td colspan="8">&nbsp;</td>
            </tr>
            @forelse ($retorno['retTrocaPostos'] as $item)
                <tr>
                    <td class="text-center">{{ $item['EMPRESA'] }}</td>
                    <td class="text-center">{{ $item['MATRICULA'] }}</td>
                    <td class="text-left">{{ $item['COLABORADOR'] }}</td>
                    <td class="text-center">{{ $item['POSTO'] }}</td>
                    <td class="text-center">{{ $item['DATA_ALTERACAO'] }}</td>
                    <td class="text-left">{{ $item['OBSERVACAO'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">SEM ÍTENS PARA EXIBIR</td>
                </tr>
            @endforelse
            <tr>
                <td colspan="3">&nbsp;</td>
            </tr>
        </tbody>
    </table>
@endif

