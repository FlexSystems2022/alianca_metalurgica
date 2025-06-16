UPDATE nexti.dbo.FLEX_COLABORADOR
SET TIPO = 1, SITUACAO = 0
WHERE SITUACAO = 2 
AND OBSERVACAO LIKE '%Já existe um registro salvo para o campo externalId com valor%'