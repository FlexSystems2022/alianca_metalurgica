UPDATE nexti.dbo.FLEX_TROCA_POSTO_PROTHEUS
SET nexti.dbo.FLEX_TROCA_POSTO_PROTHEUS.TIPO = 3,
nexti.dbo.FLEX_TROCA_POSTO_PROTHEUS.SITUACAO = 0
WHERE nexti.dbo.FLEX_TROCA_POSTO_PROTHEUS.ID > 0
AND nexti.dbo.FLEX_TROCA_POSTO_PROTHEUS.TIPO_TROCA = 'P'
AND NOT EXISTS(
	SELECT * FROM ALIANCA.dbo.SRE010
	JOIN ALIANCA.dbo.SRA010
		ON SRA010.RA_FILIAL = SRE010.RE_FILIALP
		AND SRA010.RA_MAT = SRE010.RE_MATP
	JOIN nexti.dbo.FLEX_COLABORADOR
		ON FLEX_COLABORADOR.IDEXTERNO = SRA010.R_E_C_N_O_
		AND nexti.dbo.FLEX_COLABORADOR.ID > 0
	WHERE SRE010.D_E_L_E_T_ = '' 
	AND nexti.dbo.FLEX_TROCA_POSTO_PROTHEUS.IDEXTERNO = nexti.dbo.FLEX_COLABORADOR.IDEXTERNO
	AND nexti.dbo.FLEX_TROCA_POSTO_PROTHEUS.CODCCU  = SRE010.RE_CCP collate database_default
	AND nexti.dbo.FLEX_TROCA_POSTO_PROTHEUS.INIATU = SRE010.RE_DATA
)