INSERT INTO nexti.dbo.FLEX_TROCA_EMPRESA
SELECT 
COLAB_ANTIGO.NUMEMP,
COLAB_ANTIGO.CODFIL,
COLAB_ANTIGO.TIPCOL,
COLAB_ANTIGO.NUMCAD,
SRE010.RE_DATA,
COLAB_NOVO.NUMEMP AS EMPATU,
COLAB_NOVO.CODFIL AS CODFILATU,
COLAB_NOVO.NUMCAD AS CADATU,
0 AS TIPO,
0 AS SITUACAO,
'' AS OBSERVACAO,
0 AS ID,
COLAB_ANTIGO.IDEXTERNO AS IDEXTERNO_ANTIGO,
COLAB_NOVO.IDEXTERNO AS IDEXTERNO_NOVO
FROM ALIANCA.dbo.SRE010 SRE010
JOIN ALIANCA.dbo.SRA010 SRA_OLD
    ON SRA_OLD.RA_MAT = SRE010.RE_MATD
    AND SRA_OLD.RA_FILIAL = SRE010.RE_FILIALD
JOIN nexti.dbo.FLEX_COLABORADOR COLAB_ANTIGO
    ON COLAB_ANTIGO.IDEXTERNO = SRA_OLD.R_E_C_N_O_ 
    AND COLAB_ANTIGO.ID > 0
JOIN nexti.dbo.FLEX_EMPRESA EMPRESA_OLD
	ON EMPRESA_OLD.NUMEMP = COLAB_ANTIGO.NUMEMP

JOIN ALIANCA.dbo.SRA010 SRA_NOVO
    ON SRA_NOVO.RA_MAT = SRE010.RE_MATP
    AND SRA_NOVO.RA_FILIAL = SRE010.RE_FILIALP
JOIN nexti.dbo.FLEX_COLABORADOR COLAB_NOVO
    ON COLAB_NOVO.IDEXTERNO = SRA_NOVO.R_E_C_N_O_ 
JOIN nexti.dbo.FLEX_EMPRESA EMPRESA_NOVA
	ON EMPRESA_NOVA.NUMEMP = COLAB_NOVO.NUMEMP
	
WHERE SRE010.RE_DATA >= '20250101'
AND (
	(CAST(SRE010.RE_FILIALD AS VARCHAR(100)) + CAST(SRE010.RE_MATD AS VARCHAR(100)))
	<>
	(CAST(SRE010.RE_FILIALP AS VARCHAR(100)) + CAST(SRE010.RE_MATP AS VARCHAR(100)))
)
AND NOT EXISTS (
	SELECT * FROM nexti.dbo.FLEX_TROCA_EMPRESA
	WHERE nexti.dbo.FLEX_TROCA_EMPRESA.IDEXTERNO_ANTIGO = COLAB_ANTIGO.IDEXTERNO
	AND nexti.dbo.FLEX_TROCA_EMPRESA.IDEXTERNO_NOVO = COLAB_NOVO.IDEXTERNO
	AND nexti.dbo.FLEX_TROCA_EMPRESA.DATALT = SRE010.RE_DATA
)