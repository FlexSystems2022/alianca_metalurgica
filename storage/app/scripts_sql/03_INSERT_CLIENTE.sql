
-- TERÁ POR PADRÃO APENAS ALIANÇA METALÚRGICA

-- INSERT INTO nexti.dbo.FLEX_CLIENTE
-- SELECT 
-- CTT010.R_E_C_N_O_,
-- CTT010.CTT_FILIAL AS NUMEMP,
-- '01' AS CODFIL,
-- 0 AS ID,
-- CTT010.R_E_C_N_O_ AS IDEXTERNO,
-- LTRIM(RTRIM(CTT010.CTT_CUSTO)) AS CODOEM,
-- CONCAT(
--     LTRIM(RTRIM(CTT010.CTT_CUSTO)) COLLATE Latin1_General_CI_AS,
--     ' - ',
--     LTRIM(RTRIM(CTT010.CTT_DESC01)) COLLATE Latin1_General_CI_AS
-- ) AS NOMOEM,
-- 0 AS TIPO,
-- 0 AS SITUACAO,
-- '' AS OBSERVACAO,
-- 'CCU' AS ORIGEM
-- FROM ALIANCA.dbo.CTT010
-- JOIN nexti.dbo.FLEX_EMPRESA
-- 	ON nexti.dbo.FLEX_EMPRESA.CODFIL = CTT010.CTT_FILIAL collate database_default
-- WHERE CTT010.CTT_BLOQ = 2
-- AND CTT010.D_E_L_E_T_ = ''
-- AND (
-- 	EXISTS(
-- 		SELECT * FROM ALIANCA.dbo.SRE010
-- 		WHERE SRE010.RE_CCP = CTT010.CTT_CUSTO
-- 		AND SUBSTRING(RE_FILIALP,1,2) = CTT010.CTT_FILIAL
-- 	)
-- 	OR 
-- 	EXISTS(
-- 		SELECT * FROM ALIANCA.dbo.SRE010
-- 		WHERE SRE010.RE_CCD = CTT010.CTT_CUSTO
-- 		AND SUBSTRING(RE_FILIALD,1,2) = CTT010.CTT_FILIAL
-- 	)
-- 	OR EXISTS(
-- 		SELECT * FROM ALIANCA.dbo.SRA010
-- 		WHERE SRA010.D_E_L_E_T_ = ''
-- 		AND (SRA010.RA_DEMISSA = '' OR SRA010.RA_DEMISSA IS NULL) 
-- 		AND SRA010.RA_CC = CTT010.CTT_CUSTO
-- 	)
-- )
-- AND NOT EXISTS(
-- 	SELECT * FROM nexti.dbo.FLEX_CLIENTE
-- 	WHERE nexti.dbo.FLEX_CLIENTE.IDEXTERNO = CTT010.R_E_C_N_O_ 
-- )