@php
$tipo = '';
$dicionarioTipo = array(
'1' => 'ENVIO DE AUSÊNCIAS DE SENIOR PARA O NEXTI',
'2' => 'CARGOS',
'3' => 'COLABORADORES',
'4' => 'CADASTRO DE ESCALA',
'5' => 'HORÁRIOS',
'6' => 'CADASTRO DE POSTOS',
'7' => 'CADASTRO DE SITUAÇÕES',
'8' => 'CLIENTES',
'9' => 'ENVIO DE TROCA DE ESCALAS DE SENIOR PARA O NEXTI',
'10' => 'TROCA DE POSTOS',
'11' => 'RETORNO DE AUSÊNCIAS NEXTI PARA SENIOR',
'12' => 'RETORNO DE TROCA DE ESCALA NEXTI PARA SENIOR',
'13' => 'ENVIO CONTRACHEQUES DO SENIOR PARA NEXTI',
'14' => 'RETORNO TROCA POSTOS NEXTI PARA SENIOR'
);
@endphp

<table>
	<thead>
		<td class="per25 tex-center">ÍNDICE</td>
		<td class="per25 tex-center">DESCRIÇÃO</td>
		<td class="per25 tex-center">DATA</td>
		<td class="per25 tex-center">MENSAGEM</td>
	</thead>
	<tbody>
		@forelse ($retorno as $item)
		@if ($tipo != $item['tipo'])
		@php $tipo = $item['tipo']; @endphp

		<tr>
			<td colspan="3">&nbsp;</td>
		</tr>
		<tr>
			<td colspan="3"><strong>TIPO : {{ $dicionarioTipo[$item['tipo']] }}</strong></td>
		</tr>

		@endif
		<tr>
			<td class="per25 tex-center">{{ $item['idexterno'] ?? "Sem ID EXTERNO" }}</td>
			<td class="per25 tex-center">{{ $item['indice'] ?? "Sem índice" }}</td>
			<td class="per25 tex-center">{{ $item['data'] ?? "" }}</td>
			<td class="per25 tex-center" style="padding-left: 30px">{{ $item['mensagem'] ?? "Sem mensagem"}}</td>
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