INSERT INTO flexsystems.dbo.FLEX_AVISOS
SELECT
flexsystems.dbo.FLEX_COLABORADOR.ID AS IDCOLABORADOR,
flexsystems.dbo.FLEX_COLABORADOR.NUMEMP,
flexsystems.dbo.FLEX_COLABORADOR.CODFIL,
flexsystems.dbo.FLEX_COLABORADOR.TIPCOL,
flexsystems.dbo.FLEX_COLABORADOR.NUMCAD,
flexsystems.dbo.FLEX_COLABORADOR.NOMFUN,
flexsystems.dbo.FLEX_COLABORADOR.CPF,
'Informes' AS NOME,
CONVERT(DATE, '2025-01-01', 120) AS STARTDATE,
CONVERT(DATE, '2025-12-31', 120) AS FINISHDATE,
CONVERT(DATE, '2025-01-01', 120) AS STARTDATE_AQUI,
CONVERT(DATE, '2025-12-31', 120) AS FINISHDATE_AQUI,
CONVERT(DATE, GETDATE(), 120) AS DATAPGTO,
0 AS REMOVIDO,
'0' AS ARQUIVO,
0 AS TIPO,
0 AS SITUACAO,
'' AS OBSERVACAO,
0 AS ID
FROM flexsystems.dbo.FLEX_COLABORADOR
WHERE (
	FLEX_COLABORADOR.DATADEMISSAO = '' 
	OR FLEX_COLABORADOR.DATADEMISSAO IS NULL
)
AND NOT EXISTS(
	SELECT * FROM flexsystems.dbo.FLEX_AVISOS
	WHERE flexsystems.dbo.FLEX_AVISOS.NUMEMP = flexsystems.dbo.FLEX_COLABORADOR.NUMEMP
	AND flexsystems.dbo.FLEX_AVISOS.CODFIL = flexsystems.dbo.FLEX_COLABORADOR.CODFIL
	AND flexsystems.dbo.FLEX_AVISOS.NUMCAD = flexsystems.dbo.FLEX_COLABORADOR.NUMCAD
	AND flexsystems.dbo.FLEX_AVISOS.STARTDATE = CONVERT(DATE, '2025-01-01', 120)
	AND flexsystems.dbo.FLEX_AVISOS.NOME = 'Informes'
)
AND flexsystems.dbo.FLEX_COLABORADOR.ID > 0