INSERT INTO nexti.dbo.FLEX_SITUACAO
SELECT
*
FROM(
	SELECT 
	0 AS R_E_C_N_O_,
	0 AS NUMEMP,
	0 AS ID,
	RCM010.RCM_TIPO AS IDEXTERNO,
	RCM010.RCM_TIPO AS CODSIT,
	RTRIM((CAST(LOWER(RCM010.RCM_TIPO) AS VARCHAR(100)) + ' - ' + CAST(LOWER(RCM010.RCM_DESCRI) AS VARCHAR(100)))) AS DESSIT,
	0 AS TIPO,
	0 AS SITUACAO,
	'' AS OBSERVACAO
	FROM ALIANCA.dbo.RCM010 RCM010
	WHERE RCM010.D_E_L_E_T_ = ''
	AND NOT EXISTS (
	    SELECT * FROM nexti.dbo.FLEX_SITUACAO
	    WHERE LOWER(nexti.dbo.FLEX_SITUACAO.CODSIT) = LOWER(RCM010.RCM_TIPO) collate database_default
	)
) GERAL
WHERE GERAL.CODSIT <> ''
GROUP BY 
GERAL.R_E_C_N_O_, 
GERAL.NUMEMP, 
GERAL.ID, 
GERAL.IDEXTERNO, 
GERAL.CODSIT, 
GERAL.DESSIT,
GERAL.TIPO, 
GERAL.SITUACAO, 
GERAL.OBSERVACAO
